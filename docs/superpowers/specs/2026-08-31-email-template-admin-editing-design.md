# Admin-Editable Email Templates — Design Spec

Date: 2026-08-31
Status: Approved

## Purpose

All transactional email content in this app is currently hardcoded: a
`Mailable` class per email (`app/Mail/*.php`) paired with a bare, unstyled
Blade fragment (`resources/views/emails/*.blade.php`). Changing any copy —
subject, wording, CC/BCC — means editing code and redeploying. This spec
gives Admin/Content Editor staff an `/admin/email-templates` screen to edit
and publish that content directly, without a developer in the loop for
every wording change.

Eleven emails move into this system: the 9 existing transactional
`Mailable`s (`seller-activation` splits into two templates — see below),
plus the 2 new self-service password-reset emails introduced by the
companion spec, `2026-08-31-self-service-password-reset-design.md`
(`staff_password_reset`, `buyer_password_reset`, plus the seller reset
email, which currently uses Filament's default notification and moves into
this system too — `seller_password_reset`).

Two emails stay hardcoded, not admin-editable:

- `product-edit-ready-for-acceptance` — its body is a generated diff table
  (`@foreach` over changed fields), not prose. There's no "copy" to edit.
- `seller-import-stuck` — an internal devops alert ("check the queue worker
  in Railway"), not customer-facing content.

## Why not let admins edit raw Blade

The existing views use real Blade logic: `@if`/`@else` branches, a
`@foreach` diff table, and an embedded Blade component
(`<x-product-thumbnail>`). Storing and compiling admin-authored Blade on
every send would mean admin-entered CMS content executes as PHP on the
server — a code-execution path through a form field, regardless of how
trusted staff accounts are. This spec instead uses a small, deliberately
non-Turing-complete token-substitution engine: plain `{{token}}`
interpolation and `{{#token}}...{{/token}}` presence-based sections, with
no code execution of any kind. See "Token engine" below.

## Data model

One new table, `email_templates`:

| column | type | notes |
|---|---|---|
| `key` | string, unique | binds a system template to the `Mailable` that looks it up; free-form for custom templates |
| `label` | string | admin-facing name |
| `is_system` | boolean | true for the fixed rows tied to a `Mailable`; false for freely-created ones |
| `subject` | string | live subject line |
| `body` | text (HTML) | live body |
| `default_cc` | string, nullable | comma-separated email list |
| `default_bcc` | string, nullable | comma-separated email list |
| `draft_subject` | string | editable copy; `Publish` copies this → `subject` |
| `draft_body` | text (HTML) | editable copy; `Publish` copies this → `body` |
| `draft_default_cc` | string, nullable | |
| `draft_default_bcc` | string, nullable | |
| timestamps | | |

A seeder creates the system rows (`is_system = true`) with `subject`/`body`
ported verbatim from the current hardcoded copy, and `draft_*` columns
initialized equal to the live columns — so immediately after migrating,
every email renders byte-for-byte the same as before. Nothing changes
until an admin edits a draft and hits Publish.

Custom templates (`is_system = false`) are created via the admin UI (see
below), are not referenced by any code path yet, and exist as a content
library for future use.

## Token engine

A new `App\Services\EmailTemplateRenderer` (single class, no external
templating library):

- `{{token}}` — replaced with the HTML-escaped value from the tokens array
  passed in by the calling `Mailable`. Two tokens per relevant template
  (the diff table — not applicable, see "two emails stay hardcoded" above
  — and the product thumbnail in `quote_request_received`) carry
  pre-rendered, trusted HTML instead of escaped text; everything else is
  escaped.
- `{{#token}}...{{/token}}` — the enclosed text is kept only if `token`'s
  value is non-empty, dropped otherwise. Replaces the `@if ($x)` optional
  blocks in the current views (seller-rejected's rejection reason,
  seller-approved's activation link, quote-request-confirmation's product
  line, quote-request-received's product/message blocks).
- Each template key has a fixed, hardcoded whitelist of tokens it accepts,
  defined in `EmailTemplateRenderer` itself — not admin-configurable.
  `{{...}}` text outside that whitelist is left as literal text, never
  evaluated. There is no property access into models, no arbitrary
  expression evaluation, no recursion.
- A small set of **global tokens** (`{{site_name}}`, sourced from the
  existing `Setting::current()` used elsewhere) are available on every
  template, system or custom, since custom templates have no
  Mailable-specific whitelist of their own.

Per-key token whitelists:

| key | tokens | sections |
|---|---|---|
| `seller_activation_admin_created` | `company_name`, `activation_url` | — |
| `seller_activation_self_registered` | `company_name`, `activation_url` | — |
| `seller_approved` | `company_name` | `activation_url` |
| `seller_rejected` | `company_name` | `rejection_reason` |
| `quote_request_confirmation` | `first_name`, `quote_number` | `product` (contains `product_name`) |
| `quote_request_received` | `reason`, `full_name`, `email`, `phone`, `company`, `admin_url` | `product` (contains `product_name`, `product_url`, `product_thumbnail_html`), `message` (contains `message_text`) |
| `product_listing_live` | `product_name`, `product_url` | — |
| `staff_invitation` | `staff_name`, `login_url`, `temporary_password` | — |
| `staff_password_reset` | `staff_name`, `reset_url` | — |
| `seller_password_reset` | `company_name`, `reset_url` | — |
| `buyer_password_reset` | `name`, `reset_url` | — |

## Mailable integration

Each Mailable's `envelope()`/`content()` resolves its `EmailTemplate` by
fixed key (memoized on the instance to avoid a duplicate query), builds its
token array from data it already has, and renders through the service.
Example (`SellerRejected`):

```php
public function envelope(): Envelope
{
    $template = $this->template(); // memoized EmailTemplate::where('key', 'seller_rejected')->firstOrFail()
    return new Envelope(
        subject: $template->subject,
        cc: $template->ccAddresses(),
        bcc: $template->bccAddresses(),
    );
}

public function content(): Content
{
    $html = app(EmailTemplateRenderer::class)->render($this->template()->body, [
        'company_name' => $this->seller->company_name,
        'rejection_reason' => $this->seller->rejection_reason,
    ]);
    return new Content(htmlString: $html);
}
```

`EmailTemplate::ccAddresses()`/`bccAddresses()` split the comma-separated
column, trim, and filter to valid emails — empty column means no extra
recipients, matching every existing send today.

The 9 `resources/views/emails/*.blade.php` files for templated emails are
deleted once ported into the seeder; the DB row becomes the single source
of truth, so there's no drift between "the file" and "what admins see."
The 2 hardcoded emails (`product-edit-ready-for-acceptance`,
`seller-import-stuck`) keep their existing Blade views untouched.

## Filament admin UI

New `EmailTemplateResource` at `/admin/email-templates`.

**Table**: `label`, `key`, a System/Custom badge, `subject`, a "Modified"
badge (`draft_* != *` on any column).

**Edit form**: `draft_subject` (TextInput), `draft_body` (RichEditor,
matching how Page content is already authored), `draft_default_cc`/
`draft_default_bcc` (TextInput, comma-separated, validated as a list of
emails). A `Placeholder` lists the template's valid tokens. For system
templates, `key` and `label` are locked/read-only. For custom templates,
`label` is free text that auto-generates `key` via `Str::slug()` on
`afterStateUpdated` — same pattern as `PageResource`'s title→slug field —
editable before first save.

**Actions**:
- `Preview` — renders the draft with a fixed sample-data fixture per key
  (for custom templates, only global tokens resolve; unknown tokens render
  as literal text) in a modal. Never sends mail.
- `Publish` — copies `draft_*` → live columns. Visible only when modified.
- `Reset Draft` — copies live → `draft_*`, discarding in-progress edits.
- `Create` — custom templates only (system rows are seeded, not
  user-creatable).
- `Delete` — custom templates only; hidden for `is_system = true` rows.

## RBAC

New `email_templates` area added to `RoleSeeder::AREAS`. `admin`: `full`,
`content_editor`: `full` (matching `pages`), `sales`: none.
`EmailTemplatePolicy` follows the exact shape of `PagePolicy`
(`viewAny`/`view` on `read|write|full`, `create`/`update` on
`write|full`, `delete` on `full`) — `create`/`delete` additionally check
`! $record->is_system` where applicable.

## Testing

- `EmailTemplateRenderer` unit tests: token substitution, section
  presence/absence, unknown-token literal passthrough, CC/BCC parsing
  (valid/invalid/empty).
- One feature test per ported Mailable, asserting subject/recipients/
  body-contains-expected-text still match pre-migration behavior — guards
  the port itself.
- `EmailTemplateResource` Filament tests: system template key/label
  locked, no delete action on system rows; custom template create with
  auto-slug, edit, delete; Publish/Reset Draft copy the right columns;
  RBAC (content_editor allowed, sales forbidden).

## Rollout

One migration (`create_email_templates_table`), one seeder populating the
8 system rows that come from existing Mailables (`seller_activation_*`
counts as 2) with ported copy, then the Mailable/view changes in the same
deploy (so there's never a window where a Mailable looks up a template key
that doesn't exist yet). Small commits, tests passing at each one.

The 3 reset-email rows (`staff_password_reset`, `seller_password_reset`,
`buyer_password_reset`) don't exist yet at this point — they're added by
`2026-08-31-self-service-password-reset-design.md`'s own migration/seeder
when those Mailables are built, using the infrastructure this spec
creates.

## Out of scope

- `product-edit-ready-for-acceptance` and `seller-import-stuck` stay
  hardcoded (see Purpose).
- No campaign/broadcast sending — custom templates are a content library
  for future code to reference, not something this feature sends on its
  own.
- No template versioning/history beyond the single draft/live pair.
- No branded HTML layout wrapper (header/footer/logo) around email bodies
  — bodies stay bare fragments as they are today; this can be a follow-up.
