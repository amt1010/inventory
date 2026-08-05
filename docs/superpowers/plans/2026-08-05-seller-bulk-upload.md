# Seller Bulk Upload Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let Admin bulk-create sellers from a CSV, with blank cells stored as a placeholder and approval blocked until every field is genuinely filled in — plus two new seller fields and a formatted Seller Code applied to every seller.

**Architecture:** A dedicated `SellerCodeGenerator` service (mirroring the existing `QuoteNumberGenerator` pattern) assigns every new `Seller` a `YYMMDDHHMM` + `S` + 5-digit-sequence code via a model `creating` hook. A new `App\Actions\ApproveSeller` class extracts and extends the existing inline approve logic in `SellerResource` with a readiness gate. Bulk import uses Filament v3's native `Importer`, which is already installed but requires two fixes specific to this app's multi-guard setup (a broken `imports.user_id` foreign key, and that the migrations must be explicitly published, not auto-loaded).

**Tech Stack:** Laravel 11, Filament v3 (`filament/actions` `Importer`/`ImportAction`, already installed), MySQL (dev/prod), SQLite (tests).

## Global Constraints

- Every new behavior gets a failing test first (`php artisan test --filter=...`), per this repo's test-first convention.
- Migrations must be additive; verify with `php artisan migrate` against the real dev database, never `migrate:fresh`, per `CLAUDE.md`.
- Commit after each task, small units, tests passing at each commit.
- The literal placeholder string is `Seller::PLACEHOLDER = 'To be Added'` — use the constant everywhere, never a hardcoded string.
- `manufacturing_activity` and `availability_hours` are optional/nullable on every form (self-registration, Admin manual-create) — only the bulk-upload placeholder rule and the approval gate treat them as significant.
- Run `php artisan test` (the full suite) before each commit to confirm no regressions, in addition to the task's own new tests.

---

### Task 1: `SellerCodeGenerator` service and its sequence table

**Files:**
- Create: `database/migrations/2026_08_05_100100_create_seller_code_sequences_table.php`
- Create: `app/Services/SellerCodeGenerator.php`
- Test: `tests/Unit/SellerCodeGeneratorTest.php`

**Interfaces:**
- Produces: `App\Services\SellerCodeGenerator::generate(): string`, returning a 16-character code: 10-digit `ymdHi` timestamp + literal `S` + 5-digit zero-padded sequence (e.g. `2608051423S00042`). The sequence resets to `1` for each new minute and increments atomically for repeated calls within the same minute (mirrors `App\Services\QuoteNumberGenerator` at `app/Services/QuoteNumberGenerator.php`, which does the same thing with a 4-digit sequence and no literal letter).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Services\SellerCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SellerCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_formats_the_code_as_yymmddhhmm_plus_s_plus_a_five_digit_sequence(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 5, 14, 23, 0));

        $code = (new SellerCodeGenerator())->generate();

        $this->assertSame('2608051423', substr($code, 0, 10));
        $this->assertSame('S', substr($code, 10, 1));
        $this->assertSame('00001', substr($code, 11, 5));
        $this->assertMatchesRegularExpression('/^\d{10}S\d{5}$/', $code);

        Carbon::setTestNow();
    }

    public function test_sequence_increments_for_repeated_calls_within_the_same_minute(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 5, 14, 23, 0));

        $generator = new SellerCodeGenerator();
        $first = $generator->generate();
        $second = $generator->generate();
        $third = $generator->generate();

        $this->assertSame('00001', substr($first, 11, 5));
        $this->assertSame('00002', substr($second, 11, 5));
        $this->assertSame('00003', substr($third, 11, 5));

        Carbon::setTestNow();
    }

    public function test_sequence_resets_in_a_new_minute(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 5, 14, 23, 59));
        $generator = new SellerCodeGenerator();
        $lastOfMinute = $generator->generate();

        Carbon::setTestNow(Carbon::create(2026, 8, 5, 14, 24, 0));
        $firstOfNextMinute = $generator->generate();

        $this->assertSame('00001', substr($lastOfMinute, 11, 5));
        $this->assertSame('00001', substr($firstOfNextMinute, 11, 5));
        $this->assertNotSame(substr($lastOfMinute, 0, 10), substr($firstOfNextMinute, 0, 10));

        Carbon::setTestNow();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SellerCodeGeneratorTest`
Expected: FAIL — `Class "App\Services\SellerCodeGenerator" not found`.

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
        Schema::create('seller_code_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('minute_key', 10)->unique();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_code_sequences');
    }
};
```

