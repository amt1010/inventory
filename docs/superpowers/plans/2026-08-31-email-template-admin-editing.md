# Admin-Editable Email Templates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let Admin/Content Editor staff edit and publish the subject, body, and CC/BCC of 8 transactional emails from `/admin/email-templates`, plus create free-form custom templates for future use, without a developer touching code for a wording change.

**Architecture:** One `email_templates` table with a draft/live column pair per editable field. A small regex-based `EmailTemplateRenderer` service does `{{token}}` substitution and `{{#token}}...{{/token}}` presence-based sections — no Blade compilation, no code execution. The 8 relevant `Mailable` classes look up their `EmailTemplate` row by a fixed `key` and render through the service instead of a hardcoded Blade view. A new `EmailTemplateResource` (Filament) provides the CRUD/draft/publish/preview UI.

**Tech Stack:** Laravel 11, Filament v3, MySQL (SQLite in tests), Livewire/Filament testing helpers.

**Spec:** `docs/superpowers/specs/2026-08-31-email-template-admin-editing-design.md`

## Global Constraints

- No Blade compilation or `eval` of admin-authored content anywhere — the renderer only does string substitution (spec: "Why not let admins edit raw Blade").
- `product-edit-ready-for-acceptance` and `seller-import-stuck` stay hardcoded, untouched by this plan (spec: "Purpose").
- After the migration+seeder land, every email must render byte-identical to today until an admin explicitly edits and publishes something (spec: "Data model").
- Token keys ending in `_html` are the only ones rendered unescaped (trusted, code-built HTML fragments); everything else is HTML-escaped.
- Unknown `{{...}}` tokens in a body are left as literal text, never evaluated.
- RBAC: `admin` and `content_editor` get full access to `/admin/email-templates`; `sales` gets none (spec: "RBAC").

---

### Task 1: `email_templates` table and `EmailTemplate` model

**Files:**
- Create: `database/migrations/2026_08_31_100000_create_email_templates_table.php`
- Create: `app/Models/EmailTemplate.php`
- Test: `tests/Unit/EmailTemplateTest.php`

