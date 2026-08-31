# Self-Service Password Reset Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give staff and buyers self-service "forgot password," and close a gap in the seller panel's existing self-service reset — none of the three should let a Clerk-only account (no local password) issue itself a working reset link.

**Architecture:** Staff and sellers use Filament's built-in `passwordReset()` panel feature with a custom request-page override per panel (skip the actual send for Clerk-only accounts while always showing the same response; dispatch the reset-link email through the email-templates system instead of Filament's default notification). Buyers get a small hand-rolled controller in the same style as `SessionController`/`RegistrationController`, reusing the `password_reset_tokens` table/`users` broker that already exists in `config/auth.php` but is currently unused.

**Tech Stack:** Laravel 11 (`Illuminate\Support\Facades\Password`, `Illuminate\Auth\Events\PasswordReset`), Filament v3 (`Filament\Pages\Auth\PasswordReset\RequestPasswordReset` override).

**Spec:** `docs/superpowers/specs/2026-08-31-self-service-password-reset-design.md`

**Depends on:** `docs/superpowers/plans/2026-08-31-email-template-admin-editing.md` must be implemented first — this plan's Task 1 adds 3 rows to `EmailTemplateSeeder`, which only exists once that plan lands.

## Global Constraints

- Eligibility is `password !== null`, never `clerk_user_id === null` — a buyer can have both a password and a linked Clerk account (spec: "Eligibility rule").
- Staff have no Clerk path at all — every staff account is unconditionally eligible, no gating needed for that guard.
- A password-reset request must always produce the same user-visible response regardless of whether the account exists, is Clerk-only, or has a password — no response difference may reveal which case occurred (spec: "Sellers", "Buyers").
- Reset-link emails go through the email-templates system (`App\Mail\*` + `EmailTemplate`/`EmailTemplateRenderer`), not Laravel's or Filament's default notification classes.
- New buyer routes get the same `throttle:6,1` already applied to `/register` and `/login`.

---

### Task 1: Add the 3 reset-email templates

**Files:**
- Modify: `database/seeders/EmailTemplateSeeder.php`
- Modify: `app/Filament/Resources/EmailTemplateResource.php`
- Modify: `tests/Feature/EmailTemplateSeederTest.php`

**Interfaces:**
- Produces: 3 more `EmailTemplate` rows, keys `staff_password_reset`, `seller_password_reset`, `buyer_password_reset`, `is_system = true`.

- [ ] **Step 1: Write the failing test**

