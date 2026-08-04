# Pending Items

Tracker for follow-up work identified after the 2026-08-04 merge of
`feature/issues-23-26` (Homepage + Product Listing Modernist reskin, quote
numbering, Postmark mail switch) into `master` and deploy to PROD. Pull this
file up when starting on any of these.

## 1. Email sending — blocked on a real domain

Production mail was switched from SMTP to Postmark's HTTP API because
Railway blocks all outbound SMTP ports below its Pro plan (see
`DEPLOYMENT.md`'s troubleshooting section). Postmark needs a verified
sender, which needs a domain. **Owner: revisit once the domain is live.**

- [ ] Set `POSTMARK_TOKEN` in Railway's app-service (and queue-worker
      service) environment variables — currently unset in PROD.
- [ ] Verify a Postmark Sender Signature (or a full sending domain, once
      DNS is available) and point `MAIL_FROM_ADDRESS` at it — Postmark
      rejects sends from an unverified `From` address.
- [ ] Once a real domain exists, consider moving from a single Sender
      Signature to a verified sending domain (better deliverability,
      no per-address re-verification).
- [ ] After the above, smoke-test every queued mailable: RFQ confirmation
      (`QuoteRequestConfirmation`), seller activation/approval/rejection,
      newsletter signup, product-listing notifications.

## 2. Deploy verification

- [ ] Confirm the Railway deploy triggered by the `master` push
      (`0b1f65e..c6a2db3`) succeeded on **both** the app service and the
      queue-worker service — check the Pre-Deploy Command output for clean
      migrations.
- [ ] Smoke-test the live site: catalog filter/sort, product card + Add to
      RFQ modal, quote-number confirmation flow, homepage blocks
      (hero/deals/trust badges/newsletter).

## 3. Repo housekeeping (low priority)

- [ ] Delete the stale remote branch `origin/feature/issues-23-26` on
      GitHub (23 commits behind what actually shipped) —
      `git push origin --delete feature/issues-23-26`.
- [ ] Pin line endings for `public/` vendor assets (`.gitattributes`) —
      16 Filament JS/CSS files currently flip LF/CRLF in `git status` with
      no real content change, on Windows checkouts.
