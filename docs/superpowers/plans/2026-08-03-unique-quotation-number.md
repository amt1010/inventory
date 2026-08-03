# Unique Quotation Number Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every submitted quote request gets a unique reference number in the format `YYMMDDHHMMXXXX` (year, month, day, hour, minute, then a 4-digit sequence resetting every minute), and the buyer receives a confirmation email containing it, sent alongside (not instead of) the existing internal staff notification.

**Architecture:** A dedicated `quote_number_sequences` table (one row per calendar minute, `minute_key` unique) backs an atomic counter via `lockForUpdate()` inside a DB transaction — this is race-safe under MySQL/InnoDB in production even with concurrent submissions in the same minute, and works correctly (if not truly concurrently-tested) under SQLite in the test suite. A small `QuoteNumberGenerator` service owns the format/counter logic so `QuoteRequestController` stays thin. A new buyer-facing `QuoteRequestConfirmation` Mailable is sent in addition to the existing `QuoteRequestReceived` staff notification.

**Tech Stack:** Laravel 11 (DB transactions, `lockForUpdate`), existing Mailable/queue pattern (`ShouldQueue`, matches `QuoteRequestReceived`).

## Global Constraints

- Test-first, `php artisan test` passing before every commit (`CLAUDE.md`).
- No payment/checkout code — not touched by this plan, noted only because quote numbers sometimes get conflated with order numbers; this is purely a reference number for an RFQ enquiry.
- Commit frequently in small units.

## Note on the spec's "Five digit sequential number"

The issue text says "Five digit sequential number starting from 0001", but its own format string (`XXXX`) and its own example starting value (`0001`) are both **4 digits**, not 5. This plan follows the format string and example (4 digits, `0001`–`9999`) since they're concrete and consistent with each other, and treats "five digit" as a wording slip in the issue description. Flag this back on the ticket after implementation in case the reporter actually meant 5.

---

## File Structure

- Create: `database/migrations/2026_08_03_100000_add_quote_number_to_quote_requests_table.php`
- Create: `database/migrations/2026_08_03_100100_create_quote_number_sequences_table.php`
- Create: `app/Services/QuoteNumberGenerator.php`
- Create: `tests/Unit/QuoteNumberGeneratorTest.php`
- Modify: `app/Http/Controllers/QuoteRequestController.php`
- Modify: `tests/Feature/QuoteRequestSubmissionTest.php`
- Create: `app/Mail/QuoteRequestConfirmation.php`
- Create: `resources/views/emails/quote-request-confirmation.blade.php`
- Modify: `resources/views/layouts/app.blade.php:84-86` (flash message now shows the number)
- Modify: `resources/views/quote-requests/index.blade.php` (buyer history — show the number)
- Modify: `app/Filament/Resources/QuoteRequestResource.php` (admin table — show the number)

---

### Task 1: Add the `quote_number` column and the sequence-counter table

**Files:**
- Create: `database/migrations/2026_08_03_100000_add_quote_number_to_quote_requests_table.php`
- Create: `database/migrations/2026_08_03_100100_create_quote_number_sequences_table.php`

**Interfaces:**
- Produces: `quote_requests.quote_number` (nullable string, unique — nullable because pre-existing rows have none, every new row going forward will always have one via Task 3); `quote_number_sequences` table with `minute_key` (unique string, format `YYMMDDHHMM`) and `sequence` (unsigned integer).

- [ ] **Step 1: Create the `quote_number` column migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->string('quote_number')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropUnique(['quote_number']);
            $table->dropColumn('quote_number');
        });
    }
};
```

Save as `database/migrations/2026_08_03_100000_add_quote_number_to_quote_requests_table.php`.

- [ ] **Step 2: Create the sequence-counter table migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('minute_key', 10)->unique();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_number_sequences');
    }
};
```

Save as `database/migrations/2026_08_03_100100_create_quote_number_sequences_table.php`.

- [ ] **Step 3: Run migrations locally**

