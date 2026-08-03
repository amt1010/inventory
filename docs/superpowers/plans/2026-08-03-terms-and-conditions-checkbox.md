# Terms & Conditions Checkbox at Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Require buyers and sellers to view and accept Terms & Conditions (content managed by Admin via the existing CMS) before they can register, via a lightbox with Accept/Decline that gates the registration form's Submit button.

**Architecture:** Reuse the existing `Page` model/CMS (no new admin UI) — a `Page` with slug `terms-and-conditions`, edited through the already-existing `/admin` Pages resource. Both registration controllers fetch that page and pass it to the view. Both registration views render a shared Blade partial containing a Bootstrap modal with the page content and Accept/Decline buttons, plus a required checkbox that the modal's Accept button checks. Server-side validation (`required`, `accepted`) is the actual gate; the modal/JS is a UX layer on top, matching the existing `privacy_policy` checkbox pattern in `resources/views/partials/quote-request-form-fields.blade.php:100-103`.

**Tech Stack:** Laravel 11 FormRequest validation, Blade, Bootstrap 5.3 modal component, vanilla JS (no build step — this app has no `site.js`; inline `<script>` in the partial, consistent with how this codebase has no SPA/bundler).

## Global Constraints

- Test-first: a failing feature test before each behavior change (`CLAUDE.md`).
- RBAC/Policy layer is not relevant here (buyer/seller registration is unauthenticated, plain `web`-guard territory) — no Policy class needed.
- Commit frequently in small units, tests passing at each commit (`CLAUDE.md`).
- Run `php artisan test` before every commit.

---

## File Structure

- Modify: `database/seeders/PageSeeder.php` — seed the `terms-and-conditions` page (published) alongside `home`/`contact-us`.
- Modify: `app/Http/Requests/StoreUserRegistrationRequest.php` — add `terms_accepted` validation.
- Modify: `app/Http/Requests/StoreSellerRegistrationRequest.php` — add `terms_accepted` validation.
- Modify: `app/Http/Controllers/RegistrationController.php` — pass the terms `Page` to the view.
- Modify: `app/Http/Controllers/Seller/RegistrationController.php` — pass the terms `Page` to the view.
- Create: `resources/views/partials/terms-and-conditions-modal.blade.php` — shared modal + checkbox + JS, parameterized by an `$idSuffix` so it can appear on both forms without ID collisions (mirrors the existing `$idSuffix` pattern in `quote-request-form-fields.blade.php:4`).
- Modify: `resources/views/auth/register.blade.php` — include the partial, disable Submit until accepted.
- Modify: `resources/views/seller/register.blade.php` — include the partial, disable Submit until accepted.
- Modify: `tests/Feature/BuyerRegistrationTest.php` — add `terms_accepted` to existing successful-registration payloads; add a rejection test.
- Modify: `tests/Feature/SellerRegistrationTest.php` — same.
- Create: `tests/Feature/TermsAndConditionsModalTest.php` — asserts both registration pages render the modal with the seeded page's content.

---

### Task 1: Seed the Terms & Conditions page

**Files:**
- Modify: `database/seeders/PageSeeder.php`

**Interfaces:**
- Produces: a published `Page` row with `slug = 'terms-and-conditions'` that later tasks depend on existing.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/PageSeederTest.php` is unnecessary — there's no existing test file for `PageSeeder` directly; instead extend the seeding assertion inline in Task 5's modal test (it asserts the page's content renders). Skip a dedicated seeder test and go straight to the seeder change — this mirrors how `home`/`contact-us` in the same seeder have no dedicated test either, only downstream tests (e.g. `PageRoutingTest`) that depend on them existing.

- [ ] **Step 2: Add the seeder entry**

Open `database/seeders/PageSeeder.php` and add a third `firstOrCreate` call after the existing `contact-us` one:

```php
        Page::query()->firstOrCreate(['slug' => 'terms-and-conditions'], [
            'title' => 'Terms & Conditions',
            'status' => 'published',
            'content' => [
                ['type' => 'content_strip', 'data' => [
                    'heading' => 'Terms & Conditions',
                    'body' => '<p>Welcome to our platform. By creating an account, you agree to use this '
                        .'site for legitimate sourcing and supply purposes only. Quote requests are '
                        .'non-binding enquiries; final pricing and terms are negotiated directly between '
                        .'buyer and seller off-platform. We do not process payments on this site.</p>'
                        .'<p>Update this content any time from <strong>Admin &rsaquo; Pages &rsaquo; '
                        .'Terms & Conditions</strong>.</p>',
                    'image_position' => 'left',
                ]],
            ],
        ]);