- [ ] **Step 4: Write the service**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SellerCodeGenerator
{
    public function generate(): string
    {
        $now = now();
        $minuteKey = $now->format('ymdHi');

        $sequence = DB::transaction(function () use ($minuteKey) {
            $row = DB::table('seller_code_sequences')
                ->where('minute_key', $minuteKey)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $next = $row->sequence + 1;
                DB::table('seller_code_sequences')
                    ->where('minute_key', $minuteKey)
                    ->update(['sequence' => $next, 'updated_at' => now()]);

                return $next;
            }

            DB::table('seller_code_sequences')->insert([
                'minute_key' => $minuteKey,
                'sequence' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        });

        return $minuteKey.'S'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SellerCodeGeneratorTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Apply the migration to the dev database and commit**

```bash
php artisan migrate
git add database/migrations/2026_08_05_100100_create_seller_code_sequences_table.php app/Services/SellerCodeGenerator.php tests/Unit/SellerCodeGeneratorTest.php
git commit -m "feat: add SellerCodeGenerator service for formatted seller codes"
```

---

### Task 2: `sellers` table changes and model support

**Files:**
- Create: `database/migrations/2026_08_05_100200_add_bulk_upload_fields_to_sellers_table.php`
- Modify: `app/Models/Seller.php`
- Test: `tests/Feature/SellerCodeAssignmentTest.php`

**Interfaces:**
- Consumes: `App\Services\SellerCodeGenerator::generate(): string` (Task 1).
- Produces: `Seller::PLACEHOLDER` (string constant `'To be Added'`), used by Task 3 (`ApproveSeller`) and Task 6 (`SellerImporter`). Every `Seller` created from this point on (any path, including `Seller::factory()`) automatically has `seller_code` set — later tasks must NOT set `seller_code` themselves. New nullable columns `manufacturing_activity`, `availability_hours`, `password_set_at` are added to `$fillable`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerCodeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_seller_is_assigned_a_seller_code_automatically(): void
    {
        $seller = Seller::factory()->create();

        $this->assertNotNull($seller->seller_code);
        $this->assertMatchesRegularExpression('/^\d{10}S\d{5}$/', $seller->seller_code);
    }

    public function test_two_sellers_created_in_the_same_minute_get_different_seller_codes(): void
    {
        $first = Seller::factory()->create();
        $second = Seller::factory()->create();

        $this->assertNotSame($first->seller_code, $second->seller_code);
    }

    public function test_email_no_longer_needs_to_be_unique_at_the_database_level(): void
    {
        Seller::factory()->create(['email' => 'shared@example.com']);
        $second = Seller::factory()->create(['email' => 'shared@example.com']);

        $this->assertSame('shared@example.com', $second->fresh()->email);
    }

    public function test_the_placeholder_constant_is_the_literal_string_to_be_added(): void
    {
        $this->assertSame('To be Added', Seller::PLACEHOLDER);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SellerCodeAssignmentTest`
Expected: FAIL — `seller_code` column does not exist / `Seller::PLACEHOLDER` undefined.

- [ ] **Step 3: Write the migration**

```php
<?php

use App\Services\SellerCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('seller_code', 16)->nullable()->after('id');
            $table->string('manufacturing_activity')->nullable()->after('business_address');
            $table->string('availability_hours')->nullable()->after('manufacturing_activity');
            $table->timestamp('password_set_at')->nullable()->after('approved_by');
            $table->dropUnique(['email']);
        });

        $generator = new SellerCodeGenerator();

        foreach (DB::table('sellers')->orderBy('id')->get(['id']) as $seller) {
            DB::table('sellers')->where('id', $seller->id)->update([
                'seller_code' => $generator->generate(),
            ]);
        }

        Schema::table('sellers', function (Blueprint $table) {
            $table->unique('seller_code');
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropUnique(['seller_code']);
            $table->dropColumn(['seller_code', 'manufacturing_activity', 'availability_hours', 'password_set_at']);
            $table->unique('email');
        });
    }
};
```

- [ ] **Step 4: Update the Seller model**

In `app/Models/Seller.php`, add the import, the constant, extend `$fillable` and `$casts`, and register the `creating` hook:

```php
use App\Services\SellerCodeGenerator;
```

```php
class Seller extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable;

    public const PLACEHOLDER = 'To be Added';

    protected $fillable = [
        'company_name', 'contact_person', 'phone', 'email', 'password',
        'business_address', 'gst_number', 'status', 'created_by',
        'rejection_reason', 'email_verified_at', 'approved_at', 'approved_by',
        'seller_code', 'manufacturing_activity', 'availability_hours', 'password_set_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'password_set_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Seller $seller) {
            $seller->seller_code ??= app(SellerCodeGenerator::class)->generate();
        });
    }

    // ... existing relations and methods unchanged
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=SellerCodeAssignmentTest`
Expected: PASS (4 tests)

- [ ] **Step 6: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS — no other test asserts on `sellers.email` uniqueness at the DB level or on the exact `$fillable`/`$casts` arrays.

- [ ] **Step 7: Apply the migration to the dev database and commit**

```bash
php artisan migrate
git add database/migrations/2026_08_05_100200_add_bulk_upload_fields_to_sellers_table.php app/Models/Seller.php tests/Feature/SellerCodeAssignmentTest.php
git commit -m "feat: add seller_code, manufacturing_activity, availability_hours to sellers"
```

---

### Task 3: `ApproveSeller` action with the readiness gate

**Files:**
- Create: `app/Actions/ApproveSeller.php`
- Modify: `app/Mail/SellerApproved.php`
- Modify: `resources/views/emails/seller-approved.blade.php`
- Modify: `app/Filament/Resources/SellerResource.php:92-110` (the `approve` table action)
- Test: `tests/Feature/ApproveSellerTest.php`

**Interfaces:**
- Consumes: `Seller::PLACEHOLDER` (Task 2).
- Produces: `App\Actions\ApproveSeller::approve(Seller $seller, Staff $staff): array` — returns an empty array on success (seller is now `approved`, email queued), or a non-empty array of human-readable blocking-reason strings if approval was refused (seller unchanged). Also produces `App\Actions\ApproveSeller::blockingReasons(Seller $seller): array`, used standalone by Task 6's importer-adjacent tests if needed. `SellerApproved`'s constructor becomes `__construct(public Seller $seller, public ?string $activationUrl = null)` — Task 7 relies on this second parameter.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Actions\ApproveSeller;
use App\Mail\SellerApproved;
use App\Models\Seller;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApproveSellerTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_is_blocked_while_a_required_field_still_holds_the_placeholder(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'gst_number' => Seller::PLACEHOLDER,
        ]);

        $reasons = (new ApproveSeller())->approve($seller, Staff::factory()->create());

        $this->assertNotEmpty($reasons);
        $this->assertSame('pending_admin_approval', $seller->fresh()->status);
    }

    public function test_approval_is_blocked_when_email_holds_more_than_one_address(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'email' => 'a@example.com, b@example.com',
        ]);

        $reasons = (new ApproveSeller())->approve($seller, Staff::factory()->create());

        $this->assertNotEmpty($reasons);
        $this->assertSame('pending_admin_approval', $seller->fresh()->status);
    }

    public function test_approval_is_blocked_when_gst_number_duplicates_another_seller(): void
    {
        Seller::factory()->create(['gst_number' => '27AAAAA0000A1Z5']);
        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'gst_number' => '27AAAAA0000A1Z5',
        ]);

        $reasons = (new ApproveSeller())->approve($seller, Staff::factory()->create());

        $this->assertNotEmpty($reasons);
        $this->assertSame('pending_admin_approval', $seller->fresh()->status);
    }

    public function test_approval_is_blocked_when_email_duplicates_another_seller(): void
    {
        Seller::factory()->create(['email' => 'dup@example.com']);
        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'email' => 'dup@example.com',
        ]);

        $reasons = (new ApproveSeller())->approve($seller, Staff::factory()->create());

        $this->assertNotEmpty($reasons);
    }

    public function test_approval_succeeds_and_sends_email_with_no_activation_link_when_every_field_is_complete(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create(['status' => 'pending_admin_approval']);
        $admin = Staff::factory()->create();

        $reasons = (new ApproveSeller())->approve($seller, $admin);

        $this->assertSame([], $reasons);
        $seller->refresh();
        $this->assertSame('approved', $seller->status);
        $this->assertSame($admin->id, $seller->approved_by);
        $this->assertNotNull($seller->approved_at);
        Mail::assertQueued(SellerApproved::class, fn ($mail) => $mail->seller->is($seller) && $mail->activationUrl === null);
    }

    public function test_approval_of_a_bulk_uploaded_seller_with_no_password_set_includes_an_activation_link(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'created_by' => 'admin_bulk_upload',
            'password_set_at' => null,
        ]);

        (new ApproveSeller())->approve($seller, Staff::factory()->create());

        Mail::assertQueued(SellerApproved::class, fn ($mail) => $mail->activationUrl !== null);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ApproveSellerTest`
