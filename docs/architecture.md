# Architecture & Process Flow

Diagrams reflect the system as deployed to PROD on Railway (see
`DEPLOYMENT.md`) and the core buyer/seller/staff workflows (see
`CLAUDE.md`). Regenerate/update these whenever the deployment topology or
the RFQ/product-review flow changes materially.

## 1. Production architecture

```mermaid
%%{init: {"flowchart": {"htmlLabels": true, "nodeSpacing": 45, "rankSpacing": 70, "subGraphTitleMargin": {"top": 15, "bottom": 15}}, "themeVariables": {"fontSize": "16px"}}}%%
flowchart TB
    subgraph Clients["Client Browsers"]
        direction LR
        Buyer["Buyer<br/>public catalog,<br/>RFQ, favorites,<br/>quote history"]
        Seller["Seller<br/>/seller panel"]
        Staff["Staff<br/>Admin / Content Editor<br/>/ Sales — /admin panel"]
    end

    subgraph GH["GitHub"]
        Repo[["inventory repo<br/>master branch"]]
    end

    subgraph Railway["Railway Project"]
        direction TB
        Laravel["App Service<br/>(Nixpacks: PHP-FPM + Caddy)<br/>Laravel 11 + Filament v3<br/>web guard / staff guard /<br/>seller guard"]
        Worker["Queue-Worker Service<br/>(no public domain,<br/>no HTTP listener)<br/>php artisan queue:work<br/>--tries=3 --backoff=10<br/>--max-time=3600"]
        MySQL[("MySQL<br/>(Railway plugin)")]
        Redis[("Redis<br/>cache + session +<br/>queue backend")]
        Volume[["Persistent Volume<br/>mounted at<br/>/app/public/storage<br/>product images, seller<br/>docs, spec-sheet PDFs"]]
    end

    subgraph Ext["External Services"]
        direction LR
        Postmark("Postmark<br/>HTTP-API mailer")
        Recaptcha("Google reCAPTCHA<br/>RFQ form spam check")
    end

    Buyer -- HTTPS --> Laravel
    Seller -- "HTTPS /seller" --> Laravel
    Staff -- "HTTPS /admin" --> Laravel

    Repo -- "git push master<br/>triggers auto-deploy" --> Laravel
    Repo -- "same repo/branch,<br/>2nd service" --> Worker

    Laravel -. "Pre-Deploy Command:<br/>migrate --force +<br/>config/route/view cache" .-> MySQL
    Worker -. "Pre-Deploy Command:<br/>migrate --force<br/>(idempotent)" .-> MySQL

    Laravel --> MySQL
    Laravel --> Redis
    Laravel --> Volume
    Laravel -- "queue mail jobs" --> Redis
    Laravel -- "verify RFQ<br/>submissions" --> Recaptcha

    Worker -- "dequeue jobs" --> Redis
    Worker --> MySQL
    Worker -- "send queued email" --> Postmark
```

**Notes**

- Two Railway services run from the same repo/branch: the **app service**
  (handles HTTP traffic) and the **queue-worker service** (processes queued
  mail — RFQ confirmations, seller activation/approval/rejection,
  newsletter). Neither can substitute for the other; queued mail silently
  piles up in `failed_jobs`/the Redis queue if the worker isn't running.
- The Pre-Deploy Command (`railway/init-app.sh`) runs in a throwaway
  container before the traffic-serving container boots — this is why the
  `public` disk points straight at `public_path('storage')` instead of the
  usual `storage:link` symlink (a symlink made in the throwaway container
  never reaches the real one). See `DEPLOYMENT.md` for the full story.
- `SESSION_DRIVER`, `CACHE_STORE`, and `QUEUE_CONNECTION` all ride on the
  same Redis instance in production; local dev uses `sync`/`database`/`file`
  instead (no Redis needed day-to-day).
- Mail is currently blocked on Postmark being configured with a verified
  sender — see `docs/pending-items.md`.

## 2. Process flow — seller listing → staff review → buyer RFQ

```mermaid
%%{init: {"flowchart": {"htmlLabels": true, "nodeSpacing": 40, "rankSpacing": 55, "wrap": true}, "themeVariables": {"fontSize": "16px"}}}%%
flowchart TD
    subgraph SellerFlow["Seller — list a product"]
        direction TB
        S1["Registers at<br/>/seller/register"]
        S2["Status:<br/>pending_email_verification"]
        S3["Opens signed<br/>activation link (email)"]
        S4["Status:<br/>pending_admin_approval"]
        S5["Admin approves seller<br/>in /admin"]
        S6["Status: approved"]
        S7["Creates product;<br/>may propose a new leaf<br/>category inline"]
        S8["Product: pending_review<br/>New category (if any):<br/>draft, proposed_by_seller_id set"]
    end

    subgraph StaffFlow["Staff — review & publish"]
        direction TB
        A1["Admin reviews the<br/>proposed category<br/>(name/slug/parent)"]
        A2["Admin publishes<br/>the category"]
        A3["Admin sets price_display<br/>(ProductPolicy::setPrice)"]
        A4["Product::publish() —<br/>requires category published<br/>+ price_display set"]
        A5["Product status: published<br/>(seller identity never<br/>shown publicly)"]
    end

    subgraph BuyerFlow["Buyer — discover & request a quote"]
        direction TB
        B1["Browses /products/{path}<br/>via CatalogController<br/>(category tree walk)"]
        B2["Opens Add to RFQ<br/>modal on a product card"]
        B3["Submits<br/>POST /quote-requests"]
        B4["QuoteNumberGenerator<br/>assigns YYMMDDHHMM<br/>+ 4-digit sequence"]
        B5["QuoteRequest saved"]
        B6["QuoteRequestConfirmation<br/>mailable queued on Redis"]
        B7["Queue-worker service<br/>sends confirmation<br/>via Postmark"]
    end

    subgraph StaffRFQ["Staff — handle the enquiry"]
        direction TB
        C1["Sales/Admin sees the<br/>QuoteRequest in /admin"]
        C2["Follows up with buyer<br/>off-platform — no<br/>checkout/payment in-app"]
    end

    S1 --> S2 --> S3 --> S4 --> S5 --> S6 --> S7 --> S8
    S8 --> A1 --> A2 --> A3 --> A4 --> A5
    A5 -.->|now visible in catalog| B1
    B1 --> B2 --> B3 --> B4 --> B5 --> B6 --> B7
    B5 --> C1 --> C2
```

**Notes**

- A product can never be marked `published` outside `Product::publish()` —
  that method is the single enforcement point for "category published +
  price set first."
- If the seller didn't propose a new category, `S8`'s category step is
  skipped and the product goes straight into the standard review queue
  against an already-published category.
- Buyer accounts (`web` guard) are optional — the RFQ flow above works for
  guests too; an account only adds `/favorites` and `/my-quote-requests`.
