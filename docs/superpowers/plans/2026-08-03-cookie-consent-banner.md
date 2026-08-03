# Cookie Consent Banner Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show a one-time-per-session GDPR-style cookie notice banner on the public site, and give visitors a way to reopen it from the footer.

**Architecture:** Pure client-side: a fixed-bottom Bootstrap banner partial included in the public layout, shown/hidden via a session-lifetime browser cookie (no `Expires`/`Max-Age`, so it clears when the browser closes — matching "displayed one time during the session"). The footer already has an unused placeholder (`resources/views/layouts/app.blade.php:140-141`, `data-cookies-placeholder`) — that becomes a real "Cookie Settings" link that re-shows the banner. No new tracking/analytics cookies are introduced by this app, so this is an acknowledgment banner, not a consent-gate for other scripts.

**Tech Stack:** Blade, Bootstrap 5.3 (already loaded), vanilla JS (`document.cookie`), no backend/session state — this is deliberately not tied to Laravel's server-side session so it works identically for guests and logged-in users without touching `SessionController`/CSRF.

## Global Constraints

- Test-first: PHPUnit can only assert the rendered HTML/markup exists (it can't execute JS) — feature tests here assert presence of the banner markup and its wiring attributes, not runtime show/hide behavior. Verify the interactive behavior manually via the `run` skill/browser after implementation.
- Commit frequently, tests passing at each commit.

---

## File Structure

- Create: `resources/views/partials/cookie-consent-banner.blade.php` — the banner markup + inline JS.
- Modify: `resources/views/layouts/app.blade.php` — include the partial before `</body>`; replace the placeholder div (lines 140-141) with a real "Cookie Settings" trigger link.
- Modify: `public/css/site.css` — banner positioning/styling.
- Create: `tests/Feature/CookieConsentBannerTest.php`.

---

### Task 1: Render the banner and footer trigger with correct wiring

**Files:**
- Create: `resources/views/partials/cookie-consent-banner.blade.php`
- Modify: `resources/views/layouts/app.blade.php:139-145`
- Modify: `public/css/site.css`
- Test: `tests/Feature/CookieConsentBannerTest.php`

**Interfaces:**
- Produces: `#cookie-consent-banner` (the banner element, `display: none` by default — JS decides visibility on load), `#cookie-consent-accept` (button), `#cookie-settings-link` (footer trigger, replaces the old placeholder div).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CookieConsentBannerTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class CookieConsentBannerTest extends TestCase
{
    public function test_the_public_layout_renders_the_cookie_consent_banner(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="cookie-consent-banner"', false);
        $response->assertSee('id="cookie-consent-accept"', false);
    }

    public function test_the_footer_has_a_cookie_settings_link_instead_of_the_old_placeholder(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="cookie-settings-link"', false);
        $response->assertDontSee('data-cookies-placeholder', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CookieConsentBannerTest`
Expected: FAIL — neither element exists yet. (Note: this test needs a published `home` Page and seeded nav/settings to render `/` without a 404 — if the base `TestCase` doesn't already seed these for every test, check how other tests hitting `/` — e.g. `tests/Feature/PageRoutingTest.php` — set up their fixtures, and match that setup in this test's `setUp()`/each test method before relying on `GET /` succeeding.)

- [ ] **Step 3: Create the banner partial**

`resources/views/partials/cookie-consent-banner.blade.php`:

```blade
{{-- resources/views/partials/cookie-consent-banner.blade.php --}}
<div id="cookie-consent-banner" class="cookie-consent-banner" style="display: none;" role="dialog" aria-live="polite" aria-label="Cookie notice">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
        <p class="mb-0 small">
            We use cookies to keep you signed in and remember your preferences while you browse.
            We don't use cookies for advertising or tracking.
        </p>
        <button type="button" id="cookie-consent-accept" class="btn btn-primary btn-sm">Accept</button>
    </div>
</div>

<script>
    (function () {
        var COOKIE_NAME = 'cookie_consent_ack';
        var banner = document.getElementById('cookie-consent-banner');
        var acceptBtn = document.getElementById('cookie-consent-accept');
        var settingsLink = document.getElementById('cookie-settings-link');

        function hasConsentCookie() {
            return document.cookie.split('; ').some(function (row) {
                return row.indexOf(COOKIE_NAME + '=') === 0;
            });
        }

        function showBanner() {
            banner.style.display = 'block';
        }

        function hideBanner() {
            banner.style.display = 'none';
        }

        function acceptConsent() {
            // No Max-Age/Expires: a session cookie, cleared when the browser closes,
            // matching "displayed one time during the session".
            document.cookie = COOKIE_NAME + '=1; path=/; SameSite=Lax';
            hideBanner();
        }

        if (!hasConsentCookie()) {
            showBanner();
        }

        acceptBtn.addEventListener('click', acceptConsent);

        if (settingsLink) {
            settingsLink.addEventListener('click', function (event) {
                event.preventDefault();
                showBanner();
            });
        }
    })();
</script>
```

- [ ] **Step 4: Include the partial in the layout and replace the footer placeholder**

In `resources/views/layouts/app.blade.php`, replace lines 140-141:

```blade
                    {{-- Placeholder slot for a future cookie-settings icon/button. --}}
                    <div class="footer-cookies-placeholder" data-cookies-placeholder aria-hidden="true"></div>
```

with:

```blade
                    <a href="#" id="cookie-settings-link" class="small text-muted">Cookie Settings</a>
```

Then, right before the closing `</body>` tag (after the existing Bootstrap JS `<script>` on line 150), add:

```blade
    @include('partials.cookie-consent-banner')
</body>
```

- [ ] **Step 5: Add banner styling**

In `public/css/site.css`, append:

```css
.cookie-consent-banner {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1050;
    background: #212529;
    color: #fff;
    box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.15);
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=CookieConsentBannerTest`
Expected: PASS

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: all PASS

- [ ] **Step 8: Commit**

```bash
git add resources/views/partials/cookie-consent-banner.blade.php resources/views/layouts/app.blade.php public/css/site.css tests/Feature/CookieConsentBannerTest.php
git commit -m "Add a session-scoped cookie consent banner with a footer reopen link"
```

---

## Self-Review Notes

- **Spec coverage:** "GDPR like enablement" ✓ (informational acknowledgment banner — this app sets no tracking/analytics cookies to actually gate), "displayed one time during the session" ✓ (session cookie, no `Max-Age`), footer settings/reopen affordance ✓ (fills the pre-existing placeholder rather than leaving it dead code).
- **No placeholders:** all steps have complete code.
- **Manual verification needed:** JS show/hide/accept/reopen behavior can't be asserted by PHPUnit — after implementing, load the site in a browser (private/incognito window to start with no cookie), confirm the banner shows, Accept hides it and it stays hidden on reload within the same browser session, and the footer "Cookie Settings" link reopens it.