Expected: FAIL — `Class "App\Actions\ApproveSeller" not found`.

- [ ] **Step 3: Update `SellerApproved` to accept an optional activation URL**

Replace the full contents of `app/Mail/SellerApproved.php`:

```php
<?php

namespace App\Mail;

use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SellerApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller, public ?string $activationUrl = null)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your seller account has been approved');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.seller-approved', with: [
            'seller' => $this->seller,
            'activationUrl' => $this->activationUrl,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send seller approval email.', [
            'seller_id' => $this->seller->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Update the approved email view**

Replace the full contents of `resources/views/emails/seller-approved.blade.php`:

```blade
<h1>You're approved!</h1>
<p>Congratulations — {{ $seller->company_name }}'s seller account has been approved. You can now log in and start listing products.</p>

@if ($activationUrl)
    <p>Before you can log in, set your password: <a href="{{ $activationUrl }}">Set Your Password</a></p>
@endif
```

- [ ] **Step 5: Write `ApproveSeller`**

```php
<?php

namespace App\Actions;

use App\Mail\SellerApproved;
use App\Models\Seller;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ApproveSeller
{
    /**
     * @return array<int, string>
     */
    public function blockingReasons(Seller $seller): array
    {
        $reasons = [];

        $requiredFields = [
            'company_name' => 'Company Name',
            'contact_person' => 'Contact Person',
            'phone' => 'Phone',
            'business_address' => 'Business Address',
            'gst_number' => 'GST Number',
            'manufacturing_activity' => 'Manufacturing Activity',
            'availability_hours' => 'Availability Hours',
        ];

        foreach ($requiredFields as $field => $label) {
            if ($seller->{$field} === Seller::PLACEHOLDER) {
                $reasons[] = "{$label} still needs to be filled in.";
            }
        }

        if ($seller->email === Seller::PLACEHOLDER) {
            $reasons[] = 'Email still needs to be filled in.';
        } elseif (str_contains($seller->email, ',')) {
            $reasons[] = 'Email must be a single address, not a comma-separated list.';
        } elseif (! filter_var($seller->email, FILTER_VALIDATE_EMAIL)) {
            $reasons[] = 'Email is not a valid address.';
        } elseif (Seller::query()->where('email', $seller->email)->where('id', '!=', $seller->id)->exists()) {
            $reasons[] = 'Email is already used by another seller.';
        }

        if (
            filled($seller->gst_number)
            && $seller->gst_number !== Seller::PLACEHOLDER
            && Seller::query()->where('gst_number', $seller->gst_number)->where('id', '!=', $seller->id)->exists()
        ) {
            $reasons[] = 'GST Number is already used by another seller.';
        }

        return $reasons;
    }

    /**
     * @return array<int, string>
     */
    public function approve(Seller $seller, Staff $staff): array
    {
        $reasons = $this->blockingReasons($seller);

        if ($reasons !== []) {
            return $reasons;
        }

        $needsActivationLink = $seller->created_by === 'admin_bulk_upload' && is_null($seller->password_set_at);

        $seller->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $staff->id,
        ]);

        try {
            Mail::to($seller->email)->send(new SellerApproved(
                $seller,
                $needsActivationLink
                    ? URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id])
                    : null,
            ));
        } catch (\Throwable $exception) {
            Log::error('Failed to queue seller approval email.', [
                'seller_id' => $seller->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return [];
    }
}
```

- [ ] **Step 6: Wire it into `SellerResource`'s approve action**

In `app/Filament/Resources/SellerResource.php`, add imports:

```php
use App\Actions\ApproveSeller;
use Filament\Notifications\Notification;
```

Replace the `approve` action (currently lines 92-110) with:

```php
Action::make('approve')
    ->visible(fn (Seller $record) => $record->status === 'pending_admin_approval')
    ->requiresConfirmation()
    ->action(function (Seller $record) {
        $reasons = app(ApproveSeller::class)->approve($record, auth('staff')->user());

        if ($reasons !== []) {
            Notification::make()
                ->title('Cannot approve this seller')
                ->body(implode(' ', $reasons))
                ->danger()
                ->send();
        }
    }),
```

The `Mail` and `Log` facade imports at the top of `SellerResource.php` are now only used by the `reject` action — leave them, since `reject` still uses them directly.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=ApproveSellerTest`
Expected: PASS (6 tests)

- [ ] **Step 8: Run the full suite to check for regressions**

Run: `php artisan test --filter=SellerResourceTest`
Expected: PASS — `test_approving_a_pending_seller_sets_status_and_sends_email` still passes because a plain `Seller::factory()->create(['status' => 'pending_admin_approval'])` has no placeholder fields and a unique email/GST by default.

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Actions/ApproveSeller.php app/Mail/SellerApproved.php resources/views/emails/seller-approved.blade.php app/Filament/Resources/SellerResource.php tests/Feature/ApproveSellerTest.php
git commit -m "feat: gate seller approval on complete, non-duplicate data"
```

---

### Task 4: Self-registration form gains the two new fields

**Files:**
- Modify: `app/Http/Requests/StoreSellerRegistrationRequest.php`
- Modify: `app/Http/Controllers/Seller/RegistrationController.php`
- Modify: `resources/views/seller/register.blade.php`
- Test: `tests/Feature/SellerRegistrationTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by later tasks (self-registration remains an independent path).

- [ ] **Step 1: Write the failing tests**

Add these two methods to `tests/Feature/SellerRegistrationTest.php` (inside the existing `SellerRegistrationTest` class, alongside the existing test methods):

```php
    public function test_registration_persists_manufacturing_activity_and_availability_hours_when_provided(): void
    {
        $response = $this->post(route('seller.register.store'), $this->validPayload([
            'manufacturing_activity' => 'Steel fabrication',
            'availability_hours' => 'Mon-Sat 9am-6pm',
        ]));

        $response->assertRedirect(route('seller.registration.submitted'));
        $this->assertDatabaseHas('sellers', [
            'email' => 'asha@raotraders.example',
            'manufacturing_activity' => 'Steel fabrication',
            'availability_hours' => 'Mon-Sat 9am-6pm',
        ]);
    }

    public function test_registration_succeeds_without_manufacturing_activity_or_availability_hours(): void
    {
        $response = $this->post(route('seller.register.store'), $this->validPayload());

        $response->assertRedirect(route('seller.registration.submitted'));
        $this->assertDatabaseHas('sellers', [
            'email' => 'asha@raotraders.example',
            'manufacturing_activity' => null,
            'availability_hours' => null,
        ]);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SellerRegistrationTest`
Expected: FAIL on the two new tests — `manufacturing_activity`/`availability_hours` are silently dropped since they're not validated/persisted yet (the `assertDatabaseHas` for the first new test fails).

- [ ] **Step 3: Add validation rules**

In `app/Http/Requests/StoreSellerRegistrationRequest.php`, add two lines to the `rules()` array, after the `gst_number` rule:

```php
            'manufacturing_activity' => ['nullable', 'string', 'max:255'],
            'availability_hours' => ['nullable', 'string', 'max:255'],
```

- [ ] **Step 4: Persist the new fields**

In `app/Http/Controllers/Seller/RegistrationController.php`, add two lines to the `Seller::create([...])` array in `store()`, after `'gst_number'`:

```php
            'manufacturing_activity' => $request->validated('manufacturing_activity'),
            'availability_hours' => $request->validated('availability_hours'),
```

- [ ] **Step 5: Add the form fields**

In `resources/views/seller/register.blade.php`, insert this block after the GST Number `<div class="mb-3">` block and before the Password `<div class="row">`:

```blade
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">Manufacturing Activity</label>
                <input type="text" name="manufacturing_activity" class="form-control" value="{{ old('manufacturing_activity') }}">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Availability Hours</label>
                <input type="text" name="availability_hours" class="form-control" value="{{ old('availability_hours') }}" placeholder="e.g. Mon-Sat 9am-6pm">
            </div>
        </div>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=SellerRegistrationTest`
Expected: PASS (all tests in the file)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/StoreSellerRegistrationRequest.php app/Http/Controllers/Seller/RegistrationController.php resources/views/seller/register.blade.php tests/Feature/SellerRegistrationTest.php
git commit -m "feat: add manufacturing activity and availability hours to seller registration"
```

---

### Task 5: Admin manual-create/edit form gains the two new fields

**Files:**
- Modify: `app/Filament/Resources/SellerResource.php` (the `form()` method)
- Modify: `tests/Feature/SellerAdminCreationTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

Add this method to `tests/Feature/SellerAdminCreationTest.php` (inside the existing class):

```php
    public function test_admin_creating_a_seller_can_include_manufacturing_activity_and_availability_hours(): void
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
                'email' => 'vikram2@vikramsupplies.example',
                'manufacturing_activity' => 'Textile weaving',
                'availability_hours' => 'Mon-Fri 10am-5pm',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $seller = Seller::where('email', 'vikram2@vikramsupplies.example')->firstOrFail();
        $this->assertSame('Textile weaving', $seller->manufacturing_activity);
        $this->assertSame('Mon-Fri 10am-5pm', $seller->availability_hours);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_admin_creating_a_seller_can_include_manufacturing_activity_and_availability_hours`
Expected: FAIL — the form has no such fields, so `fillForm` silently sets nothing and the assertions on the persisted seller fail.

- [ ] **Step 3: Add the form fields**

In `app/Filament/Resources/SellerResource.php`, in `form()`, add two lines after `TextInput::make('gst_number')->label('GST Number'),` and before the `Select::make('status')` block:

```php
            TextInput::make('manufacturing_activity')->label('Manufacturing Activity'),
            TextInput::make('availability_hours')->label('Availability Hours'),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SellerAdminCreationTest`
Expected: PASS (both tests in the file)

- [ ] **Step 5: Run the full suite to check for regressions**

Run: `php artisan test --filter=SellerResourceTest`
Expected: PASS — the two new optional fields don't affect any existing form-field assertions.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/SellerResource.php tests/Feature/SellerAdminCreationTest.php
git commit -m "feat: add manufacturing activity and availability hours to admin seller form"
```

---

### Task 6: `SellerImporter` and the "Import Sellers" action

This is the largest task. It includes two fixes required specifically because this app authenticates Admin on the `staff` guard, not Laravel's default `web` guard, which Filament's shipped `Importer` infrastructure assumes:

1. Filament's `imports` table migration is **not** auto-loaded (`filament/actions`'s service provider never calls `->runsMigrations(true)`) — it must be explicitly published via `vendor:publish`, which also gives it a proper dated filename so it sorts correctly among this app's own migrations.
2. Filament's `Import::user()` relationship (`vendor/filament/actions/src/Imports/Models/Import.php:47-67`) is a hard `belongsTo` FK to `App\Models\User` (the `web`-guard model), and the code that populates it (`vendor/filament/actions/src/Concerns/CanImportRecords.php:214`, `$user = auth()->user();`) always reads the **default** guard, never the currently active panel's guard. Since Admin authenticates on the `staff` guard, `auth()->user()` returns `null` when an Admin runs the import, which would violate the `imports.user_id` NOT NULL/foreign-key constraint and crash. Filament ships a documented escape hatch for exactly this — `Import::polymorphicUserRelationship()` — which this task enables. One residual limitation, not fixable without modifying vendor code: `imports.user_id`/`user_type` will stay `null` for Admin-triggered imports, since the package's own guard lookup can't see the `staff` guard. This does not affect Seller records being created correctly — it only means the internal `imports` housekeeping row won't record which Admin ran it.

**Files:**
- Create: `app/Filament/Imports/SellerImporter.php`
- Modify: `app/Filament/Resources/SellerResource/Pages/ListSellers.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Create (via `php artisan vendor:publish`): `database/migrations/{timestamp}_create_imports_table.php`, `database/migrations/{timestamp}_create_exports_table.php`, `database/migrations/{timestamp}_create_failed_import_rows_table.php`
- Create: `database/migrations/{timestamp}_make_imports_user_relationship_polymorphic.php`
- Test: `tests/Feature/SellerImporterTest.php`

**Interfaces:**
- Consumes: `Seller::PLACEHOLDER` (Task 2), `Seller::booted()`'s automatic `seller_code` assignment (Task 2 — the importer must NOT set `seller_code` itself).
- Produces: nothing consumed by later tasks. Every row it creates has `status = 'pending_admin_approval'`, `created_by = 'admin_bulk_upload'`, a random unusable password, and `password_set_at = null` — Task 7 relies on exactly these three values to decide when to allow the post-approval activation link.

- [ ] **Step 1: Publish Filament's import migrations**

```bash
php artisan vendor:publish --tag=filament-actions-migrations
```

Expected: three new files appear under `database/migrations/`, timestamped with today's date/time, named `..._create_imports_table.php`, `..._create_exports_table.php`, `..._create_failed_import_rows_table.php`.

- [ ] **Step 2: Create the follow-up migration to make `imports.user` polymorphic**

```bash
php artisan make:migration make_imports_user_relationship_polymorphic --table=imports
```

Replace its generated contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('user_type')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('user_type');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
```

This migration's filename is generated with the current timestamp by `make:migration`, so it necessarily sorts after the files published in Step 1 as long as Step 1 ran first (both use real dated filenames, unlike Filament's un-dated vendor originals).

- [ ] **Step 3: Enable the polymorphic user relationship**

In `app/Providers/Filament/AdminPanelProvider.php`, add the import and a `boot()` method:

```php
use Filament\Actions\Imports\Models\Import;
```

```php
class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        Import::polymorphicUserRelationship();
    }

    public function panel(Panel $panel): Panel
    {
        // ... unchanged
    }
}
```

- [ ] **Step 4: Apply the migrations to the dev database**

```bash
php artisan migrate
```

Expected: `imports`, `exports`, `failed_import_rows` tables created; `imports.user_id` is nullable with no foreign key, `imports.user_type` exists.

- [ ] **Step 5: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Imports\SellerImporter;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImport(): Import
    {
        Import::polymorphicUserRelationship();

        return Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 1,
        ]);
    }

    private function columnMap(): array
    {
        return [
            'company_name' => 'company_name',
            'manufacturing_activity' => 'manufacturing_activity',
            'business_address' => 'business_address',
            'phone' => 'phone',
            'email' => 'email',
            'availability_hours' => 'availability_hours',
            'contact_person' => 'contact_person',
            'gst_number' => 'gst_number',
        ];
    }

    public function test_a_fully_populated_row_creates_a_seller_pending_review(): void
    {
        $importer = new SellerImporter($this->makeImport(), $this->columnMap(), []);

        $importer([
            'company_name' => 'Rao Traders',
            'manufacturing_activity' => 'Cable manufacturing',
            'business_address' => '123 Industrial Estate, Mumbai',
            'phone' => '9876543210',
            'email' => 'bulk1@raotraders.example',
            'availability_hours' => 'Mon-Sat 9am-6pm',
            'contact_person' => 'Asha Rao',
            'gst_number' => '27AAAAA0000A1Z5',
        ]);

        $seller = Seller::where('email', 'bulk1@raotraders.example')->firstOrFail();
        $this->assertSame('Rao Traders', $seller->company_name);
        $this->assertSame('pending_admin_approval', $seller->status);
        $this->assertSame('admin_bulk_upload', $seller->created_by);
        $this->assertNull($seller->password_set_at);
        $this->assertNotNull($seller->seller_code);
    }

    public function test_blank_cells_are_stored_as_the_placeholder(): void
    {
        $importer = new SellerImporter($this->makeImport(), $this->columnMap(), []);

        $importer([
            'company_name' => 'Partial Co',
            'manufacturing_activity' => '',
            'business_address' => '',
            'phone' => '9999999999',
            'email' => 'bulk2@example.com',
            'availability_hours' => '',
            'contact_person' => 'Jane Doe',
            'gst_number' => '',
        ]);

        $seller = Seller::where('email', 'bulk2@example.com')->firstOrFail();
        $this->assertSame(Seller::PLACEHOLDER, $seller->manufacturing_activity);
        $this->assertSame(Seller::PLACEHOLDER, $seller->business_address);
        $this->assertSame(Seller::PLACEHOLDER, $seller->availability_hours);
        $this->assertSame(Seller::PLACEHOLDER, $seller->gst_number);
    }

    public function test_a_blank_email_cell_is_also_stored_as_the_placeholder(): void
    {
        $importer = new SellerImporter($this->makeImport(), $this->columnMap(), []);

        $importer([
            'company_name' => 'No Email Co',
            'manufacturing_activity' => 'Weaving',
            'business_address' => '1 Market Road',
            'phone' => '9999999998',
            'email' => '',
            'availability_hours' => 'Mon-Fri 9-5',
            'contact_person' => 'John Doe',
            'gst_number' => '27BBBBB1111B1Z6',
        ]);

        $seller = Seller::where('company_name', 'No Email Co')->firstOrFail();
        $this->assertSame(Seller::PLACEHOLDER, $seller->email);
    }

    public function test_a_comma_separated_email_is_stored_verbatim(): void
    {
        $importer = new SellerImporter($this->makeImport(), $this->columnMap(), []);

        $importer([
            'company_name' => 'Multi Email Co',
            'manufacturing_activity' => 'Weaving',
            'business_address' => '1 Market Road',
            'phone' => '9999999997',
            'email' => 'a@example.com, b@example.com',
            'availability_hours' => 'Mon-Fri 9-5',
            'contact_person' => 'John Doe',
            'gst_number' => '27CCCCC2222C1Z7',
        ]);

        $seller = Seller::where('company_name', 'Multi Email Co')->firstOrFail();
        $this->assertSame('a@example.com, b@example.com', $seller->email);
    }

    public function test_the_import_action_is_available_on_the_admin_sellers_list(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $response = $this->get('/admin/sellers');

        $response->assertOk();
        $response->assertSee('Import Sellers');
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `php artisan test --filter=SellerImporterTest`
Expected: FAIL — `Class "App\Filament\Imports\SellerImporter" not found`.

- [ ] **Step 7: Write `SellerImporter`**

```php
<?php