In `tests/Feature/EmailTemplateSeederTest.php`, update `EXPECTED_KEYS` (append, don't reorder the first 8):

```php
    private const EXPECTED_KEYS = [
        'product_listing_live',
        'quote_request_confirmation',
        'quote_request_received',
        'seller_activation_admin_created',
        'seller_activation_self_registered',
        'seller_approved',
        'seller_rejected',
        'staff_invitation',
        'staff_password_reset',
        'seller_password_reset',
        'buyer_password_reset',
    ];
```

Add one more test method to the same class:

```php
    public function test_the_three_password_reset_templates_contain_the_reset_url_token(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach (['staff_password_reset', 'seller_password_reset', 'buyer_password_reset'] as $key) {
            $this->assertStringContainsString('{{reset_url}}', EmailTemplate::forKey($key)->body);
        }
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailTemplateSeederTest.php`
Expected: FAIL — only 8 keys exist, count mismatch.

- [ ] **Step 3: Add the 3 templates to the seeder**

In `database/seeders/EmailTemplateSeeder.php`, append to the array returned by `templates()` (after the `staff_invitation` entry):

```php
            'staff_password_reset' => [
                'label' => 'Staff Password Reset',
                'subject' => 'Reset your admin panel password',
                'body' => <<<'HTML'
<h1>Reset your password</h1>
<p>Hi {{staff_name}}, click below to set a new password for your admin panel account.</p>
<p><a href="{{reset_url}}">Reset Password</a></p>
<p>If you didn't request this, you can safely ignore this email.</p>
HTML,
            ],
            'seller_password_reset' => [
                'label' => 'Seller Password Reset',
                'subject' => 'Reset your seller account password',
                'body' => <<<'HTML'
<h1>Reset your password</h1>
<p>Hi, click below to set a new password for {{company_name}}'s seller account.</p>
<p><a href="{{reset_url}}">Reset Password</a></p>
<p>If you didn't request this, you can safely ignore this email.</p>
HTML,
            ],
            'buyer_password_reset' => [
                'label' => 'Buyer Password Reset',
                'subject' => 'Reset your password',
                'body' => <<<'HTML'
<h1>Reset your password</h1>
<p>Hi {{name}}, click below to set a new password.</p>
<p><a href="{{reset_url}}">Reset Password</a></p>
<p>If you didn't request this, you can safely ignore this email.</p>
HTML,
            ],
```

- [ ] **Step 4: Add token help and sample data for the 3 new keys**

In `app/Filament/Resources/EmailTemplateResource.php`, add entries to both the `tokenHelpFor()` and `sampleTokensFor()` arrays:

```php
    // In tokenHelpFor()'s $tokens array, add:
    'staff_password_reset' => '{{staff_name}}, {{reset_url}}',
    'seller_password_reset' => '{{company_name}}, {{reset_url}}',
    'buyer_password_reset' => '{{name}}, {{reset_url}}',
```

```php
    // In sampleTokensFor()'s $samples array, add:
    'staff_password_reset' => ['staff_name' => 'Priya', 'reset_url' => url('/admin/password-reset/reset?token=sample')],
    'seller_password_reset' => ['company_name' => 'Acme Co', 'reset_url' => url('/seller/password-reset/reset?token=sample')],
    'buyer_password_reset' => ['name' => 'Asha', 'reset_url' => url('/reset-password/sample')],
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/EmailTemplateSeederTest.php`
Expected: PASS (5 tests)

- [ ] **Step 6: Commit**

```bash
git add database/seeders/EmailTemplateSeeder.php app/Filament/Resources/EmailTemplateResource.php tests/Feature/EmailTemplateSeederTest.php
git commit -m "Add staff/seller/buyer password reset templates to the email template system"
```

---

### Task 2: Staff self-service password reset

**Files:**
- Create: `database/migrations/2026_08_31_120000_create_staff_password_reset_tokens_table.php`
- Modify: `config/auth.php`
- Create: `app/Mail/StaffPasswordReset.php`
- Create: `app/Filament/Auth/RequestStaffPasswordReset.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/StaffPasswordResetTest.php`

**Interfaces:**
- Consumes: `EmailTemplate::forKey('staff_password_reset')`, `EmailTemplateRenderer::render()`, `Illuminate\Support\Facades\Password`, `Illuminate\Auth\Events\PasswordReset`.
- Placed at `app/Filament/Auth/` (not `app/Filament/Pages/Auth/`) deliberately — `AdminPanelProvider` calls `discoverPages(in: app_path('Filament/Pages'), ...)`, which would otherwise auto-register this as a spurious navigable sidebar page instead of the auth-only override it's meant to be.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Mail\StaffPasswordReset;
use App\Models\Staff;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class StaffPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Livewire::test() instantiates the page directly, bypassing the
        // panel routing/middleware that normally sets "current panel" --
        // without this, Filament::getAuthPasswordBroker() (used inside
        // RequestStaffPasswordReset::request()) has no panel to read from.
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_a_staff_member_can_request_and_complete_a_self_service_reset(): void
    {
        Mail::fake();

        $staff = Staff::factory()->create(['must_change_password' => true]);

        Livewire::test(\App\Filament\Auth\RequestStaffPasswordReset::class)
            ->fillForm(['email' => $staff->email])
            ->call('request');

        Mail::assertQueued(StaffPasswordReset::class, fn ($mail) => $mail->hasTo($staff->email));

        $token = Password::broker('staff')->createToken($staff);

        // Filament's ResetPassword page is a Livewire component reached via
        // panel routing, not a plain POST endpoint -- test it the same way
        // as the request page above, not with a raw HTTP request. The page
        // class itself is unmodified (Filament's default), reused here.
        Livewire::test(\Filament\Pages\Auth\PasswordReset\ResetPassword::class, [
            'email' => $staff->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => 'new-password-123',
                'passwordConfirmation' => 'new-password-123',
            ])
            ->call('resetPassword');

        $staff->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-123', $staff->password));
        $this->assertFalse($staff->must_change_password);
    }

    public function test_requesting_a_reset_for_an_unknown_email_gives_the_same_response_as_a_known_one(): void
    {
        Mail::fake();

        Livewire::test(\App\Filament\Auth\RequestStaffPasswordReset::class)
            ->fillForm(['email' => 'nobody@example.com'])
            ->call('request')
            ->assertHasNoFormErrors();

        Mail::assertNothingQueued();
    }

    public function test_admin_triggered_reset_still_sets_must_change_password_true(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $staffMember = Staff::factory()->create(['must_change_password' => false]);

        Livewire::test(\App\Filament\Resources\StaffResource\Pages\EditStaff::class, ['record' => $staffMember->id])
            ->callAction('resetPassword');

        $this->assertTrue($staffMember->fresh()->must_change_password);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/StaffPasswordResetTest.php`
Expected: FAIL — no `staff` password broker, no `RequestStaffPasswordReset` class, no `StaffPasswordReset` Mailable.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_password_reset_tokens');
    }
};
```

- [ ] **Step 4: Register the `staff` broker**

In `config/auth.php`, add to the `'passwords'` array (alongside the existing `users` and `sellers` entries):

```php
        'staff' => [
            'provider' => 'staff',
            'table' => env('AUTH_PASSWORD_RESET_STAFF_TOKEN_TABLE', 'staff_password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
```

- [ ] **Step 5: Write the `StaffPasswordReset` Mailable**

```php
<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Staff;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StaffPasswordReset extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Staff $staff, public string $resetUrl)
    {
    }

    private function tokens(): array
    {
        return [
            'staff_name' => $this->staff->name,
            'reset_url' => $this->resetUrl,
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('staff_password_reset');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('staff_password_reset');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send staff password reset email.', [
            'staff_id' => $this->staff->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 6: Write the custom request page**

```php
<?php

namespace App\Filament\Auth;

use App\Mail\StaffPasswordReset;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class RequestStaffPasswordReset extends RequestPasswordReset
{
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $email = $this->form->getState()['email'];

        Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            ['email' => $email],
            function (CanResetPassword $user, string $token): void {
                Mail::to($user->email)->send(new StaffPasswordReset(
                    $user,
                    Filament::getResetPasswordUrl($token, $user),
                ));
            },
        );

        // Always the same response, whether the account exists or not --
        // withholds the enumeration signal (spec: "Sellers").
        $this->getSentNotification(Password::RESET_LINK_SENT)?->send();
        $this->form->fill();
    }
}
```

- [ ] **Step 7: Wire the panel provider**

In `app/Providers/Filament/AdminPanelProvider.php`, add the import `use App\Filament\Auth\RequestStaffPasswordReset;` and add to the `panel()` chain (after `->authGuard('staff')`):

```php
            ->passwordReset(RequestStaffPasswordReset::class)
            ->authPasswordBroker('staff')