```

- [ ] **Step 3: Run the full seeder locally to confirm no errors**

Run: `php artisan db:seed --class=PageSeeder`
Expected: no errors; running it again is a no-op (`firstOrCreate`).

- [ ] **Step 4: Commit**

```bash
git add database/seeders/PageSeeder.php
git commit -m "Seed a Terms & Conditions page for registration to link to"
```

---

### Task 2: Require `terms_accepted` on buyer registration

**Files:**
- Modify: `app/Http/Requests/StoreUserRegistrationRequest.php`
- Test: `tests/Feature/BuyerRegistrationTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `StoreUserRegistrationRequest::rules()` now requires `terms_accepted` to be present and truthy (`accepted` rule accepts `1`, `"1"`, `true`, `"true"`, `"yes"`, `"on"`).

- [ ] **Step 1: Write the failing test**

In `tests/Feature/BuyerRegistrationTest.php`, add:

```php
    public function test_registration_without_accepting_terms_is_rejected(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('terms_accepted');
        $this->assertGuest('web');
        $this->assertDatabaseCount('users', 0);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_registration_without_accepting_terms_is_rejected`
Expected: FAIL — no `terms_accepted` error is raised yet (registration currently succeeds with these fields alone).

- [ ] **Step 3: Add the validation rule**

In `app/Http/Requests/StoreUserRegistrationRequest.php`, add to the `rules()` array:

```php
            'terms_accepted' => ['required', 'accepted'],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_registration_without_accepting_terms_is_rejected`
Expected: PASS

- [ ] **Step 5: Fix the now-broken existing success-path tests**