namespace App\Filament\Imports;

use App\Models\Seller;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SellerImporter extends Importer
{
    protected static ?string $model = Seller::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('company_name')
                ->label('Company Name')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->company_name = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('manufacturing_activity')
                ->label('Manufacturing Activity')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->manufacturing_activity = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('business_address')
                ->label('Address')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->business_address = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('phone')
                ->label('Phone')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->phone = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('email')
                ->label('Email')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->email = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('availability_hours')
                ->label('Availability Hours')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->availability_hours = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('contact_person')
                ->label('Contact Person')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->contact_person = filled($state) ? $state : Seller::PLACEHOLDER),
            ImportColumn::make('gst_number')
                ->label('GST Number')
                ->requiredMapping()
                ->fillRecordUsing(fn (Seller $record, ?string $state) => $record->gst_number = filled($state) ? $state : Seller::PLACEHOLDER),
        ];
    }

    public function resolveRecord(): ?Seller
    {
        return new Seller();
    }

    protected function beforeCreate(): void
    {
        $this->record->status = 'pending_admin_approval';
        $this->record->created_by = 'admin_bulk_upload';
        $this->record->password = Hash::make(Str::random(40));
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your seller import has completed and '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        $failedRowsCount = $import->getFailedRowsCount();

        if ($failedRowsCount > 0) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
```

- [ ] **Step 8: Wire the "Import Sellers" action into the list page**

Replace the full contents of `app/Filament/Resources/SellerResource/Pages/ListSellers.php`:

```php
<?php

namespace App\Filament\Resources\SellerResource\Pages;

use App\Filament\Imports\SellerImporter;
use App\Filament\Resources\SellerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSellers extends ListRecords
{
    protected static string $resource = SellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ImportAction::make()
                ->importer(SellerImporter::class)
                ->label('Import Sellers'),
            Actions\CreateAction::make(),
        ];
    }
}
```

- [ ] **Step 9: Run test to verify it passes**

Run: `php artisan test --filter=SellerImporterTest`
Expected: PASS (5 tests)

- [ ] **Step 10: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 11: Commit**

```bash
git add app/Filament/Imports/SellerImporter.php app/Filament/Resources/SellerResource/Pages/ListSellers.php app/Providers/Filament/AdminPanelProvider.php database/migrations tests/Feature/SellerImporterTest.php
git commit -m "feat: bulk-upload sellers from CSV via Filament import"
```

---

### Task 7: Post-approval activation for bulk-uploaded sellers

**Files:**
- Modify: `app/Http/Controllers/Seller/ActivationController.php`
- Test: `tests/Feature/SellerBulkUploadActivationTest.php`

**Interfaces:**
- Consumes: `Seller.password_set_at` (Task 2), `ApproveSeller` sending an `activationUrl` (Task 3), `SellerImporter` setting `created_by = 'admin_bulk_upload'` with `password_set_at = null` (Task 6).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Actions\ApproveSeller;
use App\Mail\SellerApproved;
use App\Models\Seller;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SellerBulkUploadActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_bulk_uploaded_seller_sends_an_activation_link_and_the_seller_can_set_a_password(): void
    {
        Mail::fake();

        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'created_by' => 'admin_bulk_upload',
            'password_set_at' => null,
        ]);

        (new ApproveSeller())->approve($seller, Staff::factory()->create());

        Mail::assertQueued(SellerApproved::class, fn ($mail) => $mail->activationUrl !== null);

        $url = URL::temporarySignedRoute('seller.activate.store', now()->addDays(7), ['seller' => $seller->id]);
        $response = $this->post($url, ['password' => 'newpassword123', 'password_confirmation' => 'newpassword123']);

        $response->assertOk();
        $seller->refresh();
        $this->assertTrue(Hash::check('newpassword123', $seller->password));
        $this->assertNotNull($seller->password_set_at);
        $this->assertSame('approved', $seller->status);
    }

    public function test_the_activation_link_cannot_be_reused_after_the_password_is_already_set(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'approved',
            'created_by' => 'admin_bulk_upload',
            'password_set_at' => now(),
        ]);

        $url = URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('seller.activation-invalid');
    }

    public function test_the_activation_link_is_rejected_while_the_seller_is_still_pending_review(): void
    {
        $seller = Seller::factory()->create([
            'status' => 'pending_admin_approval',
            'created_by' => 'admin_bulk_upload',
            'password_set_at' => null,
        ]);

        $url = URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $seller->id]);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertViewIs('seller.activation-invalid');
    }

    public function test_a_self_registered_seller_activation_is_unaffected(): void
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
}
```