```

- [ ] **Step 8: Register the `must_change_password` listener**

In `app/Providers/AppServiceProvider.php`, add the import `use App\Models\Staff;` and `use Illuminate\Auth\Events\PasswordReset;` and `use Illuminate\Support\Facades\Event;`, and add to `boot()`:

```php
        // A self-service reset means the staff member chose their own new
        // password directly -- unlike StaffResource's admin-triggered reset,
        // which sets a temporary password someone else picked, this
        // shouldn't force yet another change on next login.
        Event::listen(PasswordReset::class, function (PasswordReset $event): void {
            if ($event->user instanceof Staff) {
                $event->user->forceFill(['must_change_password' => false])->save();
            }
        });
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test tests/Feature/StaffPasswordResetTest.php`
Expected: PASS (3 tests)

- [ ] **Step 10: Run the full suite**

Run: `php artisan test`
Expected: All pass — confirms the new listener doesn't affect `StaffResourceTest`'s admin-triggered reset test, and the panel provider change doesn't break existing admin panel tests.

- [ ] **Step 11: Commit**

```bash
git add database/migrations/2026_08_31_120000_create_staff_password_reset_tokens_table.php config/auth.php app/Mail/StaffPasswordReset.php app/Filament/Auth/RequestStaffPasswordReset.php app/Providers/Filament/AdminPanelProvider.php app/Providers/AppServiceProvider.php tests/Feature/StaffPasswordResetTest.php
git commit -m "Add self-service password reset for staff"
```

---

### Task 3: Seller — Clerk-only guard and template-driven reset email

**Files:**
- Create: `app/Mail/SellerPasswordReset.php`
- Create: `app/Filament/Seller/Auth/RequestSellerPasswordReset.php`
- Modify: `app/Providers/Filament/SellerPanelProvider.php`
- Test: `tests/Feature/SellerPasswordResetTest.php`

**Interfaces:**
- Consumes: `EmailTemplate::forKey('seller_password_reset')`, `EmailTemplateRenderer::render()`.
- Placed at `app/Filament/Seller/Auth/` (not `app/Filament/Seller/Pages/Auth/`) for the same auto-discovery reason as Task 2.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Mail\SellerPasswordReset;
use App\Models\Seller;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class SellerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Unlike the admin panel, the seller panel isn't ->default(), so
        // Filament::getCurrentPanel() has nothing to fall back to when
        // Livewire::test() instantiates the page directly without going
        // through panel routing/middleware -- set it explicitly.
        Filament::setCurrentPanel(Filament::getPanel('seller'));
    }

    public function test_a_password_based_seller_can_request_and_complete_a_reset(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['company_name' => 'Acme Co']);

        Livewire::test(\App\Filament\Seller\Auth\RequestSellerPasswordReset::class)
            ->fillForm(['email' => $seller->email])
            ->call('request');

        Mail::assertQueued(SellerPasswordReset::class, fn ($mail) => $mail->hasTo($seller->email));

        $token = Password::broker('sellers')->createToken($seller);

        // Filament's ResetPassword page is a Livewire component reached via
        // panel routing, not a plain POST endpoint -- same pattern as the
        // staff test. The page class itself is unmodified (Filament's
        // default), reused here.
        Livewire::test(\Filament\Pages\Auth\PasswordReset\ResetPassword::class, [
            'email' => $seller->email,
            'token' => $token,
        ])
            ->fillForm([
                'password' => 'new-password-123',
                'passwordConfirmation' => 'new-password-123',
            ])
            ->call('resetPassword');

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-123', $seller->fresh()->password));
    }

    public function test_a_clerk_only_seller_gets_the_same_response_but_no_email_is_sent(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['password' => null, 'clerk_user_id' => 'user_clerk123']);

        Livewire::test(\App\Filament\Seller\Auth\RequestSellerPasswordReset::class)
            ->fillForm(['email' => $seller->email])
            ->call('request')
            ->assertHasNoFormErrors();

        Mail::assertNothingQueued();
    }

    public function test_an_unknown_email_gets_the_same_response_and_no_email_is_sent(): void
    {
        Mail::fake();

        Livewire::test(\App\Filament\Seller\Auth\RequestSellerPasswordReset::class)
            ->fillForm(['email' => 'nobody@example.com'])
            ->call('request')
            ->assertHasNoFormErrors();

        Mail::assertNothingQueued();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/SellerPasswordResetTest.php`