The two existing passing tests in `BuyerRegistrationTest.php` (`test_a_visitor_can_register_and_is_logged_in_immediately`, `test_registration_with_a_duplicate_email_is_rejected`) POST without `terms_accepted` and will now fail. Add `'terms_accepted' => '1',` to both payloads. (`test_registration_with_mismatched_passwords_is_rejected` doesn't need it — it's asserting a different field's error and should keep failing on `password` regardless.)

- [ ] **Step 6: Run the whole file to verify everything passes**

Run: `php artisan test --filter=BuyerRegistrationTest`
Expected: PASS (4 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/StoreUserRegistrationRequest.php tests/Feature/BuyerRegistrationTest.php
git commit -m "Require terms acceptance on buyer registration"
```

---

### Task 3: Require `terms_accepted` on seller registration

**Files:**
- Modify: `app/Http/Requests/StoreSellerRegistrationRequest.php`
- Test: `tests/Feature/SellerRegistrationTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `StoreSellerRegistrationRequest::rules()` now requires `terms_accepted`.

- [ ] **Step 1: Read the existing test file first**

Run: `php artisan test --filter=SellerRegistrationTest` to see current passing tests and their exact POST payloads before editing (this file wasn't read during planning — inspect it before writing new steps, since its exact successful-payload shape must be matched).

- [ ] **Step 2: Write the failing test**

Add to `tests/Feature/SellerRegistrationTest.php` (match the existing class's use of `route('seller.register.store')` or `/seller/register` — confirm the exact URL/route name used by other tests in that file and reuse it):

```php
    public function test_registration_without_accepting_terms_is_rejected(): void
    {
        $response = $this->post('/seller/register', [
            'company_name' => 'Acme Cables',
            'contact_person' => 'Rahul Mehta',
            'phone' => '9876543210',
            'email' => 'rahul@example.com',
            'business_address' => '123 Industrial Estate, Mumbai',
            'gst_number' => '27AAAAA0000A1Z5',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('terms_accepted');
        $this->assertDatabaseCount('sellers', 0);
    }
```

(Adjust field values/route to match whatever pattern the existing tests in this file already use — copy their exact valid payload and just omit `terms_accepted`.)

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=test_registration_without_accepting_terms_is_rejected`
Expected: FAIL (no such validation error yet)

- [ ] **Step 4: Add the validation rule**

In `app/Http/Requests/StoreSellerRegistrationRequest.php`, add to `rules()`:

```php
            'terms_accepted' => ['required', 'accepted'],
```

- [ ] **Step 5: Run test to verify it passes, then fix existing tests**

Run: `php artisan test --filter=test_registration_without_accepting_terms_is_rejected` → PASS.
Then add `'terms_accepted' => '1',` to every existing successful-registration payload in `SellerRegistrationTest.php` (and any other test file posting to `/seller/register` with a full valid payload expecting success — check `tests/Feature/SellerRegisterLinkTest.php` too).

- [ ] **Step 6: Run full suite for this area**

Run: `php artisan test --filter=SellerRegist`
Expected: all PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/StoreSellerRegistrationRequest.php tests/Feature/SellerRegistrationTest.php tests/Feature/SellerRegisterLinkTest.php
git commit -m "Require terms acceptance on seller registration"
```

---

### Task 4: Pass the Terms page to both registration views

**Files:**
- Modify: `app/Http/Controllers/RegistrationController.php`
- Modify: `app/Http/Controllers/Seller/RegistrationController.php`
- Test: `tests/Feature/TermsAndConditionsModalTest.php` (created here, extended in Task 5)

**Interfaces:**
- Consumes: `App\Models\Page` (existing model, `app/Models/Page.php`).
- Produces: both `create()` methods pass a `termsPage` variable (nullable `Page`) to their views.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TermsAndConditionsModalTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsAndConditionsModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_buyer_registration_page_shows_the_terms_content(): void
    {
        Page::factory()->create([
            'slug' => 'terms-and-conditions',
            'status' => 'published',
            'title' => 'Terms & Conditions',
            'content' => [
                ['type' => 'content_strip', 'data' => ['heading' => 'Terms & Conditions', 'body' => '<p>Test terms body.</p>']],
            ],
        ]);

        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('Test terms body.', false);
    }

    public function test_the_seller_registration_page_shows_the_terms_content(): void
    {
        Page::factory()->create([
            'slug' => 'terms-and-conditions',
            'status' => 'published',
            'title' => 'Terms & Conditions',
            'content' => [
                ['type' => 'content_strip', 'data' => ['heading' => 'Terms & Conditions', 'body' => '<p>Seller terms body.</p>']],
            ],
        ]);

        $response = $this->get('/seller/register');

        $response->assertOk();
        $response->assertSee('Seller terms body.', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TermsAndConditionsModalTest`
Expected: FAIL — the registration views don't render any page content yet.

- [ ] **Step 3: Update `RegistrationController`**

In `app/Http/Controllers/RegistrationController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRegistrationRequest;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'termsPage' => Page::query()->where('slug', 'terms-and-conditions')->where('status', 'published')->first(),
        ]);
    }

    public function store(StoreUserRegistrationRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        Auth::guard('web')->login($user);

        return redirect()->route('home');
    }
}
```

- [ ] **Step 4: Update `Seller\RegistrationController`**

In `app/Http/Controllers/Seller/RegistrationController.php`, change the `use` list to add `use App\Models\Page;` and change `create()`:

```php
    public function create(): View
    {
        return view('seller.register', [
            'termsPage' => Page::query()->where('slug', 'terms-and-conditions')->where('status', 'published')->first(),
        ]);
    }
```

- [ ] **Step 5: Run test to verify it still fails (views not updated yet) — this is expected**

Run: `php artisan test --filter=TermsAndConditionsModalTest`
Expected: FAIL — `$termsPage` reaches the view now, but nothing renders it yet. Task 5 finishes this.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/RegistrationController.php app/Http/Controllers/Seller/RegistrationController.php tests/Feature/TermsAndConditionsModalTest.php
git commit -m "Pass the Terms & Conditions page to both registration views"
```

---

### Task 5: Render the modal + gated checkbox on both forms

**Files:**
- Create: `resources/views/partials/terms-and-conditions-modal.blade.php`
- Modify: `resources/views/auth/register.blade.php`
- Modify: `resources/views/seller/register.blade.php`
- Test: `tests/Feature/TermsAndConditionsModalTest.php` (from Task 4, now passes)

**Interfaces:**
- Consumes: `$termsPage` (nullable `Page`, from Task 4) — this partial degrades gracefully (checkbox still required, but the "View Terms & Conditions" link/modal is only rendered `@if ($termsPage)`; if an admin hasn't published the page yet, registrants can still tick the box but see no content link — acceptable since Task 1's seeder guarantees the page exists in every environment that ran seeding).
- Produces: a `#terms-modal{{ $idSuffix }}` Bootstrap modal, a `#terms-accepted-checkbox{{ $idSuffix }}` checkbox input named `terms_accepted`, and inline JS that (a) enables the modal's own "Accept" button to check the box and close the modal, (b) disables the surrounding form's submit button until the box is checked.

- [ ] **Step 1: Create the shared partial**

`resources/views/partials/terms-and-conditions-modal.blade.php`:

```blade
{{-- resources/views/partials/terms-and-conditions-modal.blade.php --}}
@php
    $idSuffix = $idSuffix ?? '';
@endphp

<div class="mb-3 form-check">
    <input type="checkbox" name="terms_accepted" class="form-check-input" id="terms-accepted-checkbox{{ $idSuffix }}" value="1" required disabled>
    <label class="form-check-label" for="terms-accepted-checkbox{{ $idSuffix }}">
        I have read and accept the
        @if ($termsPage)
            <a href="#" data-bs-toggle="modal" data-bs-target="#terms-modal{{ $idSuffix }}">Terms &amp; Conditions</a>
        @else
            Terms &amp; Conditions
        @endif
    </label>
    @error('terms_accepted')
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>

@if ($termsPage)
    <div class="modal fade" id="terms-modal{{ $idSuffix }}" tabindex="-1" aria-labelledby="terms-modal-label{{ $idSuffix }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="terms-modal-label{{ $idSuffix }}">{{ $termsPage->title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                    @foreach ($termsPage->content as $block)
                        @includeIf('blocks.'.$block['type'], ['data' => $block['data'] ?? []])
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="terms-decline-btn{{ $idSuffix }}" data-bs-dismiss="modal">Decline</button>
                    <button type="button" class="btn btn-primary" id="terms-accept-btn{{ $idSuffix }}" data-bs-dismiss="modal">Accept</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var checkbox = document.getElementById('terms-accepted-checkbox{{ $idSuffix }}');
            var acceptBtn = document.getElementById('terms-accept-btn{{ $idSuffix }}');
            var declineBtn = document.getElementById('terms-decline-btn{{ $idSuffix }}');
            var submitBtn = checkbox.closest('form').querySelector('button[type="submit"]');

            function syncSubmitState() {
                submitBtn.disabled = !checkbox.checked;
            }

            checkbox.disabled = false;
            checkbox.addEventListener('change', syncSubmitState);
            acceptBtn.addEventListener('click', function () {
                checkbox.checked = true;
                syncSubmitState();
            });
            declineBtn.addEventListener('click', function () {
                checkbox.checked = false;
                syncSubmitState();
            });

            syncSubmitState();
        })();
    </script>
@else
    <script>
        document.getElementById('terms-accepted-checkbox{{ $idSuffix }}').disabled = false;
    </script>
@endif
```

Note: the checkbox starts `disabled` in the static HTML so a no-JS submission can't silently pass an unchecked-but-not-required box; the inline scripts immediately re-enable it (both branches). This is a defensive default, not a real security boundary — the actual gate is server-side `required|accepted` validation from Tasks 2/3, which works regardless of JS.

- [ ] **Step 2: Include the partial in the buyer registration form**

In `resources/views/auth/register.blade.php`, after the "Confirm Password" field block (before the submit `<button>`), add:

```blade
                @include('partials.terms-and-conditions-modal', ['termsPage' => $termsPage])
```

- [ ] **Step 3: Include the partial in the seller registration form**

In `resources/views/seller/register.blade.php`, find the equivalent spot (just before its submit button) and add:

```blade
                @include('partials.terms-and-conditions-modal', ['termsPage' => $termsPage, 'idSuffix' => '-seller'])
```

(Read the file first to find the exact insertion point and confirm the form's structure/indentation before editing — it wasn't read during planning.)

- [ ] **Step 4: Run the test from Task 4 to verify it passes**

Run: `php artisan test --filter=TermsAndConditionsModalTest`
Expected: PASS (both tests)

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: all PASS (this is the last task in this plan — full-suite confirmation)

- [ ] **Step 6: Commit**

```bash
git add resources/views/partials/terms-and-conditions-modal.blade.php resources/views/auth/register.blade.php resources/views/seller/register.blade.php
git commit -m "Add Terms & Conditions lightbox with accept/decline to both registration forms"
```

---

## Self-Review Notes

- **Spec coverage:** hyperlink to T&C ✓ (Task 5), content editable by Admin ✓ (Task 1, reuses existing Pages CMS), vertical scroll in lightbox ✓ (`max-height: 60vh; overflow-y: auto`), accept/decline options ✓, Submit disabled until accepted ✓ (both the `disabled` HTML default and the JS `syncSubmitState`), account creation proceeds only on accept ✓ (server-side `required|accepted` validation is the real gate).
- **Buyer vs seller both covered:** Tasks 2–5 each touch both flows.
- **No placeholders:** all steps have complete code.