**Interfaces:**
- Produces: `EmailTemplate::forKey(string $key): self`, `$template->isModified(): bool`, `$template->publish(): void`, `$template->resetDraft(): void`, `$template->ccAddresses(): array`, `$template->bccAddresses(): array`. Columns: `key`, `label`, `is_system` (bool), `subject`, `body`, `default_cc`, `default_bcc`, `draft_subject`, `draft_body`, `draft_default_cc`, `draft_default_bcc`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(array $overrides = []): EmailTemplate
    {
        return EmailTemplate::create(array_merge([
            'key' => 'sample_key',
            'label' => 'Sample',
            'is_system' => true,
            'subject' => 'Live subject',
            'body' => '<p>Live body</p>',
            'default_cc' => null,
            'default_bcc' => null,
            'draft_subject' => 'Live subject',
            'draft_body' => '<p>Live body</p>',
            'draft_default_cc' => null,
            'draft_default_bcc' => null,
        ], $overrides));
    }

    public function test_for_key_finds_a_template_by_its_key(): void
    {
        $this->makeTemplate(['key' => 'seller_rejected']);

        $found = EmailTemplate::forKey('seller_rejected');

        $this->assertSame('seller_rejected', $found->key);
    }

    public function test_for_key_throws_when_the_key_does_not_exist(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        EmailTemplate::forKey('does_not_exist');
    }

    public function test_is_modified_is_false_when_draft_matches_live(): void
    {
        $template = $this->makeTemplate();

        $this->assertFalse($template->isModified());
    }

    public function test_is_modified_is_true_when_draft_body_differs(): void
    {
        $template = $this->makeTemplate(['draft_body' => '<p>Edited body</p>']);

        $this->assertTrue($template->isModified());
    }

    public function test_publish_copies_draft_columns_onto_live_columns(): void
    {
        $template = $this->makeTemplate([
            'draft_subject' => 'New subject',
            'draft_body' => '<p>New body</p>',
            'draft_default_cc' => 'ops@example.com',
        ]);

        $template->publish();
        $template->refresh();

        $this->assertSame('New subject', $template->subject);
        $this->assertSame('<p>New body</p>', $template->body);
        $this->assertSame('ops@example.com', $template->default_cc);
        $this->assertFalse($template->isModified());
    }

    public function test_reset_draft_copies_live_columns_onto_draft_columns(): void
    {
        $template = $this->makeTemplate([
            'draft_subject' => 'Unsaved edit',
            'draft_body' => '<p>Unsaved edit</p>',
        ]);

        $template->resetDraft();
        $template->refresh();

        $this->assertSame($template->subject, $template->draft_subject);
        $this->assertSame($template->body, $template->draft_body);
        $this->assertFalse($template->isModified());
    }

    public function test_cc_addresses_splits_trims_and_filters_invalid_emails(): void
    {
        $template = $this->makeTemplate(['default_cc' => 'ops@example.com, not-an-email, sales@example.com ,']);

        $this->assertSame(['ops@example.com', 'sales@example.com'], $template->ccAddresses());
    }

    public function test_cc_addresses_is_empty_array_when_column_is_null(): void
    {
        $template = $this->makeTemplate(['default_cc' => null]);

        $this->assertSame([], $template->ccAddresses());
    }

    public function test_bcc_addresses_splits_trims_and_filters_invalid_emails(): void
    {
        $template = $this->makeTemplate(['default_bcc' => 'a@example.com,b@example.com']);

        $this->assertSame(['a@example.com', 'b@example.com'], $template->bccAddresses());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/EmailTemplateTest.php`
Expected: FAIL — class `App\Models\EmailTemplate` not found (and no `email_templates` table).

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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->boolean('is_system')->default(false);
            $table->string('subject');
            $table->longText('body');
            $table->string('default_cc')->nullable();
            $table->string('default_bcc')->nullable();
            $table->string('draft_subject');
            $table->longText('draft_body');
            $table->string('draft_default_cc')->nullable();
            $table->string('draft_default_bcc')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'key', 'label', 'is_system',
        'subject', 'body', 'default_cc', 'default_bcc',
        'draft_subject', 'draft_body', 'draft_default_cc', 'draft_default_bcc',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public static function forKey(string $key): self
    {
        return static::where('key', $key)->firstOrFail();
    }

    public function isModified(): bool
    {
        return $this->draft_subject !== $this->subject
            || $this->draft_body !== $this->body
            || $this->draft_default_cc !== $this->default_cc
            || $this->draft_default_bcc !== $this->default_bcc;
    }

    public function publish(): void
    {
        $this->update([
            'subject' => $this->draft_subject,
            'body' => $this->draft_body,
            'default_cc' => $this->draft_default_cc,
            'default_bcc' => $this->draft_default_bcc,
        ]);
    }

    public function resetDraft(): void
    {
        $this->update([
            'draft_subject' => $this->subject,
            'draft_body' => $this->body,
            'draft_default_cc' => $this->default_cc,
            'draft_default_bcc' => $this->default_bcc,
        ]);
    }

    public function ccAddresses(): array
    {
        return $this->parseAddressList($this->default_cc);
    }

    public function bccAddresses(): array
    {
        return $this->parseAddressList($this->default_bcc);
    }

    private function parseAddressList(?string $list): array
    {
        if (blank($list)) {
            return [];
        }

        return collect(explode(',', $list))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->values()
            ->all();
    }
}
```

- [ ] **Step 5: Run migration and test**

Run: `php artisan test tests/Unit/EmailTemplateTest.php`
Expected: PASS (9 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_31_100000_create_email_templates_table.php app/Models/EmailTemplate.php tests/Unit/EmailTemplateTest.php
git commit -m "Add email_templates table and EmailTemplate model"
```

---

### Task 2: `EmailTemplateRenderer` service

**Files:**
- Create: `app/Services/EmailTemplateRenderer.php`
- Test: `tests/Unit/EmailTemplateRendererTest.php`

**Interfaces:**
- Consumes: `App\Models\Setting::current()` (already exists, returns an object with `site_name`).
- Produces: `EmailTemplateRenderer::render(string $template, array $tokens = []): string`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Services\EmailTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    private function renderer(): EmailTemplateRenderer
    {
        return new EmailTemplateRenderer();
    }

    public function test_substitutes_a_scalar_token(): void
    {
        $html = $this->renderer()->render('<p>Hello {{name}}</p>', ['name' => 'Asha']);

        $this->assertSame('<p>Hello Asha</p>', $html);
    }

    public function test_escapes_a_scalar_token_by_default(): void
    {
        $html = $this->renderer()->render('<p>{{name}}</p>', ['name' => '<script>alert(1)</script>']);

        $this->assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $html);
    }

    public function test_does_not_escape_a_token_ending_in_html(): void
    {
        $html = $this->renderer()->render('{{thumbnail_html}}', ['thumbnail_html' => '<img src="x.jpg">']);

        $this->assertSame('<img src="x.jpg">', $html);
    }

    public function test_leaves_an_unknown_token_as_literal_text(): void
    {
        $html = $this->renderer()->render('<p>{{unknown_token}}</p>', []);

        $this->assertSame('<p>{{unknown_token}}</p>', $html);
    }

    public function test_keeps_a_section_when_its_token_is_present(): void
    {
        $html = $this->renderer()->render(
            '<p>Before</p>{{#reason}}<p>Reason: {{reason}}</p>{{/reason}}<p>After</p>',
            ['reason' => 'Not a fit']
        );

        $this->assertSame('<p>Before</p><p>Reason: Not a fit</p><p>After</p>', $html);
    }

    public function test_drops_a_section_when_its_token_is_absent(): void
    {
        $html = $this->renderer()->render(
            '<p>Before</p>{{#reason}}<p>Reason: {{reason}}</p>{{/reason}}<p>After</p>',
            []
        );

        $this->assertSame('<p>Before</p><p>After</p>', $html);
    }

    public function test_drops_a_section_when_its_token_is_an_empty_string(): void
    {
        $html = $this->renderer()->render('{{#reason}}<p>{{reason}}</p>{{/reason}}', ['reason' => '']);

        $this->assertSame('', $html);
    }

    public function test_merges_in_the_global_site_name_token(): void
    {
        \App\Models\Setting::current()->update(['site_name' => 'ExcessKart']);

        $html = $this->renderer()->render('<p>{{site_name}}</p>', []);

        $this->assertSame('<p>ExcessKart</p>', $html);
    }

    public function test_a_caller_supplied_token_overrides_the_global_default(): void
    {
        $html = $this->renderer()->render('<p>{{site_name}}</p>', ['site_name' => 'Override']);

        $this->assertSame('<p>Override</p>', $html);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/EmailTemplateRendererTest.php`
Expected: FAIL — class `App\Services\EmailTemplateRenderer` not found.

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Services;

use App\Models\Setting;

class EmailTemplateRenderer
{
    public function render(string $template, array $tokens = []): string
    {
        $tokens = array_merge(
            ['site_name' => Setting::current()->site_name],
            $tokens,
        );

        $template = preg_replace_callback(
            '/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
            function (array $matches) use ($tokens) {
                $value = $tokens[$matches[1]] ?? null;

                return filled($value) ? $matches[2] : '';
            },
            $template
        );

        return preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            function (array $matches) use ($tokens) {
                $key = $matches[1];

                if (! array_key_exists($key, $tokens)) {
                    return $matches[0];
                }

                $value = (string) $tokens[$key];

                return str_ends_with($key, '_html') ? $value : e($value);
            },
            $template
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/EmailTemplateRendererTest.php`
Expected: PASS (9 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/EmailTemplateRenderer.php tests/Unit/EmailTemplateRendererTest.php
git commit -m "Add EmailTemplateRenderer for token/section substitution"
```

---

### Task 3: Seed the 8 system templates

**Files:**
- Create: `database/seeders/EmailTemplateSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/EmailTemplateSeederTest.php`

**Interfaces:**
- Produces: 8 `EmailTemplate` rows, `is_system = true`, with `draft_*` equal to the live columns, keyed exactly: `product_listing_live`, `quote_request_confirmation`, `quote_request_received`, `seller_activation_admin_created`, `seller_activation_self_registered`, `seller_approved`, `seller_rejected`, `staff_invitation`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_KEYS = [
        'product_listing_live',
        'quote_request_confirmation',
        'quote_request_received',
        'seller_activation_admin_created',
        'seller_activation_self_registered',
        'seller_approved',
        'seller_rejected',
        'staff_invitation',
    ];

    public function test_seeds_exactly_the_eight_expected_system_templates(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $this->assertSame(
            self::EXPECTED_KEYS,
            EmailTemplate::query()->orderBy('id')->pluck('key')->all()
        );
    }

    public function test_every_seeded_template_is_system_and_starts_unmodified(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        foreach (EmailTemplate::all() as $template) {
            $this->assertTrue($template->is_system, "{$template->key} should be is_system");
            $this->assertFalse($template->isModified(), "{$template->key} should start with draft == live");
        }
    }

    public function test_running_the_seeder_twice_does_not_duplicate_rows(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $this->assertSame(8, EmailTemplate::count());
    }

    public function test_seller_activation_templates_carry_the_two_distinct_variants(): void
    {
        $this->seed(EmailTemplateSeeder::class);

        $admin = EmailTemplate::forKey('seller_activation_admin_created');
        $self = EmailTemplate::forKey('seller_activation_self_registered');

        $this->assertStringContainsString('An administrator has created', $admin->body);
        $this->assertStringContainsString('Thanks for registering', $self->body);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailTemplateSeederTest.php`
Expected: FAIL — class `Database\Seeders\EmailTemplateSeeder` not found.

- [ ] **Step 3: Write the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $key => $definition) {
            $existing = EmailTemplate::where('key', $key)->first();

            if ($existing) {
                continue;
            }

            EmailTemplate::create(array_merge($definition, [
                'key' => $key,
                'is_system' => true,
                'draft_subject' => $definition['subject'],
                'draft_body' => $definition['body'],
                'draft_default_cc' => null,
                'draft_default_bcc' => null,
                'default_cc' => null,
                'default_bcc' => null,
            ]));
        }
    }

    /**
     * @return array<string, array{label: string, subject: string, body: string}>
     */
    private function templates(): array
    {
        return [
            'product_listing_live' => [
                'label' => 'Product Listing Live',
                'subject' => 'Your listing is now live: {{product_name}}',
                'body' => <<<'HTML'
<h1>Your listing is live</h1>
<p><strong>{{product_name}}</strong> is now published and visible to buyers on the catalog.</p>
<p><a href="{{product_url}}">View your live listing</a></p>
HTML,
            ],
            'quote_request_confirmation' => [
                'label' => 'Quote Request Confirmation (Buyer)',
                'subject' => 'Your Quote Request {{quote_number}} Has Been Received',
                'body' => <<<'HTML'
<h1>Thank you, {{first_name}}!</h1>
<p>We've received your quote request. Your reference number is:</p>
<p style="font-size: 1.5em; font-weight: bold;">{{quote_number}}</p>
<p>Please quote this number in any follow-up correspondence about this enquiry.</p>
{{#product_name}}<p><strong>Product:</strong> {{product_name}}</p>{{/product_name}}
<p>Our team will be in touch shortly.</p>
HTML,
            ],
            'quote_request_received' => [
                'label' => 'Quote Request Received (Staff Notification)',
                'subject' => 'New Quote Request from {{full_name}}',
                'body' => <<<'HTML'
<h1>New Quote Request</h1>
<p><strong>Reason:</strong> {{reason}}</p>
<p><strong>Name:</strong> {{full_name}}</p>
<p><strong>Email:</strong> {{email}}</p>
<p><strong>Phone:</strong> {{phone}}</p>
<p><strong>Company:</strong> {{company}}</p>
{{#product_name}}<p><strong>Product:</strong> {{product_name}}</p>{{product_thumbnail_html}}<p><a href="{{product_url}}">View Product Page</a></p>{{/product_name}}
{{#message_text}}<p><strong>Message:</strong></p><p>{{message_text}}</p>{{/message_text}}
<p><a href="{{admin_url}}">View in the CMS</a></p>
HTML,
            ],
            'seller_activation_admin_created' => [
                'label' => 'Seller Activation (Admin-Created)',
                'subject' => 'Activate your seller account',
                'body' => <<<'HTML'
<h1>Activate your seller account</h1>
<p>An administrator has created a seller account for {{company_name}}. Click below to set your password and activate your account.</p>
<p><a href="{{activation_url}}">Activate Account</a></p>
HTML,
            ],
            'seller_activation_self_registered' => [
                'label' => 'Seller Activation (Self-Registered)',
                'subject' => 'Activate your seller account',
                'body' => <<<'HTML'
<h1>Activate your seller account</h1>
<p>Thanks for registering {{company_name}}. Click below to verify your email address.</p>
<p><a href="{{activation_url}}">Activate Account</a></p>
HTML,
            ],
            'seller_approved' => [
                'label' => 'Seller Approved',
                'subject' => "Your seller account has been approved",
                'body' => <<<'HTML'
<h1>You're approved!</h1>
<p>Congratulations — {{company_name}}'s seller account has been approved. You can now log in and start listing products.</p>
{{#activation_url}}<p>Before you can log in, set your password: <a href="{{activation_url}}">Set Your Password</a></p>{{/activation_url}}
HTML,
            ],
            'seller_rejected' => [
                'label' => 'Seller Rejected',
                'subject' => 'Update on your seller application',
                'body' => <<<'HTML'
<h1>Update on your application</h1>
<p>Thank you for applying to become a seller. Unfortunately, we're unable to approve {{company_name}}'s application at this time.</p>
{{#rejection_reason}}<p><strong>Reason:</strong> {{rejection_reason}}</p>{{/rejection_reason}}
HTML,
            ],
            'staff_invitation' => [
                'label' => 'Staff Invitation',
                'subject' => 'Your admin panel login',
                'body' => <<<'HTML'
<h1>Welcome to the admin panel</h1>
<p>An account has been created for you, {{staff_name}}.</p>
<p>Log in at <a href="{{login_url}}">{{login_url}}</a> using this temporary password:</p>
<p><strong>{{temporary_password}}</strong></p>
<p>You'll be asked to set a new password the first time you log in.</p>
HTML,
            ],
        ];
    }
}
```

- [ ] **Step 4: Register the seeder in `DatabaseSeeder`**

In `database/seeders/DatabaseSeeder.php`, add `EmailTemplateSeeder::class` to the `$this->call([...])` array (any position — it has no dependency on the other seeders).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/EmailTemplateSeederTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add database/seeders/EmailTemplateSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/EmailTemplateSeederTest.php
git commit -m "Seed the 8 system email templates with ported copy"
```

---

### Task 4: Port `ProductListingLive`

**Files:**
- Modify: `app/Mail/ProductListingLive.php`
- Delete: `resources/views/emails/product-listing-live.blade.php`
- Test: `tests/Feature/Mail/ProductListingLiveTemplateTest.php`

**Interfaces:**
- Consumes: `EmailTemplate::forKey('product_listing_live')`, `EmailTemplateRenderer::render()`.
- Note: `tests/Feature/ProductListingLiveMailTest.php` already exists and asserts final rendered HTML content — it must keep passing unmodified as a regression check; do not edit it.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mail;

use App\Mail\ProductListingLive;
use App\Models\Category;
use App\Models\EmailTemplate;
use App\Models\Product;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingLiveTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_the_email_uses_the_published_template_subject_and_body(): void
    {
        EmailTemplate::forKey('product_listing_live')->update([
            'subject' => 'It is live: {{product_name}}',
            'body' => '<p>Custom copy for {{product_name}} at {{product_url}}</p>',
        ]);

        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Aerial Fiber Cable', 'status' => 'published']);

        $mailable = new ProductListingLive($product);

        $mailable->assertHasSubject('It is live: Aerial Fiber Cable');
        $mailable->assertSeeInHtml('Custom copy for Aerial Fiber Cable');
    }

    public function test_the_email_applies_the_templates_cc_and_bcc(): void
    {
        EmailTemplate::forKey('product_listing_live')->update([
            'default_cc' => 'ops@example.com',
            'default_bcc' => 'audit@example.com',
        ]);

        $category = Category::factory()->create(['status' => 'published']);
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'published']);

        $mailable = new ProductListingLive($product);

        $mailable->assertHasCc('ops@example.com');
        $mailable->assertHasBcc('audit@example.com');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Mail/ProductListingLiveTemplateTest.php`
Expected: FAIL — subject/body still come from the hardcoded view, not the template.

- [ ] **Step 3: Rewrite the Mailable**

```php
<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Product;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProductListingLive extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product)
    {
    }

    private function tokens(): array
    {
        return [
            'product_name' => $this->product->name,
            'product_url' => url('/products/'.$this->product->path()),
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('product_listing_live');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('product_listing_live');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send product listing live email.', [
            'product_id' => $this->product->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Delete the old view and run both test files**

```bash
rm resources/views/emails/product-listing-live.blade.php
```

Run: `php artisan test tests/Feature/Mail/ProductListingLiveTemplateTest.php tests/Feature/ProductListingLiveMailTest.php`
Expected: PASS (2 + 1 tests) — the pre-existing regression test still passes because the seeded copy renders identically to the old hardcoded text.

- [ ] **Step 5: Commit**

```bash
git add app/Mail/ProductListingLive.php tests/Feature/Mail/ProductListingLiveTemplateTest.php
git rm resources/views/emails/product-listing-live.blade.php
git commit -m "Port ProductListingLive to the email template system"
```

---

### Task 5: Port `QuoteRequestConfirmation`

**Files:**
- Modify: `app/Mail/QuoteRequestConfirmation.php`
- Delete: `resources/views/emails/quote-request-confirmation.blade.php`
- Test: `tests/Feature/Mail/QuoteRequestConfirmationTemplateTest.php`

**Interfaces:**
- Consumes: `EmailTemplate::forKey('quote_request_confirmation')`, `EmailTemplateRenderer::render()`.
- Note: `tests/Feature/QuoteRequestMailTest.php` asserts rendered content for both this and `QuoteRequestReceived` — keep passing unmodified.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mail;

use App\Mail\QuoteRequestConfirmation;
use App\Models\EmailTemplate;
use App\Models\QuoteRequest;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestConfirmationTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_the_email_uses_the_published_template_and_shows_the_product_section_when_present(): void
    {
        EmailTemplate::forKey('quote_request_confirmation')->update([
            'subject' => 'Ref {{quote_number}} received',
            'body' => '<p>Hi {{first_name}}, ref {{quote_number}}.</p>{{#product_name}}<p>About {{product_name}}</p>{{/product_name}}',
        ]);

        $product = \App\Models\Product::factory()->create(['name' => 'Aerial Fiber Cable']);
        $quoteRequest = QuoteRequest::factory()->create([
            'first_name' => 'Asha',
            'quote_number' => 'QR-1001',
            'product_id' => $product->id,
        ]);

        $mailable = new QuoteRequestConfirmation($quoteRequest);

        $mailable->assertHasSubject('Ref QR-1001 received');
        $mailable->assertSeeInHtml('Hi Asha, ref QR-1001.');
        $mailable->assertSeeInHtml('About Aerial Fiber Cable');
    }

    public function test_the_product_section_is_dropped_when_there_is_no_product(): void
    {
        EmailTemplate::forKey('quote_request_confirmation')->update([
            'body' => '<p>Before</p>{{#product_name}}<p>About {{product_name}}</p>{{/product_name}}<p>After</p>',
        ]);

        $quoteRequest = QuoteRequest::factory()->create(['product_id' => null]);

        $mailable = new QuoteRequestConfirmation($quoteRequest);

        $mailable->assertDontSeeInHtml('About');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Mail/QuoteRequestConfirmationTemplateTest.php`
Expected: FAIL — content still comes from the hardcoded view.

- [ ] **Step 3: Rewrite the Mailable**

```php
<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\QuoteRequest;
use App\Services\EmailTemplateRenderer;
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

    private function tokens(): array
    {
        return [
            'first_name' => $this->quoteRequest->first_name,
            'quote_number' => $this->quoteRequest->quote_number,
            'product_name' => $this->quoteRequest->product?->name,
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('quote_request_confirmation');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('quote_request_confirmation');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
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

- [ ] **Step 4: Delete the old view and run both test files**

```bash
rm resources/views/emails/quote-request-confirmation.blade.php
```

Run: `php artisan test tests/Feature/Mail/QuoteRequestConfirmationTemplateTest.php tests/Feature/QuoteRequestMailTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Mail/QuoteRequestConfirmation.php tests/Feature/Mail/QuoteRequestConfirmationTemplateTest.php
git rm resources/views/emails/quote-request-confirmation.blade.php
git commit -m "Port QuoteRequestConfirmation to the email template system"
```

---

### Task 6: Port `QuoteRequestReceived`

**Files:**
- Modify: `app/Mail/QuoteRequestReceived.php`
- Delete: `resources/views/emails/quote-request-received.blade.php`
- Test: `tests/Feature/Mail/QuoteRequestReceivedTemplateTest.php`

**Interfaces:**
- Consumes: `EmailTemplate::forKey('quote_request_received')`, `EmailTemplateRenderer::render()`, the existing `resources/views/components/product-thumbnail.blade.php` component (rendered to a trusted HTML string via `view(...)->render()`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mail;

use App\Mail\QuoteRequestReceived;
use App\Models\EmailTemplate;
use App\Models\Product;
use App\Models\QuoteRequest;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestReceivedTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_the_email_uses_the_published_template_subject_and_body(): void
    {
        EmailTemplate::forKey('quote_request_received')->update([
            'subject' => 'New enquiry from {{full_name}}',
            'body' => '<p>{{reason}} from {{full_name}} ({{email}}, {{phone}}, {{company}})</p>',
        ]);

        $quoteRequest = QuoteRequest::factory()->create([
            'reason' => 'General Inquiry',
            'first_name' => 'Asha', 'last_name' => 'Rao',
            'email' => 'asha@example.com', 'phone' => '9999999999', 'company' => 'Acme',
        ]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertHasSubject('New enquiry from Asha Rao');
        $mailable->assertSeeInHtml('General Inquiry from Asha Rao (asha@example.com, 9999999999, Acme)');
    }

    public function test_the_product_section_includes_the_thumbnail_and_link_when_a_product_is_set(): void
    {
        EmailTemplate::forKey('quote_request_received')->update([
            'body' => '{{#product_name}}<p>{{product_name}}</p>{{product_thumbnail_html}}<p><a href="{{product_url}}">View</a></p>{{/product_name}}',
        ]);

        $product = Product::factory()->create(['name' => 'Aerial Fiber Cable']);
        $quoteRequest = QuoteRequest::factory()->create(['product_id' => $product->id]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertSeeInHtml('Aerial Fiber Cable');
        $mailable->assertSeeInHtml('width="132"');
        $mailable->assertSeeInHtml(url('/products/'.$product->path()), escape: false);
    }

    public function test_the_message_section_is_dropped_when_there_is_no_message(): void
    {
        EmailTemplate::forKey('quote_request_received')->update([
            'body' => '<p>Before</p>{{#message_text}}<p>{{message_text}}</p>{{/message_text}}<p>After</p>',
        ]);

        $quoteRequest = QuoteRequest::factory()->create(['message' => null]);

        $mailable = new QuoteRequestReceived($quoteRequest);

        $mailable->assertSeeInHtml('<p>Before</p><p>After</p>', escape: false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Mail/QuoteRequestReceivedTemplateTest.php`
Expected: FAIL

- [ ] **Step 3: Rewrite the Mailable**

```php
<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\QuoteRequest;
use App\Services\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QuoteRequestReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public QuoteRequest $quoteRequest)
    {
    }

    private function tokens(): array
    {
        $product = $this->quoteRequest->product;

        return [
            'reason' => $this->quoteRequest->reason,
            'full_name' => $this->quoteRequest->fullName(),
            'email' => $this->quoteRequest->email,
            'phone' => $this->quoteRequest->phone,
            'company' => $this->quoteRequest->company,
            'product_name' => $product?->name,
            'product_url' => $product ? url('/products/'.$product->path()) : null,
            'product_thumbnail_html' => $product
                ? view('components.product-thumbnail', [
                    'path' => optional($product->primaryImage())->path,
                    'alt' => $product->name,
                ])->render()
                : null,
            'message_text' => $this->quoteRequest->message,
            'admin_url' => url('/admin/quote-requests/'.$this->quoteRequest->id),
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('quote_request_received');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('quote_request_received');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send quote request notification email.', [
            'quote_request_id' => $this->quoteRequest->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Delete the old view and run both test files**

```bash
rm resources/views/emails/quote-request-received.blade.php
```

Run: `php artisan test tests/Feature/Mail/QuoteRequestReceivedTemplateTest.php tests/Feature/QuoteRequestMailTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Mail/QuoteRequestReceived.php tests/Feature/Mail/QuoteRequestReceivedTemplateTest.php
git rm resources/views/emails/quote-request-received.blade.php
git commit -m "Port QuoteRequestReceived to the email template system"
```

---

### Task 7: Port `SellerActivationMail` (two keys, one Mailable)

**Files:**
- Modify: `app/Mail/SellerActivationMail.php`
- Delete: `resources/views/emails/seller-activation.blade.php`
- Test: `tests/Feature/Mail/SellerActivationMailTemplateTest.php`

**Interfaces:**
- Consumes: `EmailTemplate::forKey('seller_activation_admin_created')` or `EmailTemplate::forKey('seller_activation_self_registered')`, chosen by `$this->seller->created_by === 'admin'`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mail;

use App\Mail\SellerActivationMail;
use App\Models\EmailTemplate;
use App\Models\Seller;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerActivationMailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_an_admin_created_seller_uses_the_admin_created_template(): void
    {
        EmailTemplate::forKey('seller_activation_admin_created')->update([
            'body' => '<p>Admin-created copy for {{company_name}}: {{activation_url}}</p>',
        ]);

        $seller = Seller::factory()->create(['created_by' => 'admin', 'company_name' => 'Acme Co']);

        $mailable = new SellerActivationMail($seller);

        $mailable->assertSeeInHtml('Admin-created copy for Acme Co');
    }

    public function test_a_self_registered_seller_uses_the_self_registered_template(): void
    {
        EmailTemplate::forKey('seller_activation_self_registered')->update([
            'body' => '<p>Self-registered copy for {{company_name}}: {{activation_url}}</p>',
        ]);

        $seller = Seller::factory()->create(['created_by' => 'self', 'company_name' => 'Acme Co']);

        $mailable = new SellerActivationMail($seller);

        $mailable->assertSeeInHtml('Self-registered copy for Acme Co');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Mail/SellerActivationMailTemplateTest.php`
Expected: FAIL

- [ ] **Step 3: Rewrite the Mailable**

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
use Illuminate\Support\Facades\URL;

class SellerActivationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller)
    {
    }

    private function templateKey(): string
    {
        return $this->seller->created_by === 'admin'
            ? 'seller_activation_admin_created'
            : 'seller_activation_self_registered';
    }

    private function tokens(): array
    {
        return [
            'company_name' => $this->seller->company_name,
            'activation_url' => URL::temporarySignedRoute('seller.activate', now()->addDays(7), ['seller' => $this->seller->id]),
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey($this->templateKey());

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey($this->templateKey());

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send seller activation email.', [
            'seller_id' => $this->seller->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Delete the old view and run tests**

```bash
rm resources/views/emails/seller-activation.blade.php
```

Run: `php artisan test tests/Feature/Mail/SellerActivationMailTemplateTest.php`
Expected: PASS. Also run: `php artisan test --filter=SellerRegistrationTest` and `--filter=SellerAdminCreationTest` (both assert `Mail::assertQueued(SellerActivationMail::class, ...)` without inspecting body — confirm they still pass unmodified).

- [ ] **Step 5: Commit**

```bash
git add app/Mail/SellerActivationMail.php tests/Feature/Mail/SellerActivationMailTemplateTest.php
git rm resources/views/emails/seller-activation.blade.php
git commit -m "Port SellerActivationMail to the email template system (two keys)"
```

---

### Task 8: Port `SellerApproved`

**Files:**
- Modify: `app/Mail/SellerApproved.php`
- Delete: `resources/views/emails/seller-approved.blade.php`
- Test: `tests/Feature/Mail/SellerApprovedTemplateTest.php`

**Interfaces:**
- Consumes: `EmailTemplate::forKey('seller_approved')`, `EmailTemplateRenderer::render()`.
- Note: `SellerResourceTest::test_approving_a_pending_seller_sets_status_and_sends_email` asserts `Mail::assertQueued(SellerApproved::class, ...)` without inspecting body — must keep passing.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mail;

use App\Mail\SellerApproved;
use App\Models\EmailTemplate;
use App\Models\Seller;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerApprovedTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_shows_the_activation_link_section_when_one_is_given(): void
    {
        EmailTemplate::forKey('seller_approved')->update([
            'body' => '<p>{{company_name}} approved.</p>{{#activation_url}}<p><a href="{{activation_url}}">Set Password</a></p>{{/activation_url}}',
        ]);

        $seller = Seller::factory()->create(['company_name' => 'Acme Co']);

        $mailable = new SellerApproved($seller, 'https://example.test/activate/1');

        $mailable->assertSeeInHtml('Acme Co approved.');
        $mailable->assertSeeInHtml('https://example.test/activate/1', escape: false);
    }

    public function test_drops_the_activation_link_section_when_none_is_given(): void
    {
        EmailTemplate::forKey('seller_approved')->update([
            'body' => '<p>Before</p>{{#activation_url}}<p>Set password link</p>{{/activation_url}}<p>After</p>',
        ]);

        $seller = Seller::factory()->create();

        $mailable = new SellerApproved($seller, null);

        $mailable->assertSeeInHtml('<p>Before</p><p>After</p>', escape: false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Mail/SellerApprovedTemplateTest.php`
Expected: FAIL

- [ ] **Step 3: Rewrite the Mailable**

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

class SellerApproved extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller, public ?string $activationUrl = null)
    {
    }

    private function tokens(): array
    {
        return [
            'company_name' => $this->seller->company_name,
            'activation_url' => $this->activationUrl,
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('seller_approved');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('seller_approved');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
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

- [ ] **Step 4: Delete the old view and run tests**

```bash
rm resources/views/emails/seller-approved.blade.php
```

Run: `php artisan test tests/Feature/Mail/SellerApprovedTemplateTest.php --filter=SellerResourceTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Mail/SellerApproved.php tests/Feature/Mail/SellerApprovedTemplateTest.php
git rm resources/views/emails/seller-approved.blade.php
git commit -m "Port SellerApproved to the email template system"
```

---

### Task 9: Port `SellerRejected`

**Files:**
- Modify: `app/Mail/SellerRejected.php`
- Delete: `resources/views/emails/seller-rejected.blade.php`
- Test: `tests/Feature/Mail/SellerRejectedTemplateTest.php`

**Interfaces:**
- Consumes: `EmailTemplate::forKey('seller_rejected')`, `EmailTemplateRenderer::render()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mail;

use App\Mail\SellerRejected;
use App\Models\EmailTemplate;
use App\Models\Seller;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerRejectedTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_shows_the_reason_section_when_one_is_set(): void
    {
        EmailTemplate::forKey('seller_rejected')->update([
            'body' => '<p>{{company_name}} rejected.</p>{{#rejection_reason}}<p>Reason: {{rejection_reason}}</p>{{/rejection_reason}}',
        ]);

        $seller = Seller::factory()->create(['company_name' => 'Acme Co', 'rejection_reason' => 'Documents did not match.']);

        $mailable = new SellerRejected($seller);

        $mailable->assertSeeInHtml('Acme Co rejected.');
        $mailable->assertSeeInHtml('Reason: Documents did not match.');
    }

    public function test_drops_the_reason_section_when_none_is_set(): void
    {
        EmailTemplate::forKey('seller_rejected')->update([
            'body' => '<p>Before</p>{{#rejection_reason}}<p>Reason</p>{{/rejection_reason}}<p>After</p>',
        ]);

        $seller = Seller::factory()->create(['rejection_reason' => null]);

        $mailable = new SellerRejected($seller);

        $mailable->assertSeeInHtml('<p>Before</p><p>After</p>', escape: false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Mail/SellerRejectedTemplateTest.php`
Expected: FAIL

- [ ] **Step 3: Rewrite the Mailable**

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

class SellerRejected extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Seller $seller)
    {
    }

    private function tokens(): array
    {
        return [
            'company_name' => $this->seller->company_name,
            'rejection_reason' => $this->seller->rejection_reason,
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('seller_rejected');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('seller_rejected');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send seller rejection email.', [
            'seller_id' => $this->seller->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Delete the old view and run tests**

```bash
rm resources/views/emails/seller-rejected.blade.php
```

Run: `php artisan test tests/Feature/Mail/SellerRejectedTemplateTest.php --filter=SellerResourceTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Mail/SellerRejected.php tests/Feature/Mail/SellerRejectedTemplateTest.php
git rm resources/views/emails/seller-rejected.blade.php
git commit -m "Port SellerRejected to the email template system"
```

---

### Task 10: Port `StaffInvitation`

**Files:**
- Modify: `app/Mail/StaffInvitation.php`
- Delete: `resources/views/emails/staff-invitation.blade.php`
- Test: `tests/Feature/Mail/StaffInvitationTemplateTest.php`

**Interfaces:**
- Consumes: `EmailTemplate::forKey('staff_invitation')`, `EmailTemplateRenderer::render()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mail;

use App\Mail\StaffInvitation;
use App\Models\EmailTemplate;
use App\Models\Staff;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffInvitationTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_the_email_uses_the_published_template_content(): void
    {
        EmailTemplate::forKey('staff_invitation')->update([
            'body' => '<p>Welcome {{staff_name}}. Login: {{login_url}}. Temp password: {{temporary_password}}.</p>',
        ]);

        $staff = Staff::factory()->create(['name' => 'Priya']);

        $mailable = new StaffInvitation($staff, 'Temp1234!', 'https://example.test/admin/login');

        $mailable->assertSeeInHtml('Welcome Priya. Login: https://example.test/admin/login. Temp password: Temp1234!.');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Mail/StaffInvitationTemplateTest.php`
Expected: FAIL

- [ ] **Step 3: Rewrite the Mailable**

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

class StaffInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Staff $staff, public string $temporaryPassword, public string $loginUrl)
    {
    }

    private function tokens(): array
    {
        return [
            'staff_name' => $this->staff->name,
            'login_url' => $this->loginUrl,
            'temporary_password' => $this->temporaryPassword,
        ];
    }

    public function envelope(): Envelope
    {
        $template = EmailTemplate::forKey('staff_invitation');

        return new Envelope(
            subject: app(EmailTemplateRenderer::class)->render($template->subject, $this->tokens()),
            cc: $template->ccAddresses(),
            bcc: $template->bccAddresses(),
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('staff_invitation');

        return new Content(
            htmlString: app(EmailTemplateRenderer::class)->render($template->body, $this->tokens()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send staff invitation email.', [
            'staff_id' => $this->staff->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Delete the old view and run tests**

```bash
rm resources/views/emails/staff-invitation.blade.php
```

Run: `php artisan test tests/Feature/Mail/StaffInvitationTemplateTest.php --filter=StaffResourceTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Mail/StaffInvitation.php tests/Feature/Mail/StaffInvitationTemplateTest.php
git rm resources/views/emails/staff-invitation.blade.php
git commit -m "Port StaffInvitation to the email template system"
```

---

### Task 11: Full-suite regression check

**Files:** none (verification-only task)

- [ ] **Step 1: Run the entire test suite**

Run: `php artisan test`
Expected: All tests pass. This confirms Tasks 4–10 didn't regress anything elsewhere (RFQ flows, seller approval/rejection flows, staff invitation flow, product publish flow).

- [ ] **Step 2: Confirm no orphaned view files remain**

Run: `ls resources/views/emails`
Expected: only `product-edit-ready-for-acceptance.blade.php` and `seller-import-stuck.blade.php` remain (the two hardcoded, out-of-scope emails).

- [ ] **Step 3: Commit (only if Step 2 required a fix)**

If any leftover file needed deleting: `git add -u && git commit -m "Remove leftover templated email views"`. Otherwise, no commit — this task is verification-only.

---

### Task 12: RBAC — `email_templates` permission area

**Files:**
- Modify: `database/seeders/RoleSeeder.php`
- Create: `app/Policies/EmailTemplatePolicy.php`
- Test: `tests/Feature/EmailTemplatePolicyTest.php`
- Modify: `tests/Feature/RoleSeederTest.php` (update its exact-list assertions to include `email_templates` — see Step 6)

**Interfaces:**
- Produces: `email_templates.read` / `.write` / `.full` permissions; `EmailTemplatePolicy` with `viewAny`/`view`/`create`/`update`/`delete` following `PagePolicy`'s exact shape, plus `delete` additionally requiring `! $record->is_system`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Staff;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplatePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_admin_can_view_and_update_email_templates(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $template = EmailTemplate::first();

        $this->assertTrue($admin->can('viewAny', EmailTemplate::class));
        $this->assertTrue($admin->can('update', $template));
    }

    public function test_content_editor_can_view_and_update_email_templates(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $template = EmailTemplate::first();

        $this->assertTrue($editor->can('viewAny', EmailTemplate::class));
        $this->assertTrue($editor->can('update', $template));
    }

    public function test_sales_cannot_view_email_templates(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $this->assertFalse($sales->can('viewAny', EmailTemplate::class));
    }

    public function test_a_system_template_cannot_be_deleted_even_by_admin(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $template = EmailTemplate::first(); // is_system = true

        $this->assertFalse($admin->can('delete', $template));
    }

    public function test_a_custom_template_can_be_deleted_by_admin(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $template = EmailTemplate::create([
            'key' => 'custom_template', 'label' => 'Custom', 'is_system' => false,
            'subject' => 's', 'body' => 'b', 'draft_subject' => 's', 'draft_body' => 'b',
        ]);

        $this->assertTrue($admin->can('delete', $template));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailTemplatePolicyTest.php`
Expected: FAIL — no policy registered for `EmailTemplate`, and `email_templates.*` permissions don't exist yet.

- [ ] **Step 3: Add the permission area to `RoleSeeder`**

In `database/seeders/RoleSeeder.php`:

```php
private const AREAS = ['dashboard', 'staff', 'roles', 'categories', 'products', 'sellers', 'quote_requests', 'pages', 'nav_items', 'settings', 'audit_logs', 'email_templates'];
```

And in `ROLE_MATRIX`, add `'email_templates' => 'full'` to the `admin` array, `'email_templates' => 'full'` to the `content_editor` array, and `'email_templates' => null` to the `sales` array (matching each role's existing `pages` entry exactly).

- [ ] **Step 4: Write the policy**

```php
<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\Staff;

class EmailTemplatePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['email_templates.read', 'email_templates.write', 'email_templates.full']);
    }

    public function view(Staff $staff, EmailTemplate $emailTemplate): bool
    {
        return $staff->hasAnyPermission(['email_templates.read', 'email_templates.write', 'email_templates.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['email_templates.write', 'email_templates.full']);
    }

    public function update(Staff $staff, EmailTemplate $emailTemplate): bool
    {
        return $staff->hasAnyPermission(['email_templates.write', 'email_templates.full']);
    }

    public function delete(Staff $staff, EmailTemplate $emailTemplate): bool
    {
        return ! $emailTemplate->is_system && $staff->hasPermissionTo('email_templates.full');
    }
}
```

Laravel auto-discovers this policy by naming convention (`App\Models\EmailTemplate` → `App\Policies\EmailTemplatePolicy`). Confirmed against this codebase: `PagePolicy` (same `App\Models`/`App\Policies` convention) has no explicit registration anywhere in `app/Providers`, while `RolePolicy` — for `Spatie\Permission\Models\Role`, which doesn't live under `App\Models` so the convention can't find it — is explicitly wired via `Gate::policy(Role::class, RolePolicy::class)` in `AppServiceProvider::boot()`. `EmailTemplate` follows the `Page` case, not the `Role` case, so no registration line is needed here.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/EmailTemplatePolicyTest.php`
Expected: PASS (5 tests)

- [ ] **Step 6: Update `RoleSeederTest`'s exact-list assertions**

Run: `php artisan test tests/Feature/RoleSeederTest.php`
Expected (before editing the test): FAIL — it asserts exact permission counts/lists that don't yet include `email_templates`.

`tests/Feature/RoleSeederTest.php` has 4 tests asserting exact, sorted permission lists (12 areas × 3 tiers = 36 total permissions now, up from 33). Update each expected array:

```php
    public function test_it_creates_21_staff_guard_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(36, Permission::where('guard_name', 'staff')->count());
    }

    public function test_admin_role_gets_full_permission_in_every_area(): void
    {
        $this->seed(RoleSeeder::class);

        $permissions = Role::findByName('admin', 'staff')->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'audit_logs.full', 'categories.full', 'dashboard.full', 'email_templates.full', 'nav_items.full',
            'pages.full', 'products.full', 'quote_requests.full', 'roles.full', 'sellers.full', 'settings.full',
            'staff.full',
        ], $permissions);
    }

    public function test_content_editor_role_matches_the_migration_matrix(): void
    {
        $this->seed(RoleSeeder::class);

        $permissions = Role::findByName('content_editor', 'staff')->permissions->pluck('name')->sort()->values()->all();

        $this->assertSame([
            'categories.full', 'dashboard.read', 'email_templates.full', 'nav_items.full', 'pages.full', 'products.write',
        ], $permissions);
    }
```

(`test_sales_role_matches_the_migration_matrix` is unchanged — `sales` gets no `email_templates` permission.)

Run: `php artisan test tests/Feature/RoleSeederTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/seeders/RoleSeeder.php app/Policies/EmailTemplatePolicy.php tests/Feature/EmailTemplatePolicyTest.php tests/Feature/RoleSeederTest.php
git commit -m "Add email_templates RBAC area and policy"
```

---

### Task 13: `EmailTemplateResource` — list, edit form, Publish/Reset Draft

**Files:**
- Create: `app/Filament/Resources/EmailTemplateResource.php`
- Create: `app/Filament/Resources/EmailTemplateResource/Pages/ListEmailTemplates.php`
- Create: `app/Filament/Resources/EmailTemplateResource/Pages/EditEmailTemplate.php`
- Test: `tests/Feature/EmailTemplateResourceTest.php`

**Interfaces:**
- Consumes: `EmailTemplate` model (Task 1), `EmailTemplatePolicy` (Task 12).
- Produces: `/admin/email-templates` list + edit pages; `Action::make('publish')`, `Action::make('resetDraft')` on the edit page.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\ListEmailTemplates;
use App\Models\EmailTemplate;
use App\Models\Staff;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplateResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    private function actingAsAdmin(): Staff
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        return $admin;
    }

    public function test_sales_gets_a_403_visiting_the_email_templates_list(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $response = $this->get('/admin/email-templates');

        $response->assertForbidden();
    }

    public function test_a_system_templates_key_and_label_are_locked_in_the_edit_form(): void
    {
        $this->actingAsAdmin();

        $template = EmailTemplate::forKey('staff_invitation');

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->assertFormFieldIsDisabled('key')
            ->assertFormFieldIsDisabled('label');
    }

    public function test_editing_the_draft_fields_does_not_change_the_live_columns(): void
    {
        $this->actingAsAdmin();

        $template = EmailTemplate::forKey('staff_invitation');

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm([
                'draft_subject' => 'A brand new subject',
                'draft_body' => '<p>New body</p>',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $template->refresh();
        $this->assertSame('A brand new subject', $template->draft_subject);
        $this->assertNotSame('A brand new subject', $template->subject);
    }

    public function test_publish_action_copies_draft_onto_live_and_is_hidden_when_unmodified(): void
    {
        $this->actingAsAdmin();

        $template = EmailTemplate::forKey('staff_invitation');
        $template->update(['draft_subject' => 'Edited subject']);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->assertActionVisible('publish')
            ->callAction('publish');

        $this->assertSame('Edited subject', $template->fresh()->subject);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->assertActionHidden('publish');
    }

    public function test_reset_draft_action_discards_unsaved_draft_changes(): void
    {
        $this->actingAsAdmin();

        $template = EmailTemplate::forKey('staff_invitation');
        $originalSubject = $template->subject;
        $template->update(['draft_subject' => 'Unsaved edit']);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('resetDraft');

        $this->assertSame($originalSubject, $template->fresh()->draft_subject);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailTemplateResourceTest.php`
Expected: FAIL — resource/pages don't exist.

- [ ] **Step 3: Write the resource**

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailTemplateResource\Pages;
use App\Models\EmailTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?int $navigationSort = 9;

    /**
     * @return array<string, string>
     */
    public static function tokenHelpFor(string $key): string
    {
        $tokens = [
            'product_listing_live' => '{{product_name}}, {{product_url}}',
            'quote_request_confirmation' => '{{first_name}}, {{quote_number}}, optional section {{#product_name}}...{{/product_name}}',
            'quote_request_received' => '{{reason}}, {{full_name}}, {{email}}, {{phone}}, {{company}}, {{admin_url}}, optional sections {{#product_name}}...{{/product_name}} and {{#message_text}}...{{/message_text}}',
            'seller_activation_admin_created' => '{{company_name}}, {{activation_url}}',
            'seller_activation_self_registered' => '{{company_name}}, {{activation_url}}',
            'seller_approved' => '{{company_name}}, optional section {{#activation_url}}...{{/activation_url}}',
            'seller_rejected' => '{{company_name}}, optional section {{#rejection_reason}}...{{/rejection_reason}}',
            'staff_invitation' => '{{staff_name}}, {{login_url}}, {{temporary_password}}',
        ];

        return $tokens[$key] ?? 'No key-specific tokens (custom template) — {{site_name}} is always available.';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')
                ->required()
                ->disabled(fn (?EmailTemplate $record) => $record?->is_system === true)
                ->afterStateUpdated(fn ($state, callable $set, ?EmailTemplate $record) => $record === null ? $set('key', \Illuminate\Support\Str::slug($state, '_')) : null)
                ->live(onBlur: true),
            TextInput::make('key')
                ->required()
                ->disabled(fn (?EmailTemplate $record) => $record?->is_system === true || $record !== null)
                ->rule(fn (?EmailTemplate $record) => Rule::unique('email_templates', 'key')->ignore($record?->id)),
            Placeholder::make('tokens_help')
                ->label('Available tokens')
                ->content(fn (?EmailTemplate $record) => $record ? static::tokenHelpFor($record->key) : 'Save first to see available tokens.'),
            TextInput::make('draft_subject')->label('Subject (draft)')->required(),
            RichEditor::make('draft_body')->label('Body (draft)')->required(),
            TextInput::make('draft_default_cc')->label('Default CC')->helperText('Comma-separated email addresses.'),
            TextInput::make('draft_default_bcc')->label('Default BCC')->helperText('Comma-separated email addresses.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('key'),
                IconColumn::make('is_system')->boolean()->label('System'),
                TextColumn::make('subject')->limit(40),
                IconColumn::make('modified')
                    ->label('Modified')
                    ->state(fn (EmailTemplate $record) => $record->isModified())
                    ->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Write the list page**

No "New Template" button yet — that's wired up in Task 14 once `CreateEmailTemplate` exists, to avoid the page linking to a route that doesn't exist.

```php
<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    protected static string $resource = EmailTemplateResource::class;
}
```

- [ ] **Step 5: Write the edit page with Publish/Reset Draft actions**

```php
<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use App\Models\EmailTemplate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->visible(fn (EmailTemplate $record) => $record->isModified())
                ->requiresConfirmation()
                ->action(function (EmailTemplate $record) {
                    $record->publish();

                    Notification::make()->title('Template published')->success()->send();
                }),
            Action::make('resetDraft')
                ->label('Reset Draft')
                ->visible(fn (EmailTemplate $record) => $record->isModified())
                ->requiresConfirmation()
                ->action(function (EmailTemplate $record) {
                    $record->resetDraft();

                    Notification::make()->title('Draft reset to the published version')->success()->send();
                }),
            DeleteAction::make()
                ->visible(fn (EmailTemplate $record) => ! $record->is_system),
        ];
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/EmailTemplateResourceTest.php`
Expected: PASS (5 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/EmailTemplateResource.php app/Filament/Resources/EmailTemplateResource/Pages/ListEmailTemplates.php app/Filament/Resources/EmailTemplateResource/Pages/EditEmailTemplate.php tests/Feature/EmailTemplateResourceTest.php
git commit -m "Add EmailTemplateResource with draft editing, Publish, and Reset Draft"
```

---

### Task 14: Custom template Create/Delete

**Files:**
- Create: `app/Filament/Resources/EmailTemplateResource/Pages/CreateEmailTemplate.php`
- Modify: `app/Filament/Resources/EmailTemplateResource.php` (add `'create'` to `getPages()`)
- Modify: `app/Filament/Resources/EmailTemplateResource/Pages/ListEmailTemplates.php` (add the "New Template" header action)
- Test: `tests/Feature/EmailTemplateCustomCreationTest.php`

**Interfaces:**
- Consumes: `EmailTemplateResource::form()` (Task 13) — the `label`→`key` auto-slug `afterStateUpdated` hook already only fires when `$record === null` (create context), so no form changes needed here beyond wiring the page.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailTemplateResource\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Models\EmailTemplate;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplateCustomCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function actingAsAdmin(): Staff
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        return $admin;
    }

    public function test_creating_a_custom_template_auto_generates_the_key_from_the_label(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateEmailTemplate::class)
            ->fillForm([
                'label' => 'Seasonal Promo Email',
                'draft_subject' => 'Big sale!',
                'draft_body' => '<p>Save today.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = EmailTemplate::where('label', 'Seasonal Promo Email')->firstOrFail();
        $this->assertSame('seasonal_promo_email', $template->key);
        $this->assertFalse($template->is_system);
        $this->assertSame('Big sale!', $template->subject);
    }

    public function test_a_custom_template_can_be_deleted(): void
    {
        $this->actingAsAdmin();

        $template = EmailTemplate::create([
            'key' => 'custom_one', 'label' => 'Custom One', 'is_system' => false,
            'subject' => 's', 'body' => 'b', 'draft_subject' => 's', 'draft_body' => 'b',
        ]);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->assertActionVisible('delete')
            ->callAction('delete');

        $this->assertNull($template->fresh());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailTemplateCustomCreationTest.php`
Expected: FAIL — `CreateEmailTemplate` page doesn't exist.

- [ ] **Step 3: Write the create page**

```php
<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_system'] = false;
        $data['subject'] = $data['draft_subject'];
        $data['body'] = $data['draft_body'];
        $data['default_cc'] = $data['draft_default_cc'] ?? null;
        $data['default_bcc'] = $data['draft_default_bcc'] ?? null;

        return $data;
    }
}
```

(A brand-new custom template starts already "published" — draft equals live from creation, since there's no prior live version to protect. `EmailTemplate::isModified()` will correctly report `false` immediately after creation, so no stray Publish button appears on a fresh record.)

- [ ] **Step 4: Wire up the create route and the list page's "New Template" button**

In `app/Filament/Resources/EmailTemplateResource.php`, add the create route to `getPages()`:

```php
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailTemplates::route('/'),
            'create' => Pages\CreateEmailTemplate::route('/create'),
            'edit' => Pages\EditEmailTemplate::route('/{record}/edit'),
        ];
    }
```

In `app/Filament/Resources/EmailTemplateResource/Pages/ListEmailTemplates.php`, add the header action:

```php
<?php

namespace App\Filament\Resources\EmailTemplateResource\Pages;

use App\Filament\Resources\EmailTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/EmailTemplateCustomCreationTest.php tests/Feature/EmailTemplateResourceTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/EmailTemplateResource/Pages/CreateEmailTemplate.php app/Filament/Resources/EmailTemplateResource/Pages/ListEmailTemplates.php app/Filament/Resources/EmailTemplateResource.php tests/Feature/EmailTemplateCustomCreationTest.php
git commit -m "Add custom email template creation and deletion"
```

---

### Task 15: Preview action

**Files:**
- Modify: `app/Filament/Resources/EmailTemplateResource.php` (add a `sampleTokensFor()` helper)
- Modify: `app/Filament/Resources/EmailTemplateResource/Pages/EditEmailTemplate.php` (add the `preview` action)
- Test: `tests/Feature/EmailTemplatePreviewTest.php`

**Interfaces:**
- Consumes: `EmailTemplateRenderer::render()` (Task 2).
- Produces: `EmailTemplateResource::sampleTokensFor(string $key): array`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Models\EmailTemplate;
use App\Models\Staff;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');
    }

    public function test_preview_renders_the_draft_with_sample_data_and_sends_no_mail(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $template = EmailTemplate::forKey('staff_invitation');
        $template->update(['draft_body' => '<p>Hello {{staff_name}}</p>']);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->mountAction('preview')
            ->assertActionMounted('preview')
            ->assertSee('Hello Priya');

        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_preview_leaves_a_key_specific_unknown_token_untouched_for_custom_templates(): void
    {
        $template = EmailTemplate::create([
            'key' => 'custom_one', 'label' => 'Custom One', 'is_system' => false,
            'subject' => 's', 'body' => 'b',
            'draft_subject' => 's', 'draft_body' => '<p>{{not_a_real_token}}</p>',
        ]);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->mountAction('preview')
            ->assertActionMounted('preview')
            ->assertSee('{{not_a_real_token}}');
    }
}
```

`mountAction` (not `callAction`) is the correct verb here: this action has no `->action()` submit handler, only `modalContent()` — it exists purely to open a modal, so the test needs to simulate *opening* the modal and inspect what it rendered, not simulate submitting a handler that doesn't exist.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/EmailTemplatePreviewTest.php`
Expected: FAIL — `preview` action doesn't exist.

- [ ] **Step 3: Add `sampleTokensFor()` to the resource**

In `app/Filament/Resources/EmailTemplateResource.php`, add:

```php
/**
 * @return array<string, string>
 */
public static function sampleTokensFor(string $key): array
{
    $samples = [
        'product_listing_live' => ['product_name' => 'Aerial Fiber Cable', 'product_url' => url('/products/sample')],
        'quote_request_confirmation' => ['first_name' => 'Asha', 'quote_number' => 'QR-1001', 'product_name' => 'Aerial Fiber Cable'],
        'quote_request_received' => [
            'reason' => 'General Inquiry', 'full_name' => 'Asha Rao', 'email' => 'asha@example.com',
            'phone' => '9999999999', 'company' => 'Acme Co', 'admin_url' => url('/admin/quote-requests/1'),
            'product_name' => 'Aerial Fiber Cable', 'product_url' => url('/products/sample'),
            'product_thumbnail_html' => '<img src="https://via.placeholder.com/132" width="132" height="132" alt="sample">',
            'message_text' => 'Please share pricing for 500 meters.',
        ],
        'seller_activation_admin_created' => ['company_name' => 'Acme Co', 'activation_url' => url('/seller/activate/1?signature=sample')],
        'seller_activation_self_registered' => ['company_name' => 'Acme Co', 'activation_url' => url('/seller/activate/1?signature=sample')],
        'seller_approved' => ['company_name' => 'Acme Co', 'activation_url' => url('/seller/activate/1?signature=sample')],
        'seller_rejected' => ['company_name' => 'Acme Co', 'rejection_reason' => 'Documents did not match business name.'],
        'staff_invitation' => ['staff_name' => 'Priya', 'login_url' => url('/admin/login'), 'temporary_password' => 'Temp1234!'],
    ];

    return $samples[$key] ?? [];
}
```

- [ ] **Step 4: Add the preview action**

In `app/Filament/Resources/EmailTemplateResource/Pages/EditEmailTemplate.php`, add to the `getHeaderActions()` array (before `publish`):

```php
Action::make('preview')
    ->modalHeading('Preview')
    ->modalSubmitAction(false)
    ->modalCancelActionLabel('Close')
    ->modalContent(function (EmailTemplate $record) {
        $tokens = \App\Filament\Resources\EmailTemplateResource::sampleTokensFor($record->key);
        $renderer = app(\App\Services\EmailTemplateRenderer::class);

        return view('filament.email-template-preview', [
            'subject' => $renderer->render($record->draft_subject, $tokens),
            'body' => $renderer->render($record->draft_body, $tokens),
        ]);
    }),
```

Create `resources/views/filament/email-template-preview.blade.php`:

```blade
<div class="space-y-4">
    <div>
        <div class="text-sm font-semibold text-gray-500">Subject</div>
        <div>{{ $subject }}</div>
    </div>
    <div class="border rounded-lg p-4">
        {!! $body !!}
    </div>
</div>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/EmailTemplatePreviewTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the full suite one final time**

Run: `php artisan test`
Expected: All tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/EmailTemplateResource.php app/Filament/Resources/EmailTemplateResource/Pages/EditEmailTemplate.php resources/views/filament/email-template-preview.blade.php tests/Feature/EmailTemplatePreviewTest.php
git commit -m "Add Preview action to EmailTemplateResource"
```