Expected: FAIL — `RequestSellerPasswordReset` doesn't exist yet, and the stock Filament flow has no Clerk-only guard.

- [ ] **Step 3: Write the `SellerPasswordReset` Mailable**

```php
<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Seller;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SellerPasswordReset extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller, public string $resetUrl)
    {
    }

    private function tokens(): array
    {
        return [
            'company_name' => $this->seller->company_name,
            'reset_url' => $this->resetUrl,
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('seller_password_reset');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('seller_password_reset');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send seller password reset email.', [
            'seller_id' => $this->seller->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Write the custom request page with the Clerk-only guard**

```php
<?php

namespace App\Filament\Seller\Auth;

use App\Mail\SellerPasswordReset;
use App\Models\Seller;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class RequestSellerPasswordReset extends RequestPasswordReset
{
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $email = $this->form->getState()['email'];
        $seller = Seller::where('email', $email)->first();

        // Eligibility is "has a local password", not "has no Clerk link" --
        // checked here rather than relying on Password::sendResetLink's own
        // user lookup, since that has no concept of "found but not
        // eligible" (spec: "Eligibility rule").
        if ($seller && $seller->password !== null) {
            Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                ['email' => $email],
                function (CanResetPassword $user, string $token): void {
                    Mail::to($user->email)->send(new SellerPasswordReset(
                        $user,
                        Filament::getResetPasswordUrl($token, $user),
                    ));
                },
            );
        }

        // Always the same response, whether the account doesn't exist, is
        // Clerk-only, or is a real password-based match -- withholds the
        // enumeration signal (spec: "Sellers").
        $this->getSentNotification(Password::RESET_LINK_SENT)?->send();
        $this->form->fill();
    }
}
```

- [ ] **Step 5: Wire the panel provider**

In `app/Providers/Filament/SellerPanelProvider.php`, add the import `use App\Filament\Seller\Auth\RequestSellerPasswordReset;` and change:

```php
            ->passwordReset()