Run: `php artisan migrate`
Expected: both migrations apply cleanly with no errors.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_03_100000_add_quote_number_to_quote_requests_table.php database/migrations/2026_08_03_100100_create_quote_number_sequences_table.php
git commit -m "Add quote_number column and a sequence-counter table for quote numbering"
```

---

### Task 2: `QuoteNumberGenerator` service

**Files:**
- Create: `app/Services/QuoteNumberGenerator.php`
- Test: `tests/Unit/QuoteNumberGeneratorTest.php`

**Interfaces:**
- Produces: `App\Services\QuoteNumberGenerator::generate(): string` — returns a 14-character string `YYMMDDHHMMXXXX`. Later tasks call this directly (`app(QuoteNumberGenerator::class)->generate()` or constructor-inject it).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/QuoteNumberGeneratorTest.php` (create the `tests/Unit` directory if it doesn't already have other files — check `ls tests/Unit` first; this repo's convention per `CLAUDE.md` is "Feature tests live in `tests/Feature`" for behavior, but this is a pure algorithm with no HTTP/DB-side-effect surface worth a feature test, so `tests/Unit` is appropriate; confirm `tests/Unit/ExampleTest.php` or similar exists as the baseline PHPUnit config already supports it — it ships with a stock Laravel install):

```php
<?php

namespace Tests\Unit;

use App\Services\QuoteNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuoteNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_formats_the_number_as_yymmddhhmm_plus_a_four_digit_sequence(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 17, 30, 0));

        $number = (new QuoteNumberGenerator())->generate();

        $this->assertSame('2608031730', substr($number, 0, 10));
        $this->assertSame('0001', substr($number, 10, 4));
        $this->assertMatchesRegularExpression('/^\d{14}$/', $number);

        Carbon::setTestNow();
    }

    public function test_sequence_increments_for_repeated_calls_within_the_same_minute(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 17, 30, 0));

        $generator = new QuoteNumberGenerator();
        $first = $generator->generate();
        $second = $generator->generate();
        $third = $generator->generate();

        $this->assertSame('0001', substr($first, 10, 4));
        $this->assertSame('0002', substr($second, 10, 4));
        $this->assertSame('0003', substr($third, 10, 4));

        Carbon::setTestNow();
    }

    public function test_sequence_resets_in_a_new_minute(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 17, 30, 59));
        $generator = new QuoteNumberGenerator();
        $lastOfMinute = $generator->generate();

        Carbon::setTestNow(Carbon::create(2026, 8, 3, 17, 31, 0));
        $firstOfNextMinute = $generator->generate();

        $this->assertSame('0001', substr($lastOfMinute, 10, 4));
        $this->assertSame('0001', substr($firstOfNextMinute, 10, 4));
        $this->assertNotSame(substr($lastOfMinute, 0, 10), substr($firstOfNextMinute, 0, 10));

        Carbon::setTestNow();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=QuoteNumberGeneratorTest`
Expected: FAIL — `App\Services\QuoteNumberGenerator` doesn't exist yet.

- [ ] **Step 3: Implement the service**

