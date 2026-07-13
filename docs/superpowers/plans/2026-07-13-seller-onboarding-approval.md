# Seller Onboarding & Admin Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a supplier register as a Seller (self-service or Admin-initiated), verify their email / set their password via a signed activation link, and have Admin approve or reject self-registered applicants — with a working `/seller` Filament panel guard/login already scaffolded, ready for the next plan to add real seller-facing content (product listings) to.

**Architecture:** `sellers`/`seller_documents` tables already exist (built in the Foundation & Catalog phase) with every column this plan needs — no new migrations. `Seller` becomes a real `Authenticatable`/`FilamentUser` on its own `seller` guard, mirroring the existing `Staff` pattern exactly. Two onboarding paths converge on the same signed-URL activation endpoint but diverge in behavior (self-registered → email verified, awaits Admin; Admin-created → sets password, approved immediately). Admin manages applicants through a new `SellerResource` in the existing `/admin` panel.

**Tech Stack:** Laravel 11, Filament v3, Laravel signed routes, Laravel `Mail` (existing best-effort-notification pattern from the RFQ phase).

## Global Constraints

- Full requirements: `docs/superpowers/specs/2026-07-12-catalog-cms-rfq-design.md`, "Seller Onboarding Workflow" section — read before deviating.
- No new migrations. `sellers` already has `status`, `created_by`, `rejection_reason`, `email_verified_at`, `approved_at`, `approved_by`, `password`, `remember_token` — every column this plan needs.
- Only `approved` sellers may log in to the `/seller` panel — enforced via `Seller::canAccessPanel()`, the same pattern `Staff::canAccessPanel()` already uses.
- `content_editor` and `sales` staff roles have **no** access to seller management at all (stricter than Category/Product, which both allow `content_editor`) — `SellerPolicy` is `admin`-only across every ability.
- Self-registered sellers require **both** email verification and explicit Admin approval (two gates). Admin-created sellers require only activation (setting their password) — Admin creating the account already is the vetting, per the spec.
- Password handling is asymmetric by design: self-registered sellers set their password at registration time; Admin-created sellers have no real password until they activate — the `password` column is NOT NULL, so an Admin-created seller gets a random, unusable placeholder (`Hash::make(Str::random(40))`) at creation time, overwritten when they actually activate. This is safe because login also requires `status = 'approved'`, which an Admin-created seller only reaches at the moment they set their real password.
- Rejection reason and approval outcome reach the seller only by email — it's their only channel, since they can't log in until approved. Wrap every such send in try/catch (log, don't throw), matching the RFQ phase's "email is a best-effort side channel, never lose the record" convention.
- Activation links are signed routes with a 7-day expiry (`URL::temporarySignedRoute`), not a separate DB token column.
- `Seller` does **not** implement Laravel's `MustVerifyEmail` contract — `email_verified_at` is a plain timestamp set directly by this plan's custom two-path activation controller, not Laravel's built-in single-path verification system.
- Out of scope for this plan (a separate follow-on plan): the `/seller` panel's actual seller-facing content — product listings, quote-count widget, profile/document self-management. This plan only builds the panel's guard/login scaffold (empty dashboard) so that follow-on plan can add resources to it without touching auth/panel wiring again.
- TDD: write the failing test before the implementation, for every task that has application logic.
- Commit after every task.

---

### Task 1: Seller authentication and the `/seller` Filament panel scaffold

**Files:**
- Modify: `app/Models/Seller.php`, `config/auth.php`
- Create: `app/Providers/Filament/SellerPanelProvider.php` (generated then edited)
- Test: `tests/Feature/SellerPanelAccessTest.php`

**Interfaces:**
- Produces: `App\Models\Seller` implementing `Illuminate\Foundation\Auth\User` (Authenticatable) and `Filament\Models\Contracts\FilamentUser`, with `guard_name`-equivalent behavior via the new `seller` guard, and `canAccessPanel(Panel $panel): bool` returning true only for panel id `seller` AND `status === 'approved'`. A booted `/seller` Filament panel (empty dashboard, no resources yet) reachable at `/seller`, login at the Filament-generated login route for that panel.
- Consumes: existing `sellers` table (Foundation & Catalog phase).

- [ ] **Step 1: Write the failing test**

`tests/Feature/SellerPanelAccessTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Seller;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_seller_can_access_the_seller_panel(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);

        $this->assertTrue($seller->canAccessPanel(Filament::getPanel('seller')));
    }

    public function test_a_pending_seller_cannot_access_the_seller_panel(): void
    {
        $seller = Seller::factory()->create(['status' => 'pending_admin_approval']);

        $this->assertFalse($seller->canAccessPanel(Filament::getPanel('seller')));
    }

    public function test_a_rejected_seller_cannot_access_the_seller_panel(): void
    {
        $seller = Seller::factory()->create(['status' => 'rejected']);

        $this->assertFalse($seller->canAccessPanel(Filament::getPanel('seller')));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=SellerPanelAccessTest
```

Expected: FAIL — `Seller` doesn't implement `canAccessPanel()` yet, and the `seller` panel isn't registered.

- [ ] **Step 3: Update the `Seller` model**

`app/Models/Seller.php`:

```php
<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Seller extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'company_name', 'contact_person', 'phone', 'email', 'password',
        'business_address', 'gst_number', 'status', 'created_by',
        'rejection_reason', 'email_verified_at', 'approved_at', 'approved_by',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function customAttributes(): MorphMany
    {
        return $this->morphMany(CustomAttribute::class, 'attributable');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'seller' && $this->isApproved();
    }
}
```

- [ ] **Step 4: Add the `seller` guard and provider to `config/auth.php`**

In the `guards` array, add (alongside the existing `web` and `staff` entries):

```php
'seller' => [
    'driver' => 'session',
    'provider' => 'sellers',
],
```

In the `providers` array, add:

```php
'sellers' => [
    'driver' => 'eloquent',
    'model' => App\Models\Seller::class,
],
```

- [ ] **Step 5: Generate the `/seller` panel**

```bash
php artisan make:filament-panel seller
```

Expected: creates `app/Providers/Filament/SellerPanelProvider.php` and registers it in `bootstrap/providers.php`, the same way `filament:install --panels` did for `AdminPanelProvider`. If this exact command doesn't exist in the installed Filament version, check `php artisan list filament` for the equivalent (Filament v3 added multi-panel scaffolding commands; the exact name may differ by point release) — don't hand-write the provider from scratch without checking first.

- [ ] **Step 6: Edit the generated panel provider**

Replace the contents of `app/Providers/Filament/SellerPanelProvider.php` with (adjust only if the generated file's boilerplate differs structurally from `AdminPanelProvider.php` — read that file first for the established pattern in this codebase):

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SellerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('seller')
            ->path('seller')
            ->login()
            ->authGuard('seller')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Seller/Resources'), for: 'App\\Filament\\Seller\\Resources')
            ->discoverPages(in: app_path('Filament/Seller/Pages'), for: 'App\\Filament\\Seller\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Seller/Widgets'), for: 'App\\Filament\\Seller\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
```

Note the resource/page/widget discovery paths are `Filament/Seller/...`, deliberately distinct from `/admin`'s `Filament/Resources` etc. — this is a separate panel with its own resource namespace so the two panels never accidentally share or collide over the same Resource classes. That directory doesn't exist yet (this plan adds no seller-panel resources) — if Filament's discovery errors on a missing directory rather than silently finding zero resources, create `app/Filament/Seller/Resources/.gitkeep` as a minimal fix and note it in your report.

Do **not** call `->default()` on this panel — `/admin` should remain the application's default panel.

- [ ] **Step 7: Run test to verify it passes**

```bash
php artisan test --filter=SellerPanelAccessTest
```

Expected: PASS (3 tests)

- [ ] **Step 8: Manual verification**

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Visit `http://127.0.0.1:8000/seller` — expect a redirect to a login page (no seller session yet). Confirm no fatal errors on boot (check `php artisan route:list --path=seller` shows panel routes registered).

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "Add seller authentication and the /seller Filament panel scaffold"
```

---

### Task 2: Public seller self-registration

**Files:**
- Create: `app/Http/Requests/StoreSellerRegistrationRequest.php`
- Create: `app/Http/Controllers/Seller/RegistrationController.php`
- Create: `resources/views/seller/register.blade.php`, `resources/views/seller/registration-submitted.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SellerRegistrationTest.php`

**Interfaces:**
- Produces: `GET /seller/register` (name `seller.register`), `POST /seller/register` (name `seller.register.store`), `GET /seller/register/submitted` (name `seller.registration.submitted`). On valid submission: creates a `Seller` (`status = 'pending_email_verification'`, `created_by = 'self'`), creates a `SellerDocument` per uploaded file, sends the activation email (Task 3 builds the Mailable this depends on — for this task, just call `Mail::to(...)->send(new SellerActivationMail($seller))`, which won't exist until Task 3; write this task's controller referencing that class name now, and Task 3 will make it real — see the note in Step 3 below).
- Consumes: `Seller`, `SellerDocument` (existing models).

**Note on task ordering**: this task's controller references `App\Mail\SellerActivationMail`, which doesn't exist until Task 3. Write the controller code exactly as specified below (it won't autoload-error — PHP only resolves the class name when the line actually executes); the RED step will fail with a "class not found" error when the mail-sending line runs, which is an *expected* RED reason distinct from the ones you're testing — don't worry about it. GREEN for this task's own tests will require Task 3's Mailable to exist, so if you're executing tasks in order, do Task 3's Mailable class (just the class + a minimal view) before finishing this task's GREEN step, or coordinate with whoever does. If you're an autonomous implementer executing tasks strictly in order, the simplest path is: implement this task's controller, then note in your report that final GREEN verification for `SellerRegistrationTest` depends on Task 3, don't force it prematurely — report DONE_WITH_CONCERNS if Task 3 hasn't landed yet in your working tree, rather than stubbing a fake Mailable class.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/SellerRegistrationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Mail\SellerActivationMail;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_registration_creates_a_pending_seller_and_sends_the_activation_email(): void
    {
        Mail::fake();
        Storage::fake('public');

        $response = $this->post(route('seller.register.store'), array_merge($this->validPayload(), [
            'documents' => [UploadedFile::fake()->create('gst-certificate.pdf', 100, 'application/pdf')],
        ]));

        $response->assertRedirect(route('seller.registration.submitted'));

        $this->assertDatabaseHas('sellers', [
            'email' => 'asha@raotraders.example',
            'status' => 'pending_email_verification',
            'created_by' => 'self',
        ]);

        $seller = Seller::where('email', 'asha@raotraders.example')->firstOrFail();
        $this->assertCount(1, $seller->documents);

        Mail::assertSent(SellerActivationMail::class, fn ($mail) => $mail->seller->is($seller));
    }

    public function test_registration_with_a_duplicate_email_is_rejected(): void
    {
        Seller::factory()->create(['email' => 'asha@raotraders.example']);

        $response = $this->post(route('seller.register.store'), $this->validPayload());

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('sellers', 1);
    }

    public function test_registration_with_an_invalid_gst_number_is_rejected(): void
    {
        $response = $this->post(route('seller.register.store'), $this->validPayload(['gst_number' => 'not-a-gstin']));

        $response->assertSessionHasErrors(['gst_number']);
        $this->assertDatabaseCount('sellers', 0);
    }

    public function test_registration_with_mismatched_passwords_is_rejected(): void
    {
        $response = $this->post(route('seller.register.store'), $this->validPayload(['password_confirmation' => 'somethingelse']));

        $response->assertSessionHasErrors(['password']);
        $this->assertDatabaseCount('sellers', 0);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Rao Traders',
            'contact_person' => 'Asha Rao',
            'email' => 'asha@raotraders.example',
            'phone' => '9876543210',
            'business_address' => '123 Industrial Estate, Mumbai',
            'gst_number' => '27AAAAA0000A1Z5',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ], $overrides);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=SellerRegistrationTest
```

Expected: FAIL — the route doesn't exist yet.

- [ ] **Step 3: Write the form request**

`app/Http/Requests/StoreSellerRegistrationRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreSellerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255', 'unique:sellers,email'],
            'business_address' => ['required', 'string', 'max:500'],
            'gst_number' => ['required', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Seller/RegistrationController.php`:

```php
<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSellerRegistrationRequest;
use App\Mail\SellerActivationMail;
use App\Models\Seller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('seller.register');
    }

    public function store(StoreSellerRegistrationRequest $request): RedirectResponse
    {
        $seller = Seller::create([
            'company_name' => $request->validated('company_name'),
            'contact_person' => $request->validated('contact_person'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'business_address' => $request->validated('business_address'),
            'gst_number' => $request->validated('gst_number'),
            'password' => Hash::make($request->validated('password')),
            'status' => 'pending_email_verification',
            'created_by' => 'self',
        ]);

        foreach ($request->file('documents', []) as $file) {
            $seller->documents()->create([
                'label' => $file->getClientOriginalName(),
                'file_path' => $file->store('seller-documents', 'public'),
                'uploaded_at' => now(),
            ]);
        }

        try {
            Mail::to($seller->email)->send(new SellerActivationMail($seller));
        } catch (\Throwable $exception) {
            Log::error('Failed to send seller activation email.', [
                'seller_id' => $seller->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('seller.registration.submitted');
    }
}
```

- [ ] **Step 5: Register the routes**

Add to `routes/web.php`:

```php
use App\Http\Controllers\Seller\RegistrationController;

Route::get('/seller/register', [RegistrationController::class, 'create'])->name('seller.register');
Route::post('/seller/register', [RegistrationController::class, 'store'])->name('seller.register.store');
Route::view('/seller/register/submitted', 'seller.registration-submitted')->name('seller.registration.submitted');
```

- [ ] **Step 6: Write the views**

`resources/views/seller/register.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Become a Seller')

@section('content')
    <h1>Seller Registration</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('seller.register.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Contact Person</label>
                <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person') }}" required>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Business Address</label>
            <textarea name="business_address" class="form-control" rows="2" required>{{ old('business_address') }}</textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">GST Number</label>
            <input type="text" name="gst_number" class="form-control" value="{{ old('gst_number') }}" placeholder="e.g. 27AAAAA0000A1Z5" required>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Business Documents (optional)</label>
            <input type="file" name="documents[]" class="form-control" multiple>
            <div class="form-text">GST certificate, trade license, or similar. PDF, JPG, or PNG, max 5MB each.</div>
        </div>

        <button type="submit" class="btn btn-primary">Register</button>
    </form>
@endsection
```

`resources/views/seller/registration-submitted.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Registration Submitted')

@section('content')
    <h1>Check your email</h1>
    <p>We've sent an activation link to the email address you provided. Click the link to verify your email and submit your application for review.</p>
@endsection
```

- [ ] **Step 7: Run tests to verify they pass (only once Task 3's `SellerActivationMail` exists — see the note above)**

```bash
php artisan test --filter=SellerRegistrationTest
```

Expected: PASS (4 tests)

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "Add public seller self-registration"
```

---

### Task 3: Signed-URL activation (self-registered and Admin-created paths)

**Files:**
- Create: `app/Http/Requests/SetSellerPasswordRequest.php`
- Create: `app/Http/Controllers/Seller/ActivationController.php`
- Create: `app/Mail/SellerActivationMail.php`
- Create: `resources/views/emails/seller-activation.blade.php`, `resources/views/seller/activation-complete.blade.php`, `resources/views/seller/set-password.blade.php`, `resources/views/seller/activation-invalid.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SellerActivationTest.php`

**Interfaces:**
- Produces: `App\Mail\SellerActivationMail` (constructed with a `Seller $seller`, builds its own 7-day signed activation URL). `GET /seller/activate/{seller}` (name `seller.activate`, `signed` middleware) and `POST /seller/activate/{seller}` (name `seller.activate.store`, `signed` middleware). Self-registered sellers hitting the `GET` route transition `pending_email_verification` → `pending_admin_approval` (email verified). Admin-created sellers hitting the `GET` route see a password-setting form; submitting it via `POST` transitions them straight to `approved`.
- Consumes: `Seller` (existing model, updated in Task 1).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/SellerActivationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SellerActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_self_registered_seller_moves_to_pending_admin_approval_after_activating(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_email_verification',
            'created_by' => 'self',
            'email_verified_at' => null,
        ]);

        $url = URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id]);

        $response = $this->get($url);

        $response->assertOk();
        $seller->refresh();
        $this->assertSame('pending_admin_approval', $seller->status);
        $this->assertNotNull($seller->email_verified_at);
    }

    public function test_an_admin_created_seller_sees_a_set_password_form_on_the_activation_link(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_email_verification',
            'created_by' => 'admin',
        ]);

        $url = URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('seller.set-password');
        $this->assertSame('pending_email_verification', $seller->fresh()->status);
    }

    public function test_an_admin_created_seller_becomes_approved_immediately_after_setting_a_password(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_email_verification',
            'created_by' => 'admin',
        ]);

        $url = URL::temporarySignedRoute('seller.activate.store', now()->addDays(7), ['seller' => $seller->id]);

        $response = $this->post($url, [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk();
        $seller->refresh();
        $this->assertSame('approved', $seller->status);
        $this->assertNotNull($seller->approved_at);
        $this->assertTrue(Hash::check('newpassword123', $seller->password));
    }

    public function test_a_request_without_a_valid_signature_is_rejected(): void
    {
        $seller = Seller::factory()->create(['status' => 'pending_email_verification']);

        $response = $this->get(route('seller.activate', ['seller' => $seller->id]));

        $response->assertForbidden();
    }

    public function test_an_already_activated_seller_sees_the_invalid_link_page(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);

        $url = URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('seller.activation-invalid');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=SellerActivationTest
```

Expected: FAIL — the route doesn't exist yet.

- [ ] **Step 3: Write the form request**

`app/Http/Requests/SetSellerPasswordRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetSellerPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Seller/ActivationController.php`:

```php
<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\SetSellerPasswordRequest;
use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ActivationController extends Controller
{
    public function show(Request $request, Seller $seller): View
    {
        if ($seller->status !== 'pending_email_verification') {
            return view('seller.activation-invalid');
        }

        if ($seller->created_by === 'admin') {
            return view('seller.set-password', ['seller' => $seller]);
        }

        $seller->update([
            'email_verified_at' => now(),
            'status' => 'pending_admin_approval',
        ]);

        return view('seller.activation-complete', ['seller' => $seller]);
    }

    public function store(SetSellerPasswordRequest $request, Seller $seller): View
    {
        if ($seller->status !== 'pending_email_verification' || $seller->created_by !== 'admin') {
            return view('seller.activation-invalid');
        }

        $seller->update([
            'password' => Hash::make($request->validated('password')),
            'email_verified_at' => now(),
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return view('seller.activation-complete', ['seller' => $seller]);
    }
}
```

- [ ] **Step 5: Register the routes**

Add to `routes/web.php`:

```php
use App\Http\Controllers\Seller\ActivationController;

Route::get('/seller/activate/{seller}', [ActivationController::class, 'show'])->middleware('signed')->name('seller.activate');
Route::post('/seller/activate/{seller}', [ActivationController::class, 'store'])->middleware('signed')->name('seller.activate.store');
```

- [ ] **Step 6: Write the Mailable**

`app/Mail/SellerActivationMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class SellerActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Activate your seller account');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller-activation',
            with: [
                'seller' => $this->seller,
                'activationUrl' => URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $this->seller->id]),
            ],
        );
    }
}
```

- [ ] **Step 7: Write the views**

`resources/views/emails/seller-activation.blade.php`:

```blade
<h1>Activate your seller account</h1>

@if ($seller->created_by === 'admin')
    <p>An administrator has created a seller account for {{ $seller->company_name }}. Click below to set your password and activate your account.</p>
@else
    <p>Thanks for registering {{ $seller->company_name }}. Click below to verify your email address.</p>
@endif

<p><a href="{{ $activationUrl }}">Activate Account</a></p>
```

`resources/views/seller/activation-complete.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Account Activated')

@section('content')
    @if ($seller->status === 'approved')
        <h1>Your account is ready</h1>
        <p>Your password has been set and your account is active. You can now log in to the seller portal.</p>
    @else
        <h1>Email verified</h1>
        <p>Thanks — your email address has been verified. Our team will review your application and get back to you shortly.</p>
    @endif
@endsection
```

`resources/views/seller/set-password.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Set Your Password')

@section('content')
    <h1>Set Your Password</h1>
    <p>An administrator has created a seller account for <strong>{{ $seller->company_name }}</strong>. Set a password to activate it.</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ url()->full() }}" method="POST">
        @csrf
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Activate Account</button>
    </form>
@endsection
```

`resources/views/seller/activation-invalid.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Link No Longer Valid')

@section('content')
    <h1>This activation link is no longer valid</h1>
    <p>It may have already been used, or your account status has changed. If you need help, please contact our support team.</p>
@endsection
```

- [ ] **Step 8: Run tests to verify they pass**

```bash
php artisan test --filter=SellerActivationTest
```

Expected: PASS (5 tests)

- [ ] **Step 9: Go back and finish Task 2's GREEN step**

Now that `SellerActivationMail` exists, run:

```bash
php artisan test --filter=SellerRegistrationTest
```

Expected: PASS (4 tests) — if it isn't, that's a real bug to fix now, not defer.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "Add signed-URL seller activation for both self-registered and Admin-created paths"
```

---

### Task 4: `SellerPolicy` and the Admin-side `SellerResource`

**Files:**
- Create: `app/Policies/SellerPolicy.php`
- Create: `app/Mail/SellerApproved.php`, `app/Mail/SellerRejected.php`
- Create: `resources/views/emails/seller-approved.blade.php`, `resources/views/emails/seller-rejected.blade.php`
- Create: `app/Filament/Resources/SellerResource.php`, `app/Filament/Resources/SellerResource/Pages/{ListSellers,CreateSeller,EditSeller}.php` (generated then `CreateSeller` further edited in Task 5), `app/Filament/Resources/SellerResource/RelationManagers/DocumentsRelationManager.php` (generated then edited)
- Test: `tests/Feature/SellerPolicyTest.php`, `tests/Feature/SellerResourceTest.php`

**Interfaces:**
- Produces: `App\Policies\SellerPolicy` — every ability (`viewAny`/`view`/`create`/`update`/`delete`) returns true only for `hasRole('admin')`. A Filament resource at `/admin/sellers` with an `approve`/`reject` table action pair, each only visible when `status === 'pending_admin_approval'`, sending `SellerApproved`/`SellerRejected` email on success (best-effort, try/catch).
- Consumes: `Seller` (Task 1), `Staff`/roles (existing).

- [ ] **Step 1: Write the failing policy test**

`tests/Feature/SellerPolicyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_only_admin_can_view_sellers(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $this->assertTrue($admin->can('viewAny', Seller::class));
        $this->assertFalse($editor->can('viewAny', Seller::class));
        $this->assertFalse($sales->can('viewAny', Seller::class));
    }

    public function test_only_admin_can_manage_a_specific_seller(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $seller = Seller::factory()->create();

        $this->assertTrue($admin->can('update', $seller));
        $this->assertFalse($editor->can('update', $seller));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=SellerPolicyTest
```

Expected: FAIL — the generated `SellerPolicy` doesn't have these rules yet (or doesn't exist).

- [ ] **Step 3: Write the policy**

`app/Policies/SellerPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Seller;
use App\Models\Staff;

class SellerPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function view(Staff $staff, Seller $seller): bool
    {
        return $staff->hasRole('admin');
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function update(Staff $staff, Seller $seller): bool
    {
        return $staff->hasRole('admin');
    }

    public function delete(Staff $staff, Seller $seller): bool
    {
        return $staff->hasRole('admin');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=SellerPolicyTest
```

Expected: PASS (2 tests)

- [ ] **Step 5: Write the failing resource test**

`tests/Feature/SellerResourceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SellerResource\Pages\ListSellers;
use App\Mail\SellerApproved;
use App\Mail\SellerRejected;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SellerResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_content_editor_gets_a_403_visiting_the_sellers_list(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $response = $this->get('/admin/sellers');

        $response->assertForbidden();
    }

    public function test_approving_a_pending_seller_sets_status_and_sends_email(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create(['status' => 'pending_admin_approval']);

        Livewire::test(ListSellers::class)
            ->callTableAction('approve', $seller);

        $seller->refresh();
        $this->assertSame('approved', $seller->status);
        $this->assertSame($admin->id, $seller->approved_by);
        $this->assertNotNull($seller->approved_at);

        Mail::assertSent(SellerApproved::class, fn ($mail) => $mail->seller->is($seller));
    }

    public function test_rejecting_a_pending_seller_stores_the_reason_and_sends_email(): void
    {
        Mail::fake();

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create(['status' => 'pending_admin_approval']);

        Livewire::test(ListSellers::class)
            ->callTableAction('reject', $seller, data: ['rejection_reason' => 'Documents did not match business name.']);

        $seller->refresh();
        $this->assertSame('rejected', $seller->status);
        $this->assertSame('Documents did not match business name.', $seller->rejection_reason);

        Mail::assertSent(SellerRejected::class, fn ($mail) => $mail->seller->is($seller));
    }

    public function test_approve_and_reject_are_not_available_for_a_seller_not_pending_approval(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create(['status' => 'approved']);

        Livewire::test(ListSellers::class)
            ->assertTableActionHidden('approve', $seller)
            ->assertTableActionHidden('reject', $seller);
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

```bash
php artisan test --filter=SellerResourceTest
```

Expected: FAIL — `SellerResource` doesn't exist yet. If the exact `Livewire::test(...)->callTableAction(...)`/`assertTableActionHidden(...)` API doesn't match the installed Filament/Livewire versions, verify against vendor source the same way earlier tasks in this codebase have (check `.superpowers/sdd/` reports from the RFQ phase's Task 5/6 for the established approach) and adapt — don't guess.

- [ ] **Step 7: Write the mailables and views**

`app/Mail/SellerApproved.php`:

```php
<?php

namespace App\Mail;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your seller account has been approved');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.seller-approved', with: ['seller' => $this->seller]);
    }
}
```

`app/Mail/SellerRejected.php`:

```php
<?php

namespace App\Mail;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Update on your seller application');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.seller-rejected', with: ['seller' => $this->seller]);
    }
}
```

`resources/views/emails/seller-approved.blade.php`:

```blade
<h1>You're approved!</h1>
<p>Congratulations — {{ $seller->company_name }}'s seller account has been approved. You can now log in and start listing products.</p>
```

`resources/views/emails/seller-rejected.blade.php`:

```blade
<h1>Update on your application</h1>
<p>Thank you for applying to become a seller. Unfortunately, we're unable to approve {{ $seller->company_name }}'s application at this time.</p>

@if ($seller->rejection_reason)
    <p><strong>Reason:</strong> {{ $seller->rejection_reason }}</p>
@endif
```

- [ ] **Step 8: Generate the resource and relation manager**

```bash
php artisan make:filament-resource Seller
php artisan make:filament-relation-manager SellerResource documents label
```

- [ ] **Step 9: Replace the generated `form()`, `table()`, `getRelations()`, `getPages()`**

`app/Filament/Resources/SellerResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SellerResource\Pages;
use App\Filament\Resources\SellerResource\RelationManagers\DocumentsRelationManager;
use App\Mail\SellerApproved;
use App\Mail\SellerRejected;
use App\Models\Seller;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SellerResource extends Resource
{
    protected static ?string $model = Seller::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static function statusOptions(): array
    {
        return [
            'pending_email_verification' => 'Pending Email Verification',
            'pending_admin_approval' => 'Pending Admin Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'suspended' => 'Suspended',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('company_name')->required(),
            TextInput::make('contact_person')->required(),
            TextInput::make('phone')->required(),
            TextInput::make('email')->email()->required(),
            TextInput::make('business_address'),
            TextInput::make('gst_number')->label('GST Number'),
            Select::make('status')
                ->options(static::statusOptions())
                ->required()
                ->visible(fn (string $operation): bool => $operation !== 'create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->searchable(),
                TextColumn::make('contact_person'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('gst_number')->label('GST Number'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_by')->label('Created By'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(static::statusOptions()),
                SelectFilter::make('created_by')->options([
                    'self' => 'Self-registered',
                    'admin' => 'Admin-created',
                ]),
            ])
            ->actions([
                Action::make('approve')
                    ->visible(fn (Seller $record) => $record->status === 'pending_admin_approval')
                    ->requiresConfirmation()
                    ->action(function (Seller $record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_at' => now(),
                            'approved_by' => auth('staff')->id(),
                        ]);

                        try {
                            Mail::to($record->email)->send(new SellerApproved($record));
                        } catch (\Throwable $exception) {
                            Log::error('Failed to send seller approval email.', [
                                'seller_id' => $record->id,
                                'exception' => $exception->getMessage(),
                            ]);
                        }
                    }),
                Action::make('reject')
                    ->visible(fn (Seller $record) => $record->status === 'pending_admin_approval')
                    ->form([
                        Textarea::make('rejection_reason')->label('Reason')->required(),
                    ])
                    ->action(function (Seller $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        try {
                            Mail::to($record->email)->send(new SellerRejected($record));
                        } catch (\Throwable $exception) {
                            Log::error('Failed to send seller rejection email.', [
                                'seller_id' => $record->id,
                                'exception' => $exception->getMessage(),
                            ]);
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellers::route('/'),
            'create' => Pages\CreateSeller::route('/create'),
            'edit' => Pages\EditSeller::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 10: Edit the documents relation manager**

`app/Filament/Resources/SellerResource/RelationManagers/DocumentsRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\SellerResource\RelationManagers;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')->required(),
            FileUpload::make('file_path')->directory('seller-documents')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('uploaded_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['uploaded_at'] = now();

                        return $data;
                    }),
            ])
            ->defaultSort('uploaded_at', 'desc');
    }
}
```

- [ ] **Step 11: Run test to verify it passes**

```bash
php artisan test --filter=SellerResourceTest
```

Expected: PASS (4 tests)

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "Add SellerPolicy and the Admin-side SellerResource with approve/reject"
```

---

### Task 5: Admin-initiated seller creation

**Files:**
- Modify: `app/Filament/Resources/SellerResource/Pages/CreateSeller.php`
- Test: `tests/Feature/SellerAdminCreationTest.php`

**Interfaces:**
- Produces: creating a Seller through `/admin/sellers/create` always sets `created_by = 'admin'`, `status = 'pending_email_verification'`, and a random unusable placeholder password — then sends `SellerActivationMail` after the record is created (best-effort, try/catch). This closes the loop with Task 3's activation flow: hitting the resulting signed link takes the seller straight to `approved` (no `pending_admin_approval` stop), per the spec's "Admin creating the account already is the vetting" rule.
- Consumes: `SellerResource` (Task 4), `SellerActivationMail` (Task 3).

- [ ] **Step 1: Write the failing test**

`tests/Feature/SellerAdminCreationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\SellerResource\Pages\CreateSeller;
use App\Mail\SellerActivationMail;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class SellerAdminCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creating_a_seller_sends_an_activation_email_and_the_seller_becomes_approved_immediately_after_activating(): void
    {
        Mail::fake();

        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateSeller::class)
            ->fillForm([
                'company_name' => 'Vikram Supplies',
                'contact_person' => 'Vikram Singh',
                'phone' => '9876500000',
                'email' => 'vikram@vikramsupplies.example',
                'business_address' => '45 MG Road, Pune',
                'gst_number' => '27BBBBB1111B1Z6',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $seller = Seller::where('email', 'vikram@vikramsupplies.example')->firstOrFail();
        $this->assertSame('pending_email_verification', $seller->status);
        $this->assertSame('admin', $seller->created_by);

        Mail::assertSent(SellerActivationMail::class, fn ($mail) => $mail->seller->is($seller));

        $url = URL::temporarySignedRoute('seller.activate.store', now()->addDays(7), ['seller' => $seller->id]);
        $this->post($url, ['password' => 'brandnewpass1', 'password_confirmation' => 'brandnewpass1']);

        $this->assertSame('approved', $seller->fresh()->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=SellerAdminCreationTest
```

Expected: FAIL — `CreateSeller` doesn't set `created_by`/`status`/placeholder password or send the email yet, so the seller record's `created_by` will be whatever the (currently hidden-on-create, per Task 4) form default is, and no mail is sent.

- [ ] **Step 3: Edit the `CreateSeller` page**

`app/Filament/Resources/SellerResource/Pages/CreateSeller.php`:

```php
<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Resources\SellerResource;
use App\Mail\SellerActivationMail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateSeller extends CreateRecord
{
    protected static string $resource = SellerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = 'admin';
        $data['status'] = 'pending_email_verification';
        $data['password'] = Hash::make(Str::random(40));

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            Mail::to($this->record->email)->send(new SellerActivationMail($this->record));
        } catch (\Throwable $exception) {
            Log::error('Failed to send seller activation email.', [
                'seller_id' => $this->record->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=SellerAdminCreationTest
```

Expected: PASS (1 test)

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "Wire Admin-initiated seller creation to the activation email flow"
```

---

### Task 6: End-to-end verification

**Files:** none (verification only)

**Interfaces:** none — this task confirms Tasks 1–5 integrate correctly.

- [ ] **Step 1: Full reset and full test suite**

```bash
php artisan migrate:fresh --seed
php artisan test
```

Expected: no errors; all tests pass (should be roughly 69 tests — the ~50 from the RFQ phase plus this plan's new tests; the exact count doesn't matter as long as everything is green).

- [ ] **Step 2: Manual smoke test**

```bash
php artisan serve
```

- Visit `http://127.0.0.1:8000/seller/register`, fill out and submit the form with a valid GSTIN (e.g. `27AAAAA0000A1Z5`). Confirm you land on the "check your email" page.
- Check the log (`storage/logs/laravel.log`, since `MAIL_MAILER` is likely `log` in this dev environment) for the activation email and its link. Visit that link. Confirm you see "Email verified" and the seller's status in the DB (via tinker) is `pending_admin_approval`.
- Log into `/admin` as the seeded admin, open "Sellers," confirm the new applicant appears with status "Pending Admin Approval." Click "Approve." Confirm status becomes "Approved" and check the log for the approval email.
- Repeat registration with a second email, but this time click "Reject" (Admin, with a reason) instead of Approve. Confirm status becomes "Rejected," the reason is stored, and the log shows a rejection email containing that reason.
- As the seeded admin, use "Sellers → New" to create a seller directly (no password field should appear). Confirm the log shows an activation email. Follow its link, confirm you see a "Set Your Password" form (not the plain "email verified" message). Submit a password. Confirm status becomes "Approved" immediately (no "pending admin approval" stop).
- Attempt to visit `/seller` while logged in as neither of the above sellers (i.e., logged out) — confirm you're redirected to a seller login page, not the dashboard.

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "Seller Onboarding & Admin Approval plan complete: verified end-to-end"
```