```

to:

```php
            ->passwordReset(RequestSellerPasswordReset::class)
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/SellerPasswordResetTest.php`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Mail/SellerPasswordReset.php app/Filament/Seller/Auth/RequestSellerPasswordReset.php app/Providers/Filament/SellerPanelProvider.php tests/Feature/SellerPasswordResetTest.php
git commit -m "Add Clerk-only guard and template-driven email to seller password reset"
```

---

### Task 4: Buyer self-service password reset

**Files:**
- Create: `app/Mail/BuyerPasswordReset.php`
- Create: `app/Http/Requests/SendPasswordResetLinkRequest.php`
- Create: `app/Http/Requests/ResetPasswordRequest.php`
- Create: `app/Http/Controllers/PasswordResetController.php`
- Create: `resources/views/auth/forgot-password.blade.php`
- Create: `resources/views/auth/reset-password.blade.php`
- Modify: `resources/views/auth/login.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/BuyerPasswordResetTest.php`

**Interfaces:**
- Consumes: `EmailTemplate::forKey('buyer_password_reset')`, `EmailTemplateRenderer::render()`, the `users` broker already in `config/auth.php` (`Illuminate\Support\Facades\Password::broker('users')`, the default when no broker name is passed).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Mail\BuyerPasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class BuyerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_password_based_buyer_can_request_and_complete_a_reset(): void
    {
        Mail::fake();

        $user = User::factory()->create(['name' => 'Asha', 'password' => Hash::make('old-password')]);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Mail::assertQueued(BuyerPasswordReset::class, fn ($mail) => $mail->hasTo($user->email));

        $token = Password::broker('users')->createToken($user);

        $this->get('/reset-password/'.$token.'?email='.urlencode($user->email))
            ->assertOk();

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_a_clerk_only_buyer_gets_a_redirect_but_no_email_is_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => null, 'clerk_user_id' => 'user_clerk123']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Mail::assertNothingQueued();
    }

    public function test_an_unknown_email_gets_the_same_redirect_and_no_email_is_sent(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Mail::assertNothingQueued();
    }

    public function test_the_forgot_password_route_is_rate_limited(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => $user->email]);
        }

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertStatus(429);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/BuyerPasswordResetTest.php`
Expected: FAIL — routes/controller don't exist (404s).

- [ ] **Step 3: Write the `BuyerPasswordReset` Mailable**

```php
<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BuyerPasswordReset extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $resetUrl)
    {
    }

    private function tokens(): array
    {
        return [
            'name' => $this->user->name,
            'reset_url' => $this->resetUrl,
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('buyer_password_reset');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('buyer_password_reset');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send buyer password reset email.', [
            'user_id' => $this->user->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Write the form requests**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendPasswordResetLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email']];
    }
}
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::default()],
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendPasswordResetLinkRequest;
use App\Mail\BuyerPasswordReset;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(SendPasswordResetLinkRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // Eligibility is "has a local password", not "has no Clerk link" --
        // a buyer can have both (spec: "Eligibility rule"). Checked here
        // rather than relying on Password::sendResetLink's own lookup,
        // since that has no concept of "found but not eligible".
        if ($user && $user->password !== null) {
            Password::broker('users')->sendResetLink(
                ['email' => $user->email],
                function (CanResetPassword $resetUser, string $token): void {
                    Mail::to($resetUser->email)->send(new BuyerPasswordReset(
                        $resetUser,
                        url('/reset-password/'.$token.'?email='.urlencode($resetUser->email)),
                    ));
                },
            );
        }

        // Always the same response, whether the account doesn't exist, is
        // Clerk-only, or is a real password-based match -- withholds the
        // enumeration signal (spec: "Buyers").
        return redirect()->route('login')->with('status', 'If that email is registered, we\'ve sent a password reset link.');
    }

    public function showResetForm(string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request('email'),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset.');
    }
}
```

- [ ] **Step 6: Add the routes**

In `routes/web.php`, add the import `use App\Http\Controllers\PasswordResetController;` and, after the existing `/login`/`/logout` routes:

```php
Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
```

- [ ] **Step 7: Write the views**

`resources/views/auth/forgot-password.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Forgot Password')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1>Forgot Password</h1>
            <p class="text-muted">Enter your email and we'll send you a password reset link.</p>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <button type="submit" class="btn btn-primary">Send Reset Link</button>
                <a href="{{ route('login') }}" class="btn btn-link">Back to Log In</a>
            </form>
        </div>
    </div>