- [ ] **Step 2: Run tests to verify the new ones fail**

Run: `php artisan test --filter=SellerBulkUploadActivationTest`
Expected: FAIL on the first three tests — the controller has no `admin_bulk_upload` branch, so an `approved`-status seller always hits the generic `pending_email_verification` guard and gets `activation-invalid` regardless of the link being freshly issued.

- [ ] **Step 3: Update `ActivationController`**

Replace the full contents of `app/Http/Controllers/Seller/ActivationController.php`:

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
        if ($seller->created_by === 'admin_bulk_upload') {
            if ($seller->status !== 'approved' || $seller->password_set_at !== null) {
                return view('seller.activation-invalid');
            }

            return view('seller.set-password', ['seller' => $seller]);
        }

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
        if ($seller->created_by === 'admin_bulk_upload') {
            if ($seller->status !== 'approved' || $seller->password_set_at !== null) {
                return view('seller.activation-invalid');
            }

            $seller->update([
                'password' => Hash::make($request->validated('password')),
                'password_set_at' => now(),
            ]);

            return view('seller.activation-complete', ['seller' => $seller]);
        }

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

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SellerBulkUploadActivationTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Run the full suite to check for regressions**

Run: `php artisan test --filter=SellerActivationTest`
Expected: PASS — the existing `self`/`admin` branches are untouched, only reordered behind a new early-return for `admin_bulk_upload`.

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Seller/ActivationController.php tests/Feature/SellerBulkUploadActivationTest.php
git commit -m "feat: let approved bulk-uploaded sellers set a password and activate"
```

---

### Task 8: Show the Seller Code on the Admin Products list

**Files:**
- Modify: `app/Filament/Resources/ProductResource.php`
- Test: `tests/Feature/AdminProductSellerCodeColumnTest.php`

**Interfaces:**
- Consumes: `Seller::seller_code` (Task 2).
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductSellerCodeColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_products_list_shows_the_owning_sellers_code(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $seller = Seller::factory()->create();
        Product::factory()->create(['seller_id' => $seller->id]);

        Livewire::test(ListProducts::class)
            ->assertSee($seller->seller_code);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AdminProductSellerCodeColumnTest`
Expected: FAIL — the seller code is not rendered anywhere on the page.

- [ ] **Step 3: Add the column**

In `app/Filament/Resources/ProductResource.php`, add one line immediately after `TextColumn::make('seller.company_name')->label('Seller'),`:

```php
                TextColumn::make('seller.seller_code')->label('Seller Code'),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AdminProductSellerCodeColumnTest`
Expected: PASS

- [ ] **Step 5: Run the full suite to check for regressions**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/ProductResource.php tests/Feature/AdminProductSellerCodeColumnTest.php
git commit -m "feat: show seller code on the admin products list"
```