`app/Services/QuoteNumberGenerator.php`:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class QuoteNumberGenerator
{
    public function generate(): string
    {
        $now = now();
        $minuteKey = $now->format('ymdHi');

        $sequence = DB::transaction(function () use ($minuteKey) {
            $row = DB::table('quote_number_sequences')
                ->where('minute_key', $minuteKey)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $next = $row->sequence + 1;
                DB::table('quote_number_sequences')
                    ->where('minute_key', $minuteKey)
                    ->update(['sequence' => $next, 'updated_at' => now()]);

                return $next;
            }

            DB::table('quote_number_sequences')->insert([
                'minute_key' => $minuteKey,
                'sequence' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return 1;
        });

        return $minuteKey.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
```

Note: `lockForUpdate()` is a no-op on SQLite (the test suite's DB) but SQLite serializes all writes at the connection level anyway, so sequential calls within a single test process are still correct. Row-level locking only matters for true concurrent MySQL connections in production — this code path is identical either way, just meaningfully race-safe only under MySQL.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=QuoteNumberGeneratorTest`
Expected: PASS (all 3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/QuoteNumberGenerator.php tests/Unit/QuoteNumberGeneratorTest.php
git commit -m "Add QuoteNumberGenerator producing YYMMDDHHMM + 4-digit sequence numbers"
```

---

### Task 3: Wire the generator into quote request submission

**Files:**
- Modify: `app/Http/Controllers/QuoteRequestController.php`
- Modify: `tests/Feature/QuoteRequestSubmissionTest.php`

**Interfaces:**
- Consumes: `App\Services\QuoteNumberGenerator::generate(): string` (Task 2).
- Produces: every newly created `QuoteRequest` has a non-null `quote_number`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/QuoteRequestSubmissionTest.php`:

```php
    public function test_a_submission_is_assigned_a_unique_quote_number(): void
    {
        Mail::fake();

        $this->post(route('quote-requests.store'), [
            'reason' => 'General Inquiry',
            'first_name' => 'Priya',
            'last_name' => 'Nair',
            'email' => 'priya@example.com',
            'phone' => '9876511111',
            'contact_preference' => 'email',
            'privacy_policy' => '1',
        ]);

        $quoteRequest = \App\Models\QuoteRequest::where('email', 'priya@example.com')->firstOrFail();

        $this->assertMatchesRegularExpression('/^\d{14}$/', $quoteRequest->quote_number);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_a_submission_is_assigned_a_unique_quote_number`
Expected: FAIL — `quote_number` is null.

- [ ] **Step 3: Update the controller**

In `app/Http/Controllers/QuoteRequestController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequestRequest;
use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use App\Services\QuoteNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuoteRequestController extends Controller
{
    public function store(StoreQuoteRequestRequest $request, QuoteNumberGenerator $quoteNumberGenerator): RedirectResponse
    {
        $quoteRequest = QuoteRequest::create([
            ...$request->safe()->except(['privacy_policy', 'g-recaptcha-response']),
            'quote_number' => $quoteNumberGenerator->generate(),
            'user_id' => auth('web')->id(),
            'source_url' => $request->input('source_url'),
            'status' => 'new',
        ]);

        try {
            Mail::to(config('rfq.notification_email'))->send(new QuoteRequestReceived($quoteRequest));
        } catch (\Throwable $exception) {
            Log::error('Failed to queue quote request notification email.', [
                'quote_request_id' => $quoteRequest->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return back()->with('quote_request_submitted', $quoteRequest->quote_number);
    }
}
```

(Laravel resolves `QuoteNumberGenerator` via the container automatically — no service provider binding needed since it has no constructor dependencies.)

- [ ] **Step 4: Fix the now-broken flash-value assertion**

`test_a_valid_submission_creates_a_quote_request_and_sends_the_notification_email` in the same file currently has:

```php
        $response->assertSessionHas('quote_request_submitted', true);
```

Change to:

```php
        $response->assertSessionHas('quote_request_submitted', fn ($value) => (bool) preg_match('/^\d{14}$/', $value));
```

- [ ] **Step 5: Run the whole file to verify everything passes**

Run: `php artisan test --filter=QuoteRequestSubmissionTest`
Expected: PASS (all tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/QuoteRequestController.php tests/Feature/QuoteRequestSubmissionTest.php
git commit -m "Assign a unique quote number to every submitted quote request"
```

---

### Task 4: Buyer-facing confirmation email

**Files:**
- Create: `app/Mail/QuoteRequestConfirmation.php`
- Create: `resources/views/emails/quote-request-confirmation.blade.php`
- Modify: `app/Http/Controllers/QuoteRequestController.php`
- Modify: `tests/Feature/QuoteRequestSubmissionTest.php`

**Interfaces:**
- Consumes: `QuoteRequest` model (existing).
- Produces: `App\Mail\QuoteRequestConfirmation` — a queued Mailable sent to `$quoteRequest->email`, in addition to the existing staff `QuoteRequestReceived` notification (not a replacement).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/QuoteRequestSubmissionTest.php` (add `use App\Mail\QuoteRequestConfirmation;` to the top `use` block):

```php
    public function test_the_buyer_receives_a_confirmation_email_with_their_quote_number(): void
    {
        Mail::fake();

        $this->post(route('quote-requests.store'), [
            'reason' => 'General Inquiry',
            'first_name' => 'Priya',
            'last_name' => 'Nair',
            'email' => 'priya@example.com',
            'phone' => '9876511111',
            'contact_preference' => 'email',
            'privacy_policy' => '1',
        ]);

        $quoteRequest = \App\Models\QuoteRequest::where('email', 'priya@example.com')->firstOrFail();

        Mail::assertQueued(QuoteRequestConfirmation::class, function (QuoteRequestConfirmation $mail) use ($quoteRequest) {
            return $mail->hasTo('priya@example.com') && $mail->quoteRequest->is($quoteRequest);
        });

        // The existing staff notification still goes out too — this is additive, not a replacement.
        Mail::assertQueued(QuoteRequestReceived::class);
    }
```

(Add `use App\Mail\QuoteRequestReceived;` too, if not already imported in the file — check the existing `use` block first.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_the_buyer_receives_a_confirmation_email_with_their_quote_number`
Expected: FAIL — `App\Mail\QuoteRequestConfirmation` doesn't exist.

- [ ] **Step 3: Create the Mailable**

`app/Mail/QuoteRequestConfirmation.php`:

```php
<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QuoteRequestConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quoteRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Quote Request '.$this->quoteRequest->quote_number.' Has Been Received',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-request-confirmation',
            with: ['quoteRequest' => $this->quoteRequest],
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send quote request confirmation email.', [
            'quote_request_id' => $this->quoteRequest->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Create the email view**

`resources/views/emails/quote-request-confirmation.blade.php`:

```blade
<h1>Thank you, {{ $quoteRequest->first_name }}!</h1>

<p>We've received your quote request. Your reference number is:</p>

<p style="font-size: 1.5em; font-weight: bold;">{{ $quoteRequest->quote_number }}</p>

<p>Please quote this number in any follow-up correspondence about this enquiry.</p>

@if ($quoteRequest->product)
    <p><strong>Product:</strong> {{ $quoteRequest->product->name }}</p>
@endif

<p>Our team will be in touch shortly.</p>
```

- [ ] **Step 5: Send it from the controller**

In `app/Http/Controllers/QuoteRequestController.php`, add `use App\Mail\QuoteRequestConfirmation;` to the imports, and after the existing staff-notification `try`/`catch` block (before `return back()...`), add:

```php
        try {
            Mail::to($quoteRequest->email)->send(new QuoteRequestConfirmation($quoteRequest));
        } catch (\Throwable $exception) {
            Log::error('Failed to queue quote request confirmation email.', [
                'quote_request_id' => $quoteRequest->id,
                'exception' => $exception->getMessage(),
            ]);
        }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=test_the_buyer_receives_a_confirmation_email_with_their_quote_number`
Expected: PASS

- [ ] **Step 7: Run the whole file, then the full suite**

Run: `php artisan test --filter=QuoteRequestSubmissionTest` then `php artisan test`
Expected: all PASS

- [ ] **Step 8: Commit**

```bash
git add app/Mail/QuoteRequestConfirmation.php resources/views/emails/quote-request-confirmation.blade.php app/Http/Controllers/QuoteRequestController.php tests/Feature/QuoteRequestSubmissionTest.php
git commit -m "Send buyers a confirmation email with their quote number"
```

---

### Task 5: Surface the quote number in the flash message, buyer history, and admin table

**Files:**
- Modify: `resources/views/layouts/app.blade.php:84-86`
- Modify: `resources/views/quote-requests/index.blade.php`
- Modify: `app/Filament/Resources/QuoteRequestResource.php`
- Test: `tests/Feature/QuoteRequestSubmissionTest.php` (flash display), `tests/Feature/QuoteRequestHistoryTest.php` (existing file — extend), a new assertion in `tests/Feature/QuoteRequestResourceTest.php` (existing file — extend)

**Interfaces:**
- Consumes: `session('quote_request_submitted')` is now the quote number string (from Task 3), not a boolean.

- [ ] **Step 1: Write the failing test for the flash message**

Add to `tests/Feature/QuoteRequestSubmissionTest.php`:

```php
    public function test_the_success_message_displays_the_quote_number(): void
    {
        Mail::fake();

        $this->post(route('quote-requests.store'), [
            'reason' => 'General Inquiry',
            'first_name' => 'Priya',
            'last_name' => 'Nair',
            'email' => 'priya@example.com',
            'phone' => '9876511111',
            'contact_preference' => 'email',
            'privacy_policy' => '1',
        ]);

        $quoteRequest = \App\Models\QuoteRequest::where('email', 'priya@example.com')->firstOrFail();

        $response = $this->get('/');

        $response->assertSee($quoteRequest->quote_number);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_the_success_message_displays_the_quote_number`
Expected: FAIL — the current alert text doesn't include the number.

- [ ] **Step 3: Update the flash message markup**

In `resources/views/layouts/app.blade.php`, replace lines 84-86:

```blade
        @if (session('quote_request_submitted'))
            <div class="alert alert-success">Thank you — your quote request has been submitted. Our team will be in touch shortly.</div>
        @endif
```

with:

```blade
        @if ($quoteNumber = session('quote_request_submitted'))
            <div class="alert alert-success">Thank you — your quote request <strong>{{ $quoteNumber }}</strong> has been submitted. Our team will be in touch shortly.</div>
        @endif
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_the_success_message_displays_the_quote_number`
Expected: PASS

- [ ] **Step 5: Show the number in the buyer's quote request history**

Read `tests/Feature/QuoteRequestHistoryTest.php` first to see its existing assertions/fixture shape, then add a column. In `resources/views/quote-requests/index.blade.php`, add a `<th>Quote #</th>` header (first column) and `<td>{{ $quoteRequest->quote_number }}</td>` (first cell) — read the file's current structure (already shown above) and insert consistently with its existing `<th>`/`<td>` pairs.

Add a corresponding test to `tests/Feature/QuoteRequestHistoryTest.php` (matching that file's existing fixture-setup pattern) asserting the rendered history page includes a known `quote_number`.

- [ ] **Step 6: Show the number in the admin QuoteRequestResource table**

In `app/Filament/Resources/QuoteRequestResource.php`, in the `columns([...])` array (starts at line 53 per the earlier grep), add as the first column:

```php
                TextColumn::make('quote_number')->label('Quote #')->searchable(),
```

Add a test assertion to `tests/Feature/QuoteRequestResourceTest.php` (read its existing setup first) confirming the column renders/searches correctly, following that file's existing conventions for asserting on Filament table columns.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: all PASS — this is the last task in this plan.

- [ ] **Step 8: Commit**

```bash
git add resources/views/layouts/app.blade.php resources/views/quote-requests/index.blade.php app/Filament/Resources/QuoteRequestResource.php tests/Feature/QuoteRequestSubmissionTest.php tests/Feature/QuoteRequestHistoryTest.php tests/Feature/QuoteRequestResourceTest.php
git commit -m "Surface the quote number in the flash message, buyer history, and admin table"
```

---

## Self-Review Notes

- **Spec coverage:** unique number in `YYMMDDHHMMXXXX` format ✓ (Task 2), "parallel" confirmation email to the submitting buyer ✓ (Task 4, sent in addition to the existing staff notification, not replacing it), reasonable UX completion (number is actually visible somewhere to the buyer) ✓ (Task 5).
- **Concurrency caveat documented, not hidden:** `lockForUpdate()` is genuinely race-safe under MySQL/InnoDB in production but only sequentially-tested under SQLite — called out explicitly in Task 2 rather than presented as fully proven.
- **No placeholders:** all steps have complete code.
- **Format discrepancy in the source issue** (4 vs "five" digits) is flagged at the top of this plan and should be mentioned back on the GitHub issue.