@endsection
```

`resources/views/auth/reset-password.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Reset Password')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1>Reset Password</h1>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </form>
        </div>
    </div>
@endsection
```

Both forms follow the seller-registration-form conventions already in this app: `novalidate` + required-field markup that picks up the site-wide neon-red validation styling (`public/css/site.css`, `public/js/form-validation.js`) with no extra work needed here.

- [ ] **Step 8: Add the "Forgot password?" link to the login page**

In `resources/views/auth/login.blade.php`, add a link near the password field (exact placement depends on the current file — add it directly under the password `<div class="mb-3">` block):

```blade
<div class="mb-3">
    <a href="{{ route('password.request') }}" class="small">Forgot your password?</a>
</div>
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test tests/Feature/BuyerPasswordResetTest.php`
Expected: PASS (4 tests)

- [ ] **Step 10: Run the full suite**

Run: `php artisan test`
Expected: All pass — confirms the login page change didn't break `SessionController`/login-page tests.

- [ ] **Step 11: Commit**

```bash
git add app/Mail/BuyerPasswordReset.php app/Http/Requests/SendPasswordResetLinkRequest.php app/Http/Requests/ResetPasswordRequest.php app/Http/Controllers/PasswordResetController.php resources/views/auth/forgot-password.blade.php resources/views/auth/reset-password.blade.php resources/views/auth/login.blade.php routes/web.php tests/Feature/BuyerPasswordResetTest.php
git commit -m "Add self-service password reset for buyers"
```

---

### Task 5: `CLAUDE.md` update and final regression check

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Update the buyer-account paragraph**

In `CLAUDE.md`, find the bullet describing buyer accounts (starts "**Buyers** — public visitors who browse the catalog..."), which currently ends with:

> An account (`web` guard, the stock `users` table) is optional and low-friction — no email verification, no password reset — used only to view past quote requests and favorites.

Replace with:

> An account (`web` guard, the stock `users` table) is optional and low-friction — no email verification — used only to view past quote requests and favorites. Buyers who registered with email/password can self-service reset it at `/forgot-password`; accounts that signed up via Clerk/Google with no local password (`password IS NULL`) are not eligible — same rule applies to sellers.

- [ ] **Step 2: Run the full test suite one final time**

Run: `php artisan test`
Expected: All tests pass — full confirmation that both this plan and the email-template plan it depends on are correctly integrated.

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md
git commit -m "Document self-service password reset eligibility in CLAUDE.md"
```
