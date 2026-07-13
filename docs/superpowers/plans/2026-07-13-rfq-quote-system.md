# RFQ / Quote System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a buyer submit a "Request a Quote" enquiry from any published Product Detail page, store it as a `quote_requests` row, notify Sales by email, and give Admin/Sales a Filament dashboard to triage, note, and export those requests — with Content Editor and Sellers excluded per the RBAC design.

**Architecture:** A public POST endpoint validates and stores the request (email notification is a best-effort side channel, never blocking or losing the stored record), then a Filament `QuoteRequestResource` (Sales + Admin only) manages the inbox with a notes relation manager and a CSV export action. Reuses the Foundation & Catalog phase's `staff`/RBAC/Filament setup — no new panels or guards.

**Tech Stack:** Laravel 11, Filament v3, spatie/laravel-permission (existing), Laravel `Mail`, Bootstrap 5 (modal + JS bundle, not yet loaded — added in this plan).

## Global Constraints

- Full requirements: `docs/superpowers/specs/2026-07-12-catalog-cms-rfq-design.md`, "RFQ / Quote Request Workflow" and "CMS Admin (Filament) & RBAC" sections — read before deviating.
- No payment/checkout code anywhere in this system.
- `quote_requests.user_id` stays `null` in this phase — there is no buyer login flow yet (that's a separate future phase); the column exists now so that phase doesn't need a migration later.
- reCAPTCHA is wired but dormant: verification is skipped entirely when `RECAPTCHA_SITE_KEY`/`RECAPTCHA_SECRET_KEY` are not configured (they aren't yet), so local dev and tests never depend on real Google credentials or network access.
- A failure to send the notification email must never lose the stored `quote_requests` row — the row is always saved first; email send is wrapped so it can fail silently (logged, not thrown).
- Only `admin` and `sales` staff roles may access quote requests in any form (list, view, notes, export). `content_editor` must get a 403, not just a hidden nav item.
- Sellers have no visibility into `quote_requests` in this phase (the Seller Portal doesn't exist yet — this constraint is enforced simply by no seller-facing code touching this table at all).
- Only the product-page launch point is built now (a "Get a Quote" button on `catalog/product.blade.php`). The general-inquiry launch point (embedding the same form on a Contact-Us content page) is out of scope — that page system doesn't exist yet — but the backend (`product_id` nullable, controller, validation) already supports a null-product submission so that later phase can reuse it without changes here.
- TDD: write the failing test before the implementation, for every task that has application logic.
- Commit after every task.

---

### Task 1: `quote_requests` and `quote_request_notes` schema and models

**Files:**
- Create: `database/migrations/2026_07_13_090000_create_quote_requests_table.php`
- Create: `database/migrations/2026_07_13_090100_create_quote_request_notes_table.php`
- Create: `app/Models/QuoteRequest.php`, `app/Models/QuoteRequestNote.php`
- Create: `database/factories/QuoteRequestFactory.php`
- Test: `tests/Feature/QuoteRequestModelTest.php`

**Interfaces:**
- Produces: `App\Models\QuoteRequest` (fields: `product_id` nullable, `user_id` nullable, `reason`, `first_name`, `last_name`, `email`, `phone`, `company`, `country`, `market`, `city`, `state`, `message`, `contact_preference`, `source_url`, `status`, `assigned_to` nullable) with `product()`, `assignee()`, `notes()` relations and `fullName(): string`. `App\Models\QuoteRequestNote` (fields: `quote_request_id`, `staff_id`, `note`, `created_at` only — no `updated_at`) with `quoteRequest()`, `staff()` relations. Later tasks (Mailable, controller, Filament resource) depend on this exact shape.
- Consumes: `Product` (existing), `Staff` (existing), `users` table (existing, from the legacy app's default Laravel scaffolding).

- [ ] **Step 1: Write the failing test**

`tests/Feature/QuoteRequestModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestNote;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_quote_request_can_belong_to_a_product(): void
    {
        $product = Product::factory()->create();
        $quoteRequest = QuoteRequest::factory()->create(['product_id' => $product->id]);

        $this->assertTrue($quoteRequest->product->is($product));
    }

    public function test_a_quote_request_can_be_a_general_inquiry_with_no_product(): void
    {
        $quoteRequest = QuoteRequest::factory()->create(['product_id' => null]);

        $this->assertNull($quoteRequest->product);
    }

    public function test_a_quote_request_can_have_notes_from_staff(): void
    {
        $quoteRequest = QuoteRequest::factory()->create();
        $staff = Staff::factory()->create();

        $note = QuoteRequestNote::create([
            'quote_request_id' => $quoteRequest->id,
            'staff_id' => $staff->id,
            'note' => 'Called the buyer, following up next week.',
        ]);

        $this->assertTrue($quoteRequest->notes->contains($note));
        $this->assertTrue($note->staff->is($staff));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=QuoteRequestModelTest
```

Expected: FAIL — `QuoteRequest` class / `quote_requests` table doesn't exist.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_07_13_090000_create_quote_requests_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->default('Request a Quote');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->string('company')->nullable();
            $table->string('country')->nullable();
            $table->string('market')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->text('message')->nullable();
            $table->string('contact_preference')->default('email'); // email|phone
            $table->string('source_url')->nullable();
            $table->string('status')->default('new'); // new|in_progress|closed
            $table->foreignId('assigned_to')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
```

`database/migrations/2026_07_13_090100_create_quote_request_notes_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_request_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->text('note');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_request_notes');
    }
};
```

- [ ] **Step 4: Write the models**

`app/Models/QuoteRequest.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'user_id', 'reason', 'first_name', 'last_name', 'email',
        'phone', 'company', 'country', 'market', 'city', 'state', 'message',
        'contact_preference', 'source_url', 'status', 'assigned_to',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(QuoteRequestNote::class)->latest();
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
```

`app/Models/QuoteRequestNote.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteRequestNote extends Model
{
    public $timestamps = false;

    protected $fillable = ['quote_request_id', 'staff_id', 'note'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (QuoteRequestNote $note) {
            $note->created_at ??= now();
        });
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
```

- [ ] **Step 5: Write the factory**

`database/factories/QuoteRequestFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\QuoteRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuoteRequestFactory extends Factory
{
    protected $model = QuoteRequest::class;

    public function definition(): array
    {
        return [
            'reason' => 'Request a Quote',
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'company' => $this->faker->company(),
            'country' => 'India',
            'market' => 'Industrial',
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'message' => $this->faker->paragraph(),
            'contact_preference' => 'email',
            'status' => 'new',
        ];
    }
}
```

- [ ] **Step 6: Run migration and test**

```bash
php artisan migrate:fresh
php artisan test --filter=QuoteRequestModelTest
```

Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Add quote_requests and quote_request_notes schema and models"
```

---

### Task 2: RFQ static config and the dormant-by-default reCAPTCHA rule

**Files:**
- Create: `config/rfq.php`
- Modify: `config/services.php`
- Create: `app/Rules/Recaptcha.php`
- Modify: `.env.example`
- Test: `tests/Feature/RecaptchaRuleTest.php`

**Interfaces:**
- Produces: `config('rfq.notification_email')`, `config('rfq.reasons')`, `config('rfq.contact_preferences')`, `config('rfq.countries')`, `config('rfq.markets')` (all associative arrays, `value => label`). `App\Rules\Recaptcha` (implements `Illuminate\Contracts\Validation\ValidationRule`) — passes trivially when `config('services.recaptcha.secret_key')` is blank; otherwise verifies against Google's siteverify API. Later tasks (the RFQ form) consume all of these.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/RecaptchaRuleTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Rules\Recaptcha;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RecaptchaRuleTest extends TestCase
{
    public function test_validation_passes_when_recaptcha_is_not_configured(): void
    {
        config(['services.recaptcha.secret_key' => null]);

        $validator = Validator::make(['g-recaptcha-response' => ''], [
            'g-recaptcha-response' => [new Recaptcha()],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_when_configured_and_google_rejects_the_token(): void
    {
        config(['services.recaptcha.secret_key' => 'test-secret']);
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $validator = Validator::make(['g-recaptcha-response' => 'bad-token'], [
            'g-recaptcha-response' => [new Recaptcha()],
        ]);

        $this->assertFalse($validator->passes());
    }

    public function test_validation_passes_when_configured_and_google_accepts_the_token(): void
    {
        config(['services.recaptcha.secret_key' => 'test-secret']);
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $validator = Validator::make(['g-recaptcha-response' => 'good-token'], [
            'g-recaptcha-response' => [new Recaptcha()],
        ]);

        $this->assertTrue($validator->passes());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=RecaptchaRuleTest
```

Expected: FAIL — `App\Rules\Recaptcha` doesn't exist.

- [ ] **Step 3: Write the config files**

`config/rfq.php`:

```php
<?php

return [
    'notification_email' => env('RFQ_NOTIFICATION_EMAIL', 'sales@example.com'),

    'reasons' => [
        'Request a Quote' => 'Request a Quote',
        'General Inquiry' => 'General Inquiry',
    ],

    'contact_preferences' => [
        'email' => 'Email',
        'phone' => 'Phone',
    ],

    'countries' => [
        'India' => 'India',
        'United States' => 'United States',
        'United Kingdom' => 'United Kingdom',
        'United Arab Emirates' => 'United Arab Emirates',
        'Singapore' => 'Singapore',
        'Australia' => 'Australia',
        'Germany' => 'Germany',
        'Other' => 'Other',
    ],

    'markets' => [
        'Broadband' => 'Broadband',
        'Enterprise' => 'Enterprise',
        'Energy' => 'Energy',
        'Industrial' => 'Industrial',
        'Hyperscale' => 'Hyperscale',
    ],
];
```

Add to `config/services.php`'s returned array (alongside the existing `postmark`/`ses`/`resend`/`slack` entries):

```php
'recaptcha' => [
    'site_key' => env('RECAPTCHA_SITE_KEY'),
    'secret_key' => env('RECAPTCHA_SECRET_KEY'),
],
```

Add to `.env.example` (new lines, anywhere sensible — e.g. near the bottom):

```
RFQ_NOTIFICATION_EMAIL=sales@example.com
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
```

- [ ] **Step 4: Write the rule**

`app/Rules/Recaptcha.php`:

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Recaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.recaptcha.secret_key');

        if (blank($secret)) {
            // reCAPTCHA is not configured for this environment — skip verification
            // rather than blocking every submission until keys are provisioned.
            return;
        }

        if (blank($value)) {
            $fail('Please confirm you are not a robot.');

            return;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $value,
        ]);

        if (! $response->json('success', false)) {
            $fail('reCAPTCHA verification failed. Please try again.');
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test --filter=RecaptchaRuleTest
```

Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Add RFQ static config and a dormant-by-default reCAPTCHA validation rule"
```

---

### Task 3: Quote request notification email

**Files:**
- Create: `app/Mail/QuoteRequestReceived.php`
- Create: `resources/views/emails/quote-request-received.blade.php`
- Test: `tests/Feature/QuoteRequestMailTest.php`

**Interfaces:**
- Produces: `App\Mail\QuoteRequestReceived` (constructed with a `QuoteRequest $quoteRequest` public property), a `Mailable` renderable via `Mail::to(...)->send(new QuoteRequestReceived($quoteRequest))`. Task 4's controller sends this.
- Consumes: `QuoteRequest` (Task 1).

- [ ] **Step 1: Write the failing test**

`tests/Feature/QuoteRequestMailTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notification_email_renders_the_quote_requests_details(): void
    {
        $quoteRequest = QuoteRequest::factory()->create([
            'first_name' => 'Asha',
            'last_name' => 'Rao',
            'email' => 'asha@example.com',
            'message' => 'Need pricing for 500 meters.',
        ]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertSeeInHtml('Asha Rao');
        $mailable->assertSeeInHtml('asha@example.com');
        $mailable->assertSeeInHtml('Need pricing for 500 meters.');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=QuoteRequestMailTest
```

Expected: FAIL — `App\Mail\QuoteRequestReceived` doesn't exist.

- [ ] **Step 3: Write the Mailable**

`app/Mail/QuoteRequestReceived.php`:

```php
<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quoteRequest)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Quote Request from '.$this->quoteRequest->fullName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-request-received',
            with: ['quoteRequest' => $this->quoteRequest],
        );
    }
}
```

- [ ] **Step 4: Write the email view**

`resources/views/emails/quote-request-received.blade.php`:

```blade
<h1>New Quote Request</h1>

<p><strong>Reason:</strong> {{ $quoteRequest->reason }}</p>
<p><strong>Name:</strong> {{ $quoteRequest->fullName() }}</p>
<p><strong>Email:</strong> {{ $quoteRequest->email }}</p>
<p><strong>Phone:</strong> {{ $quoteRequest->phone }}</p>
<p><strong>Company:</strong> {{ $quoteRequest->company }}</p>

@if ($quoteRequest->product)
    <p><strong>Product:</strong> {{ $quoteRequest->product->name }}</p>
@endif

@if ($quoteRequest->message)
    <p><strong>Message:</strong></p>
    <p>{{ $quoteRequest->message }}</p>
@endif

<p>
    <a href="{{ url('/admin/quote-requests/'.$quoteRequest->id) }}">View in the CMS</a>
</p>
```

- [ ] **Step 5: Run test to verify it passes**

```bash
php artisan test --filter=QuoteRequestMailTest
```

Expected: PASS (1 test)

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Add the quote request notification email"
```

---

### Task 4: Public RFQ form, submission endpoint, and product-page wiring

**Files:**
- Create: `app/Http/Requests/StoreQuoteRequestRequest.php`
- Create: `app/Http/Controllers/QuoteRequestController.php`
- Create: `resources/views/partials/quote-request-form.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/catalog/product.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/QuoteRequestSubmissionTest.php`

**Interfaces:**
- Produces: `POST /quote-requests` (named `quote-requests.store`) — validates via `StoreQuoteRequestRequest`, creates a `QuoteRequest`, attempts (non-fatally) to send `QuoteRequestReceived`, redirects back with `session('quote_request_submitted', true)`. A reusable Blade partial `partials.quote-request-form` accepting an optional `$product` variable (omitted/null → general inquiry).
- Consumes: `QuoteRequest` (Task 1), `App\Rules\Recaptcha` + `config('rfq.*')` (Task 2), `QuoteRequestReceived` (Task 3).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/QuoteRequestSubmissionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Mail\QuoteRequestReceived;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuoteRequestSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_submission_creates_a_quote_request_and_sends_the_notification_email(): void
    {
        Mail::fake();

        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->post(route('quote-requests.store'), [
            'product_id' => $product->id,
            'reason' => 'Request a Quote',
            'first_name' => 'Asha',
            'last_name' => 'Rao',
            'email' => 'asha@example.com',
            'phone' => '9876543210',
            'company' => 'Rao Traders',
            'country' => 'India',
            'market' => 'Industrial',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'message' => 'Need pricing for 500 meters.',
            'contact_preference' => 'email',
            'privacy_policy' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('quote_request_submitted', true);

        $this->assertDatabaseHas('quote_requests', [
            'product_id' => $product->id,
            'email' => 'asha@example.com',
            'status' => 'new',
        ]);

        Mail::assertSent(QuoteRequestReceived::class, function (QuoteRequestReceived $mail) use ($product) {
            return $mail->quoteRequest->product_id === $product->id;
        });
    }

    public function test_a_general_inquiry_without_a_product_is_accepted(): void
    {
        Mail::fake();

        $response = $this->post(route('quote-requests.store'), [
            'reason' => 'General Inquiry',
            'first_name' => 'Vikram',
            'last_name' => 'Singh',
            'email' => 'vikram@example.com',
            'phone' => '9876500000',
            'contact_preference' => 'phone',
            'privacy_policy' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('quote_requests', [
            'product_id' => null,
            'email' => 'vikram@example.com',
        ]);
    }

    public function test_an_invalid_submission_is_rejected_with_validation_errors(): void
    {
        Mail::fake();

        $response = $this->post(route('quote-requests.store'), [
            'reason' => 'Request a Quote',
        ]);

        $response->assertSessionHasErrors(['first_name', 'last_name', 'email', 'phone', 'contact_preference', 'privacy_policy']);
        $this->assertDatabaseCount('quote_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_the_notification_is_sent_to_the_configured_recipient_address(): void
    {
        config(['rfq.notification_email' => 'custom-sales@example.com']);
        Mail::fake();

        $this->post(route('quote-requests.store'), [
            'reason' => 'General Inquiry',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'contact_preference' => 'email',
            'privacy_policy' => '1',
        ]);

        Mail::assertSent(QuoteRequestReceived::class, function (QuoteRequestReceived $mail) {
            return $mail->hasTo('custom-sales@example.com');
        });
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=QuoteRequestSubmissionTest
```

Expected: FAIL — `/quote-requests` route doesn't exist.

- [ ] **Step 3: Write the form request**

`app/Http/Requests/StoreQuoteRequestRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Rules\Recaptcha;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'exists:products,id'],
            'reason' => ['required', 'in:Request a Quote,General Inquiry'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'market' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'contact_preference' => ['required', 'in:email,phone'],
            'privacy_policy' => ['accepted'],
            'g-recaptcha-response' => [new Recaptcha()],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/QuoteRequestController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequestRequest;
use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuoteRequestController extends Controller
{
    public function store(StoreQuoteRequestRequest $request): RedirectResponse
    {
        $quoteRequest = QuoteRequest::create([
            ...$request->safe()->except(['privacy_policy', 'g-recaptcha-response']),
            'user_id' => auth('web')->id(),
            'source_url' => $request->input('source_url'),
            'status' => 'new',
        ]);

        try {
            Mail::to(config('rfq.notification_email'))->send(new QuoteRequestReceived($quoteRequest));
        } catch (\Throwable $exception) {
            Log::error('Failed to send quote request notification email.', [
                'quote_request_id' => $quoteRequest->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return back()->with('quote_request_submitted', true);
    }
}
```

- [ ] **Step 5: Register the route**

Add to `routes/web.php` (above the `/products/{path?}` wildcard route is not required here since `/quote-requests` doesn't collide with `/products`, but keep it grouped near the other named routes for readability):

```php
use App\Http\Controllers\QuoteRequestController;

Route::post('/quote-requests', [QuoteRequestController::class, 'store'])->name('quote-requests.store');
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test --filter=QuoteRequestSubmissionTest
```

Expected: PASS (4 tests)

- [ ] **Step 7: Write the reusable form partial**

`resources/views/partials/quote-request-form.blade.php`:

```blade
@php
    $modalId = isset($product) ? 'quoteRequestModal-'.$product->id : 'quoteRequestModal';
    $defaultReason = isset($product) ? 'Request a Quote' : 'General Inquiry';
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request a Quote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('quote-requests.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @isset($product)
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <p class="text-muted">Regarding: <strong>{{ $product->name }}</strong></p>
                    @endisset
                    <input type="hidden" name="source_url" value="{{ url()->current() }}">

                    <div class="mb-3">
                        <label class="form-label">Reason for Contact</label>
                        <select name="reason" class="form-select" required>
                            @foreach (config('rfq.reasons') as $value => $label)
                                <option value="{{ $value }}" @selected($value === $defaultReason)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Company</label>
                            <input type="text" name="company" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Select Country</label>
                            <select name="country" class="form-select">
                                <option value="">Select Country</option>
                                @foreach (config('rfq.countries') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Select Market</label>
                            <select name="market" class="form-select">
                                <option value="">Select Market</option>
                                @foreach (config('rfq.markets') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control" rows="4">{{ isset($product) ? 'I am interested in '.$product->name.' ('.url()->current().')' : '' }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">How would you prefer to be contacted?</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="contact_preference" value="email" id="contact-email-{{ $modalId }}" checked>
                            <label class="form-check-label" for="contact-email-{{ $modalId }}">Email</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="contact_preference" value="phone" id="contact-phone-{{ $modalId }}">
                            <label class="form-check-label" for="contact-phone-{{ $modalId }}">Phone</label>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" name="privacy_policy" class="form-check-input" id="privacy-{{ $modalId }}" required>
                        <label class="form-check-label" for="privacy-{{ $modalId }}">I have read and accepted the Privacy Policy.</label>
                    </div>

                    @if (config('services.recaptcha.site_key'))
                        <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
```

- [ ] **Step 8: Wire the "Get a Quote" button on the product page**

In `resources/views/catalog/product.blade.php`, replace the placeholder link:

```blade
{{-- The "Get a Quote" flow is built in the RFQ / Quote System plan; this is a placeholder link until then. --}}
<a href="#" class="btn btn-primary mt-3">Get a Quote</a>
```

with:

```blade
<button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#quoteRequestModal-{{ $product->id }}">Get a Quote</button>
```

and add, right after the `@endsection` line's content (i.e. as the last line inside the `@section('content')` block, after the "Related Products" block):

```blade
@include('partials.quote-request-form', ['product' => $product])
```

- [ ] **Step 9: Add the Bootstrap JS bundle and a success flash message to the layout**

In `resources/views/layouts/app.blade.php`, add the Bootstrap JS bundle before `</body>` (the modal's `data-bs-toggle`/`data-bs-target` attributes need it — only the CSS was loaded before this task):

```blade
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
```

And add a success banner inside `<main>`, before `@yield('content')`:

```blade
    <main class="container py-4">
        @if (session('quote_request_submitted'))
            <div class="alert alert-success">Thank you — your quote request has been submitted. Our team will be in touch shortly.</div>
        @endif
        @yield('content')
    </main>
```

- [ ] **Step 10: Manual verification**

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Visit `http://127.0.0.1:8000/products/fiber-optic-cable/aerial/opgw/centracore-opgw-cable`, click "Get a Quote", fill the form, submit. Expected: redirected back to the same page with the success banner visible.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "Add the public RFQ form, submission endpoint, and product-page wiring"
```

---

### Task 5: `QuoteRequestPolicy` and the Filament `QuoteRequestResource`

**Files:**
- Create: `app/Policies/QuoteRequestPolicy.php`
- Create: `app/Filament/Resources/QuoteRequestResource.php`, `app/Filament/Resources/QuoteRequestResource/Pages/{ListQuoteRequests,ViewQuoteRequest,EditQuoteRequest}.php` (generated)
- Create: `app/Filament/Resources/QuoteRequestResource/RelationManagers/NotesRelationManager.php` (generated then edited)
- Test: `tests/Feature/QuoteRequestPolicyTest.php`, `tests/Feature/QuoteRequestResourceTest.php`

**Interfaces:**
- Produces: `App\Policies\QuoteRequestPolicy` with `viewAny`/`view`/`update` → `admin`+`sales` only, `create` → always `false` (requests are only created via the public form, never manually in the CMS), `delete` → `admin` only. A Filament resource at `/admin/quote-requests` with list/view/edit pages and a `notes` relation manager, auto-discovered by Laravel for the `QuoteRequest` model.
- Consumes: `QuoteRequest` (Task 1), `Staff`/roles (existing).

- [ ] **Step 1: Generate the resource and relation manager**

```bash
php artisan make:filament-resource QuoteRequest
php artisan make:filament-relation-manager QuoteRequestResource notes note
```

Then delete the generated `app/Filament/Resources/QuoteRequestResource/Pages/CreateQuoteRequest.php` — this resource has no "create" page (requests are only ever created via the public form; `QuoteRequestPolicy::create()` will return `false`, so Filament wouldn't render a "New" button anyway, but the page/route shouldn't exist either).

- [ ] **Step 2: Write the failing policy test**

`tests/Feature/QuoteRequestPolicyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_sales_and_admin_can_view_quote_requests_but_content_editor_cannot(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $this->assertTrue($sales->can('viewAny', QuoteRequest::class));
        $this->assertTrue($admin->can('viewAny', QuoteRequest::class));
        $this->assertFalse($editor->can('viewAny', QuoteRequest::class));
    }

    public function test_only_admin_can_delete_a_quote_request(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $quoteRequest = QuoteRequest::factory()->create();

        $this->assertFalse($sales->can('delete', $quoteRequest));
        $this->assertTrue($admin->can('delete', $quoteRequest));
    }

    public function test_no_one_can_create_a_quote_request_through_the_policy(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $this->assertFalse($admin->can('create', QuoteRequest::class));
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
php artisan test --filter=QuoteRequestPolicyTest
```

Expected: FAIL — the generated `QuoteRequestPolicy` doesn't have these role-based rules yet.

- [ ] **Step 4: Write the policy**

`app/Policies/QuoteRequestPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\QuoteRequest;
use App\Models\Staff;

class QuoteRequestPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'sales']);
    }

    public function view(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasAnyRole(['admin', 'sales']);
    }

    public function create(Staff $staff): bool
    {
        return false;
    }

    public function update(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasAnyRole(['admin', 'sales']);
    }

    public function delete(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasRole('admin');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
php artisan test --filter=QuoteRequestPolicyTest
```

Expected: PASS (3 tests)

- [ ] **Step 6: Write the failing resource test**

`tests/Feature/QuoteRequestResourceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\QuoteRequestResource\Pages\ListQuoteRequests;
use App\Models\QuoteRequest;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_sales_can_view_the_quote_requests_list(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $quoteRequest = QuoteRequest::factory()->create(['first_name' => 'Asha']);

        Livewire::test(ListQuoteRequests::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$quoteRequest]);
    }

    public function test_content_editor_gets_a_403_visiting_the_quote_requests_list(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $response = $this->get('/admin/quote-requests');

        $response->assertForbidden();
    }

    public function test_sales_can_add_a_note_to_a_quote_request(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $quoteRequest = QuoteRequest::factory()->create();

        $quoteRequest->notes()->create([
            'staff_id' => $sales->id,
            'note' => 'Left a voicemail.',
        ]);

        $this->assertCount(1, $quoteRequest->fresh()->notes);
    }
}
```

- [ ] **Step 7: Run test to verify it fails**

```bash
php artisan test --filter=QuoteRequestResourceTest
```

Expected: FAIL — `app/Filament/Resources/QuoteRequestResource.php`'s generated `form()`/`table()` don't yet show the fields these tests expect, and `content_editor` isn't yet blocked.

- [ ] **Step 8: Replace the generated `form()`, `table()`, and `getRelations()`/`getPages()` methods**

In `app/Filament/Resources/QuoteRequestResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuoteRequestResource\Pages;
use App\Filament\Resources\QuoteRequestResource\RelationManagers\NotesRelationManager;
use App\Models\QuoteRequest;
use App\Models\Staff;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QuoteRequestResource extends Resource
{
    protected static ?string $model = QuoteRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Placeholder::make('contact')
                ->label('Contact')
                ->content(fn (QuoteRequest $record) => "{$record->first_name} {$record->last_name} — {$record->email} — {$record->phone}"),
            Placeholder::make('product')
                ->label('Product')
                ->content(fn (QuoteRequest $record) => $record->product?->name ?? 'General inquiry'),
            Placeholder::make('message')
                ->label('Message')
                ->content(fn (QuoteRequest $record) => $record->message ?? '—'),
            Select::make('status')
                ->options([
                    'new' => 'New',
                    'in_progress' => 'In Progress',
                    'closed' => 'Closed',
                ])
                ->required(),
            Select::make('assigned_to')
                ->label('Assigned To')
                ->options(fn () => Staff::query()->pluck('name', 'id'))
                ->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Received')->dateTime()->sortable(),
                TextColumn::make('first_name')
                    ->label('Name')
                    ->formatStateUsing(fn (QuoteRequest $record) => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')->searchable(),
                TextColumn::make('product.name')->label('Product')->placeholder('General inquiry'),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('assignee.name')->label('Assigned To')->placeholder('Unassigned'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'new' => 'New',
                    'in_progress' => 'In Progress',
                    'closed' => 'Closed',
                ]),
                SelectFilter::make('assigned_to')->label('Assigned To')->options(fn () => Staff::query()->pluck('name', 'id')),
                SelectFilter::make('product_id')->label('Product')->relationship('product', 'name'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuoteRequests::route('/'),
            'view' => Pages\ViewQuoteRequest::route('/{record}'),
            'edit' => Pages\EditQuoteRequest::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 9: Edit the notes relation manager**

In `app/Filament/Resources/QuoteRequestResource/RelationManagers/NotesRelationManager.php`:

```php
<?php

namespace App\Filament\Resources\QuoteRequestResource\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public function form(Form $form): Form
    {
        return $form->schema([
            Textarea::make('note')->required()->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('note')
            ->columns([
                TextColumn::make('note')->wrap(),
                TextColumn::make('staff.name')->label('By'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['staff_id'] = auth('staff')->id();

                        return $data;
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
```

- [ ] **Step 10: Run test to verify it passes**

```bash
php artisan test --filter=QuoteRequestResourceTest
```

Expected: PASS (3 tests)

- [ ] **Step 11: Manual verification**

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Submit a quote request from a product page (per Task 4's manual check), then log into `/admin/quote-requests` as the seeded admin. Confirm the request appears, and that adding a note on its detail page works.

- [ ] **Step 12: Commit**

```bash
git add -A
git commit -m "Add QuoteRequestPolicy and the Filament QuoteRequestResource with notes"
```

---

### Task 6: CSV export action

**Files:**
- Modify: `app/Filament/Resources/QuoteRequestResource.php`
- Test: `tests/Feature/QuoteRequestExportTest.php`

**Interfaces:**
- Produces: an "Export CSV" header action on the `QuoteRequestResource` list table, streaming a CSV of all quote requests (respecting no filter state — exports everything; the spec's stated integration point for a future CRM handoff, not a per-view filtered export).
- Consumes: `QuoteRequestResource` (Task 5).

- [ ] **Step 1: Write the failing test**

`tests/Feature/QuoteRequestExportTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\QuoteRequestResource\Pages\ListQuoteRequests;
use App\Models\QuoteRequest;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteRequestExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_export_quote_requests_to_csv(): void
    {
        $this->seed(RoleSeeder::class);
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        QuoteRequest::factory()->create(['email' => 'export-target@example.com']);

        Livewire::test(ListQuoteRequests::class)
            ->callAction('export')
            ->assertFileDownloaded('quote-requests.csv');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --filter=QuoteRequestExportTest
```

Expected: FAIL — no `export` action exists on the table yet.

If `assertFileDownloaded()` isn't available on the Livewire test object with the installed Filament/Livewire versions, check `vendor/filament/filament` and `vendor/livewire/livewire` for the equivalent helper (Filament registers Livewire test macros for its actions) and use that instead — don't skip verifying the download actually happens.

- [ ] **Step 3: Add the export action**

In `app/Filament/Resources/QuoteRequestResource.php`, add these imports:

```php
use App\Models\QuoteRequest;
use Filament\Tables\Actions\Action;
```

(`QuoteRequest` is likely already imported from Task 5; don't duplicate the `use` line if so.)

Add a `->headerActions([...])` call to the `table()` method's chain (after `->filters([...])`, before `->defaultSort(...)`):

```php
->headerActions([
    Action::make('export')
        ->label('Export CSV')
        ->action(function () {
            return response()->streamDownload(function () {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Received', 'First Name', 'Last Name', 'Email', 'Phone', 'Company', 'Product', 'Status', 'Assigned To', 'Message']);

                QuoteRequest::query()
                    ->with(['product', 'assignee'])
                    ->orderByDesc('created_at')
                    ->each(function (QuoteRequest $quoteRequest) use ($handle) {
                        fputcsv($handle, [
                            $quoteRequest->created_at->toDateTimeString(),
                            $quoteRequest->first_name,
                            $quoteRequest->last_name,
                            $quoteRequest->email,
                            $quoteRequest->phone,
                            $quoteRequest->company,
                            $quoteRequest->product?->name,
                            $quoteRequest->status,
                            $quoteRequest->assignee?->name,
                            $quoteRequest->message,
                        ]);
                    });

                fclose($handle);
            }, 'quote-requests.csv');
        }),
])
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test --filter=QuoteRequestExportTest
```

Expected: PASS (1 test)

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "Add CSV export action to the Quote Requests dashboard"
```

---

### Task 7: End-to-end verification

**Files:** none (verification only)

**Interfaces:** none — this task confirms Tasks 1–6 integrate correctly.

- [ ] **Step 1: Full reset, seed, and test suite**

```bash
php artisan migrate:fresh --seed
php artisan test
```

Expected: no errors; all tests pass (should be roughly 45 tests — the ~29 from the Foundation & Catalog phase plus this plan's new tests; the exact count doesn't matter as long as everything is green).

- [ ] **Step 2: Manual smoke test**

```bash
php artisan serve
```

- Visit a seeded product page (e.g. `http://127.0.0.1:8000/products/fiber-optic-cable/aerial/opgw/centracore-opgw-cable`), click "Get a Quote", submit a valid request. Confirm the success banner appears.
- Submit the form again with an empty "First Name" field. Confirm the page re-renders with a validation error (open the modal again to see it).
- Log into `/admin` as `admin@example.com`, open "Quote Requests" in the nav, confirm the just-submitted request appears with status "New".
- Open the request, add an internal note, confirm it's saved and attributed to the logged-in admin.
- Click "Export CSV" on the list page, confirm a `quote-requests.csv` file downloads containing the submitted request.
- Log in as a `content_editor`-role staff member (you'll need to create one via `php artisan tinker` — `App\Models\Staff::factory()->create()->assignRole('content_editor')` — since none is seeded by default) and confirm visiting `/admin/quote-requests` returns a 403, and the "Quote Requests" nav item doesn't appear for them.

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "RFQ / Quote System plan complete: verified end-to-end"
```
