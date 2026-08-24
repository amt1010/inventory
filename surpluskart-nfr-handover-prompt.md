# Claude Code Handover Prompt — SurplusKart.in Non-Functional Hardening

Copy everything below into Claude Code (in VS Code) as your starting prompt. Adjust the bracketed placeholders before running.

---

## Context

I'm working on **SurplusKart.in**, a web portal (no payment/checkout functionality — [describe: e.g. listings/classifieds/inquiry-based marketplace for surplus inventory]). It's hosted on **Railway**, custom domain `www.surpluskart.in` is live with HTTPS working correctly.

Tech stack: [FILL IN — e.g. Node.js/Express + React frontend + Postgres, or your actual stack]
Repo structure: [FILL IN — monorepo / separate frontend-backend / etc.]

I need you to help me systematically go through non-functional hardening across security, performance, reliability, and compliance. Work through this as a series of discrete, reviewable changes — don't do everything in one giant commit. Explain each change briefly before making it, and flag anything that needs a decision from me (e.g. choice of monitoring tool, third-party service) rather than assuming.

## Scope of work

### 1. Security
- [ ] Audit all forms (inquiry forms, signup, contact, listing submission) for input validation and sanitization — prevent XSS and injection
- [ ] Add rate limiting on public-facing APIs and forms (inquiry submission, search, any auth endpoints) to prevent spam/scraping/abuse
- [ ] Confirm no secrets, API keys, or credentials are hardcoded — move everything to Railway environment variables; add a `.env.example` with dummy values
- [ ] Run a dependency vulnerability scan (`npm audit` / equivalent for the stack) and fix or document any high/critical findings
- [ ] Review CORS configuration — restrict to known origins only
- [ ] Add security headers (CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, HSTS) — check what's already set at the Railway/edge level vs what needs app-level middleware
- [ ] If user accounts exist: review password hashing (bcrypt/argon2), session handling, and auth token expiry

### 2. Performance
- [ ] Audit Core Web Vitals (LCP, CLS, INP) using Lighthouse — report current scores before/after changes
- [ ] Check image handling — ensure listing/product images are compressed, served in modern formats (WebP/AVIF), and lazy-loaded
- [ ] Evaluate whether a CDN in front of Railway (e.g. Cloudflare) makes sense — don't implement without confirming with me first, since it touches DNS
- [ ] Add caching headers for static assets
- [ ] Review database queries on high-traffic pages (listing pages, search) for N+1 queries or missing indexes

### 3. Reliability & Availability
- [ ] Set up uptime monitoring (propose 1-2 free-tier options — e.g. UptimeRobot, Better Uptime — and I'll pick)
- [ ] Set up error tracking (propose Sentry or equivalent, free tier) and wire it into the app
- [ ] Confirm/document the database backup strategy on Railway — check what's automatic vs what I need to configure, and document the restore process
- [ ] Add basic structured logging for key events (form submissions, errors, key user actions) so issues are traceable

### 4. Compliance & Legal (India-specific, no payment involved)
- [ ] Draft/add a Privacy Policy page — covers what personal data is collected (names, emails, phone numbers from forms) and how it's used, per India's DPDP Act, 2023
- [ ] Draft/add Terms of Service page
- [ ] Add cookie consent banner if using analytics/tracking cookies
- [ ] Flag any other India e-commerce/portal disclosure requirements relevant to a non-payment listings model — ask me before assuming which apply, since our model doesn't do transactions

### 5. SEO & Discoverability
- [ ] Add `sitemap.xml` and `robots.txt`
- [ ] Add structured data (schema.org) for listings if applicable
- [ ] Confirm mobile responsiveness across key pages

### 6. CI/CD & Environment Hygiene
- [ ] Confirm Railway auto-deploy setup — check if staging and production environments are properly separated
- [ ] Add a basic CI check (lint + build) on PRs if not already present

## Working agreement
- Prioritize in this order: **Security → Reliability (backups, monitoring, error tracking) → Performance → Compliance pages → SEO → CI/CD polish**
- Before installing new dependencies or third-party services, tell me what you're adding and why
- For anything requiring a business/legal decision (privacy policy wording, which monitoring tool, CDN setup), stop and ask rather than guessing
- Keep changes in small, reviewable commits with clear messages
- At the end of each work session, give me a short summary of what changed and what's still open

Start by reading through the current codebase structure, then give me a short report of what's already in place vs what's missing from the checklist above, before making any changes.
