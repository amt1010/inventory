# Clerk Google Sign-In Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add "Sign in with Google" (via Clerk) as an alternative to email/password on buyer register/login and seller register/panel-login, without changing the existing password flows.

**Architecture:** Clerk is an identity broker only. Clerk's vanilla JS SDK runs the Google OAuth handshake in the browser; the resulting session token is POSTed to a small Laravel backend that verifies it against Clerk's JWKS (`firebase/php-jwt`), fetches the verified email/name from Clerk's Backend API, then logs into the app's existing `web`/`seller` guards exactly as the password flows do today. No guard, Policy, or Filament code changes beyond one login-page render hook.

**Tech Stack:** Laravel 11 / PHP 8.2, `firebase/php-jwt` (new), Clerk vanilla JS SDK (CDN, no build step), existing Bootstrap/Blade public site, Filament v3 seller panel.

**Spec:** `docs/superpowers/specs/2026-08-30-clerk-google-auth-design.md`

## Global Constraints

- No Apple Sign-In — Google only, per the spec.
- Staff (`/admin`) is untouched — Clerk is scoped to buyer (`web` guard) and seller (`seller` guard) auth only.
- The existing email/password paths (`RegistrationController`, `Seller\RegistrationController`, `SessionController`, Filament's own password login) must not change behavior for anyone not using Clerk.
- New POST endpoints get `throttle:6,1`, matching `/register` and `/login`.
- Work happens on a new branch, `feature/clerk-google-signin`, off `master`.
- Every migration is additive/nullable — never breaks an existing row.
- Tests run against SQLite per `phpunit.xml`; verify any migration also applies cleanly to the dev MySQL database with `php artisan migrate` (never `migrate:fresh`) before considering a schema task done.

---

## Task 1: Branch, dependency, and Clerk config scaffolding

**Files:**
- Create: none (dependency + config only)
- Modify: `composer.json`, `config/services.php`, `.env.example`

**Interfaces:**
- Produces: `config('services.clerk.publishable_key')`, `config('services.clerk.secret_key')`, `config('services.clerk.frontend_api')` — consumed by every later task.

- [ ] **Step 1: Create the feature branch**

```bash
git checkout master
git pull
git checkout -b feature/clerk-google-signin
```

- [ ] **Step 2: Add the JWT verification dependency**

```bash
composer require firebase/php-jwt:^6.10
```

- [ ] **Step 3: Add the Clerk config block**

Edit `config/services.php`, adding after the existing `'recaptcha'` block:

```php
    'clerk' => [
        'publishable_key' => env('CLERK_PUBLISHABLE_KEY'),
        'secret_key' => env('CLERK_SECRET_KEY'),
        'frontend_api' => env('CLERK_FRONTEND_API'),
    ],
```

- [ ] **Step 4: Add the env vars**

Edit `.env.example`, adding after the existing `RECAPTCHA_SECRET_KEY=` line:

```
# Clerk (Google sign-in for buyers/sellers — see CLAUDE.md local-dev section).
# CLERK_FRONTEND_API is the bare host from your Clerk dashboard's API Keys
# page, e.g. your-app-name.clerk.accounts.dev — no https:// prefix.
CLERK_PUBLISHABLE_KEY=
CLERK_SECRET_KEY=
CLERK_FRONTEND_API=
```

- [ ] **Step 5: Verify nothing broke**

Run: `php artisan test`
Expected: PASS (no test yet references Clerk — this just confirms config loads without error).

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock config/services.php .env.example
git commit -m "Add Clerk dependency and config scaffolding"
```

---

## Task 2: Schema — clerk_user_id and nullable password on users and sellers

**Files:**
- Create: `database/migrations/2026_08_30_120000_add_clerk_user_id_to_users_table.php`
- Create: `database/migrations/2026_08_30_120100_add_clerk_fields_to_sellers_table.php`
- Modify: `app/Models/User.php`, `app/Models/Seller.php`

**Interfaces:**
- Produces: `users.clerk_user_id` (nullable unique string), `sellers.clerk_user_id` (nullable unique string), both tables' `password` now nullable. `User::$fillable` and `Seller::$fillable` both include `clerk_user_id`.

- [ ] **Step 1: Write the users migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('clerk_user_id')->nullable()->unique()->after('email');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['clerk_user_id']);
            $table->dropColumn('clerk_user_id');
            $table->string('password')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 2: Write the sellers migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('clerk_user_id')->nullable()->unique()->after('email');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropUnique(['clerk_user_id']);
            $table->dropColumn('clerk_user_id');
            $table->string('password')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 3: Apply to the dev database and confirm it's clean**

Run: `php artisan migrate`
Expected: both new migrations run with no errors, existing rows untouched.

- [ ] **Step 4: Add `clerk_user_id` to both models' fillable arrays**

In `app/Models/User.php`, change:

```php
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
```

to:

```php
    protected $fillable = [
        'name',
        'email',
        'password',
        'clerk_user_id',
    ];
```

In `app/Models/Seller.php`, change:

```php
    protected $fillable = [
        'company_name', 'contact_person', 'phone', 'email', 'password',
        'business_address', 'gst_number', 'status', 'created_by',
        'rejection_reason', 'email_verified_at', 'approved_at', 'approved_by',
        'seller_code', 'manufacturing_activity', 'availability_hours', 'password_set_at',
    ];
```

to:

```php
    protected $fillable = [
        'company_name', 'contact_person', 'phone', 'email', 'password',
        'business_address', 'gst_number', 'status', 'created_by',
        'rejection_reason', 'email_verified_at', 'approved_at', 'approved_by',
        'seller_code', 'manufacturing_activity', 'availability_hours', 'password_set_at',
        'clerk_user_id',
    ];
```

- [ ] **Step 5: Run the full test suite against SQLite**

Run: `php artisan test`
Expected: PASS — `RefreshDatabase` picks up both new migrations automatically; no existing test touches `clerk_user_id` yet so nothing changes behaviorally.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_30_120000_add_clerk_user_id_to_users_table.php \
        database/migrations/2026_08_30_120100_add_clerk_fields_to_sellers_table.php \
        app/Models/User.php app/Models/Seller.php
git commit -m "Add clerk_user_id and nullable password to users and sellers"
```

---

## Task 3: ClerkAuthenticator service (token verification + identity fetch)

**Files:**
- Create: `app/Services/Clerk/ClerkIdentity.php`
- Create: `app/Exceptions/ClerkVerificationException.php`
- Create: `app/Services/Clerk/ClerkAuthenticator.php`
- Create: `tests/Unit/Services/Clerk/ClerkAuthenticatorTest.php`
- Modify: `bootstrap/app.php`

**Interfaces:**
- Consumes: `config('services.clerk.frontend_api')`, `config('services.clerk.secret_key')`.
- Produces: `App\Services\Clerk\ClerkAuthenticator::identify(string $token): ClerkIdentity`, throwing `App\Exceptions\ClerkVerificationException` on any failure (bad signature, expired token, unreachable Clerk API, no verified email). `ClerkIdentity` has public readonly `$id`, `$email`, `$name` (string, string, ?string). Every later controller task depends on this exact signature. The exception is auto-rendered as a `422 {"error": "..."}` JSON response by the global handler — controllers never need to catch it themselves.

- [ ] **Step 1: Write the ClerkIdentity DTO**

```php
<?php

namespace App\Services\Clerk;

final class ClerkIdentity
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly ?string $name,
    ) {
    }
}
```

- [ ] **Step 2: Write the exception class**

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class ClerkVerificationException extends RuntimeException
{
}
```

- [ ] **Step 3: Register the global JSON handler for it**

In `bootstrap/app.php`, change:

```php
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

to:

```php
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ClerkVerificationException $e, Request $request) {
            return response()->json(['error' => 'Google sign-in failed. Please try again.'], 422);
        });
    })->create();
```

Add the two new imports at the top of the file, alongside the existing `use` statements:

```php
use App\Exceptions\ClerkVerificationException;
use Illuminate\Http\Request;
```

- [ ] **Step 4: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Clerk;

use App\Services\Clerk\ClerkAuthenticator;
use App\Exceptions\ClerkVerificationException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClerkAuthenticatorTest extends TestCase
{
    public function test_it_verifies_a_valid_token_and_returns_the_clerk_identity(): void
    {
        [$privateKeyPem, $jwk] = $this->generateTestKey();

        config([
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
            'services.clerk.secret_key' => 'sk_test_dummy',
        ]);

        Http::fake([
            'https://test.clerk.accounts.dev/.well-known/jwks.json' => Http::response(['keys' => [$jwk]]),
            'https://api.clerk.com/v1/users/user_123' => Http::response([
                'id' => 'user_123',
                'first_name' => 'Asha',
                'last_name' => 'Rao',
                'primary_email_address_id' => 'idn_1',
                'email_addresses' => [
                    ['id' => 'idn_1', 'email_address' => 'asha@example.com'],
                ],
            ]),
        ]);

        $token = JWT::encode(
            ['sub' => 'user_123', 'iat' => time(), 'exp' => time() + 60],
            $privateKeyPem,
            'RS256',
            'test-key-1'
        );

        $identity = app(ClerkAuthenticator::class)->identify($token);

        $this->assertSame('user_123', $identity->id);
        $this->assertSame('asha@example.com', $identity->email);
        $this->assertSame('Asha Rao', $identity->name);
    }

    public function test_it_rejects_a_token_signed_by_an_unknown_key(): void
    {
        [, $jwk] = $this->generateTestKey();
        [$otherPrivateKeyPem] = $this->generateTestKey('other-key-1');

        config([
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
            'services.clerk.secret_key' => 'sk_test_dummy',
        ]);

        Http::fake([
            'https://test.clerk.accounts.dev/.well-known/jwks.json' => Http::response(['keys' => [$jwk]]),
        ]);

        $token = JWT::encode(
            ['sub' => 'user_123', 'iat' => time(), 'exp' => time() + 60],
            $otherPrivateKeyPem,
            'RS256',
            'other-key-1'
        );

        $this->expectException(ClerkVerificationException::class);

        app(ClerkAuthenticator::class)->identify($token);
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function generateTestKey(string $kid = 'test-key-1'): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($resource, $privateKeyPem);
        $details = openssl_pkey_get_details($resource);

        $jwk = [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $kid,
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ];

        return [$privateKeyPem, $jwk];
    }
}
```

- [ ] **Step 5: Run the test to verify it fails**

Run: `php artisan test --filter=ClerkAuthenticatorTest`
Expected: FAIL — `Class "App\Services\Clerk\ClerkAuthenticator" not found`.

- [ ] **Step 6: Write the ClerkAuthenticator implementation**

```php
<?php

namespace App\Services\Clerk;

use App\Exceptions\ClerkVerificationException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ClerkAuthenticator
{
    public function identify(string $token): ClerkIdentity
    {
        return $this->fetchIdentity($this->verify($token));
    }

    private function verify(string $token): string
    {
        $frontendApi = config('services.clerk.frontend_api');

        $jwks = Cache::remember('clerk.jwks', now()->addHour(), function () use ($frontendApi) {
            $response = Http::get("https://{$frontendApi}/.well-known/jwks.json");

            if ($response->failed()) {
                throw new ClerkVerificationException('Could not fetch Clerk JWKS.');
            }

            return $response->json();
        });

        try {
            $payload = JWT::decode($token, JWK::parseKeySet($jwks));
        } catch (Throwable $exception) {
            throw new ClerkVerificationException('Invalid Clerk session token.', previous: $exception);
        }

        if (empty($payload->sub)) {
            throw new ClerkVerificationException('Clerk session token is missing a subject.');
        }

        return $payload->sub;
    }

    private function fetchIdentity(string $clerkUserId): ClerkIdentity
    {
        $response = Http::withToken(config('services.clerk.secret_key'))
            ->get("https://api.clerk.com/v1/users/{$clerkUserId}");

        if ($response->failed()) {
            throw new ClerkVerificationException('Could not fetch the Clerk user record.');
        }

        $primaryEmailId = $response->json('primary_email_address_id');
        $email = collect($response->json('email_addresses', []))
            ->firstWhere('id', $primaryEmailId);

        if (blank($email['email_address'] ?? null)) {
            throw new ClerkVerificationException('Clerk user has no verified email address.');
        }

        $name = trim(($response->json('first_name') ?? '').' '.($response->json('last_name') ?? ''));

        return new ClerkIdentity(
            id: $clerkUserId,
            email: $email['email_address'],
            name: $name !== '' ? $name : null,
        );
    }
}
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=ClerkAuthenticatorTest`
Expected: PASS (both tests).

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add app/Services/Clerk app/Exceptions/ClerkVerificationException.php bootstrap/app.php \
        tests/Unit/Services/Clerk/ClerkAuthenticatorTest.php
git commit -m "Add ClerkAuthenticator for token verification and identity lookup"
```

---

## Task 4: Buyer Clerk auth endpoint

**Files:**
- Create: `app/Http/Controllers/ClerkBuyerAuthController.php`
- Create: `tests/Feature/ClerkBuyerAuthTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Services\Clerk\ClerkAuthenticator::identify()` (Task 3).
- Produces: `POST /auth/clerk/buyer` (route name `auth.clerk.buyer`) — request body `{"token": "..."}`, response `{"redirect": "..."}` on success (200) and logs into the `web` guard, or a `422 {"error": "..."}` from the global Clerk exception handler on failure.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Clerk\ClerkAuthenticator;
use App\Services\Clerk\ClerkIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkBuyerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_visitor_is_registered_and_logged_in_via_google(): void
    {
        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->with('valid-token')
                ->andReturn(new ClerkIdentity('user_123', 'jane@example.com', 'Jane Buyer'));
        });

        $response = $this->postJson('/auth/clerk/buyer', ['token' => 'valid-token']);

        $response->assertOk();
        $response->assertJson(['redirect' => route('home')]);
        $this->assertAuthenticated('web');

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('user_123', $user->clerk_user_id);
        $this->assertSame('Jane Buyer', $user->name);
        $this->assertNull($user->password);
    }

    public function test_an_existing_password_account_is_linked_by_email_instead_of_duplicated(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'clerk_user_id' => null]);

        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->andReturn(new ClerkIdentity('user_123', 'jane@example.com', 'Jane Buyer'));
        });

        $response = $this->postJson('/auth/clerk/buyer', ['token' => 'valid-token']);

        $response->assertOk();
        $this->assertAuthenticatedAs($user->fresh(), 'web');
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('user_123', $user->fresh()->clerk_user_id);
    }

    public function test_a_missing_token_is_rejected(): void
    {
        $response = $this->postJson('/auth/clerk/buyer', []);

        $response->assertStatus(422);
        $this->assertGuest('web');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ClerkBuyerAuthTest`
Expected: FAIL — route `/auth/clerk/buyer` not found (404).

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Clerk\ClerkAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClerkBuyerAuthController extends Controller
{
    public function store(Request $request, ClerkAuthenticator $clerk): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $identity = $clerk->identify($request->string('token')->toString());

        $user = User::where('clerk_user_id', $identity->id)->first()
            ?? User::where('email', $identity->email)->first()
            ?? new User(['email' => $identity->email]);

        $user->clerk_user_id = $identity->id;

        if (blank($user->name)) {
            $user->name = $identity->name ?? $identity->email;
        }

        $user->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('home')]);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\ClerkBuyerAuthController;
```

Add, right after the existing `/register` and `/login` route pairs:

```php
Route::post('/auth/clerk/buyer', [ClerkBuyerAuthController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('auth.clerk.buyer');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=ClerkBuyerAuthTest`
Expected: PASS (all three tests).

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ClerkBuyerAuthController.php routes/web.php tests/Feature/ClerkBuyerAuthTest.php
git commit -m "Add buyer Clerk sign-in endpoint"
```

---

## Task 5: Seller Clerk registration-identity endpoint

**Files:**
- Create: `app/Http/Controllers/Seller/ClerkRegistrationIdentityController.php`
- Create: `tests/Feature/Seller/ClerkRegistrationIdentityTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `ClerkAuthenticator::identify()` (Task 3).
- Produces: `POST /auth/clerk/seller/register` (route name `seller.clerk.register`) — stashes `session('seller_clerk_identity')` as `['id' => string, 'email' => string, 'name' => ?string]` and responds `{"redirect": "<seller.register URL>"}`. Task 6 reads this session key. Responds `422 {"error": "..."}` if a seller with that email already exists.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Seller;

use App\Models\Seller;
use App\Services\Clerk\ClerkAuthenticator;
use App\Services\Clerk\ClerkIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkRegistrationIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_verified_identity_is_stashed_in_session_and_redirects_to_the_registration_form(): void
    {
        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->with('valid-token')
                ->andReturn(new ClerkIdentity('user_456', 'asha@raotraders.example', 'Asha Rao'));
        });

        $response = $this->postJson('/auth/clerk/seller/register', ['token' => 'valid-token']);

        $response->assertOk();
        $response->assertJson(['redirect' => route('seller.register')]);
        $this->assertSame([
            'id' => 'user_456',
            'email' => 'asha@raotraders.example',
            'name' => 'Asha Rao',
        ], session('seller_clerk_identity'));
    }

    public function test_an_email_already_used_by_a_seller_is_rejected(): void
    {
        Seller::factory()->create(['email' => 'asha@raotraders.example']);

        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->andReturn(new ClerkIdentity('user_456', 'asha@raotraders.example', 'Asha Rao'));
        });

        $response = $this->postJson('/auth/clerk/seller/register', ['token' => 'valid-token']);

        $response->assertStatus(422);
        $this->assertNull(session('seller_clerk_identity'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ClerkRegistrationIdentityTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\Clerk\ClerkAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClerkRegistrationIdentityController extends Controller
{
    public function store(Request $request, ClerkAuthenticator $clerk): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $identity = $clerk->identify($request->string('token')->toString());

        if (Seller::where('email', $identity->email)->exists()) {
            return response()->json([
                'error' => 'A seller account already exists for this email. Try logging in instead.',
            ], 422);
        }

        $request->session()->put('seller_clerk_identity', [
            'id' => $identity->id,
            'email' => $identity->email,
            'name' => $identity->name,
        ]);

        return response()->json(['redirect' => route('seller.register')]);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\Seller\ClerkRegistrationIdentityController;
```

Add, right after the existing seller register routes:

```php
Route::post('/auth/clerk/seller/register', [ClerkRegistrationIdentityController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('seller.clerk.register');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=ClerkRegistrationIdentityTest`
Expected: PASS.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Seller/ClerkRegistrationIdentityController.php routes/web.php \
        tests/Feature/Seller/ClerkRegistrationIdentityTest.php
git commit -m "Add seller Clerk registration-identity endpoint"
```

---

## Task 6: Seller registration form — Clerk-prefilled path

**Files:**
- Modify: `app/Http/Requests/StoreSellerRegistrationRequest.php`
- Modify: `app/Http/Controllers/Seller/RegistrationController.php`
- Modify: `resources/views/seller/register.blade.php`
- Modify: `tests/Feature/SellerRegistrationTest.php`

**Interfaces:**
- Consumes: `session('seller_clerk_identity')` (Task 5's shape: `['id', 'email', 'name']`).
- Produces: when that session key is present, `Seller::create()` sets `clerk_user_id`, skips `password`, sets `status = 'pending_admin_approval'` and `email_verified_at = now()` directly (no activation email), and clears the session key afterward. Behavior is unchanged when the session key is absent.

- [ ] **Step 1: Write the failing tests (added to the existing SellerRegistrationTest.php)**

Add these two methods to `tests/Feature/SellerRegistrationTest.php` (inside the class, alongside the existing tests):

```php
    public function test_a_clerk_identified_registration_skips_password_and_email_verification(): void
    {
        Mail::fake();

        $this->withSession(['seller_clerk_identity' => [
            'id' => 'user_456',
            'email' => 'asha@raotraders.example',
            'name' => 'Asha Rao',
        ]]);

        $payload = $this->validPayload();
        unset($payload['password'], $payload['password_confirmation']);

        $response = $this->post(route('seller.register.store'), $payload);

        $response->assertRedirect(route('seller.registration.submitted'));

        $this->assertDatabaseHas('sellers', [
            'email' => 'asha@raotraders.example',
            'clerk_user_id' => 'user_456',
            'status' => 'pending_admin_approval',
            'password' => null,
        ]);

        $seller = Seller::where('email', 'asha@raotraders.example')->firstOrFail();
        $this->assertNotNull($seller->email_verified_at);

        Mail::assertNothingQueued();
        $this->assertNull(session('seller_clerk_identity'));
    }

    public function test_a_clerk_identified_registration_is_rejected_if_the_email_is_already_taken(): void
    {
        Seller::factory()->create(['email' => 'asha@raotraders.example']);

        $this->withSession(['seller_clerk_identity' => [
            'id' => 'user_456',
            'email' => 'asha@raotraders.example',
            'name' => 'Asha Rao',
        ]]);

        $payload = $this->validPayload(['company_name' => 'A Second Company']);
        unset($payload['password'], $payload['password_confirmation']);

        $response = $this->post(route('seller.register.store'), $payload);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseCount('sellers', 1);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=SellerRegistrationTest`
Expected: FAIL on the two new tests — `password` is currently `required`, so omitting it fails validation instead of exercising the Clerk path.

- [ ] **Step 3: Make the password rule conditional**

In `app/Http/Requests/StoreSellerRegistrationRequest.php`, change:

```php
            'password' => ['required', 'confirmed', Password::min(8)],
```

to:

```php
            'password' => session()->has('seller_clerk_identity')
                ? ['nullable']
                : ['required', 'confirmed', Password::min(8)],
```

- [ ] **Step 4: Update the controller to branch on the Clerk identity**

Replace `app/Http/Controllers/Seller/RegistrationController.php` entirely with:

```php
<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSellerRegistrationRequest;
use App\Mail\SellerActivationMail;
use App\Models\Page;
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
        return view('seller.register', [
            'termsPage' => Page::query()->where('slug', 'terms-and-conditions')->where('status', 'published')->first(),
            'clerkIdentity' => session('seller_clerk_identity'),
        ]);
    }

    public function store(StoreSellerRegistrationRequest $request): RedirectResponse
    {
        $clerkIdentity = session('seller_clerk_identity');

        if ($clerkIdentity && Seller::where('email', $clerkIdentity['email'])->exists()) {
            return back()->withErrors(['email' => 'A seller account already exists for this email.'])->withInput();
        }

        $seller = Seller::create([
            'company_name' => $request->validated('company_name'),
            'contact_person' => $request->validated('contact_person'),
            'phone' => $request->validated('phone'),
            'email' => $clerkIdentity['email'] ?? $request->validated('email'),
            'business_address' => $request->validated('business_address'),
            'gst_number' => $request->validated('gst_number'),
            'manufacturing_activity' => $request->validated('manufacturing_activity'),
            'availability_hours' => $request->validated('availability_hours'),
            'password' => $clerkIdentity ? null : Hash::make($request->validated('password')),
            'clerk_user_id' => $clerkIdentity['id'] ?? null,
            'status' => $clerkIdentity ? 'pending_admin_approval' : 'pending_email_verification',
            'email_verified_at' => $clerkIdentity ? now() : null,
            'created_by' => 'self',
        ]);

        foreach ($request->file('documents', []) as $file) {
            $seller->documents()->create([
                'label' => $file->getClientOriginalName(),
                'file_path' => $file->store('seller-documents', 'public'),
                'uploaded_at' => now(),
            ]);
        }

        if ($clerkIdentity) {
            session()->forget('seller_clerk_identity');
        } else {
            try {
                Mail::to($seller->email)->send(new SellerActivationMail($seller));
            } catch (\Throwable $exception) {
                Log::error('Failed to queue seller activation email.', [
                    'seller_id' => $seller->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return redirect()->route('seller.registration.submitted');
    }
}
```

- [ ] **Step 5: Update the view to hide email/password when a Clerk identity is present**

In `resources/views/seller/register.blade.php`, replace the email field:

```blade
            <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
```

with:

```blade
            <div class="col-md-6 mb-3">
                <label class="form-label">Email</label>
                @if ($clerkIdentity)
                    <input type="email" class="form-control" value="{{ $clerkIdentity['email'] }}" readonly>
                    <div class="form-text">Signed in as {{ $clerkIdentity['email'] }} via Google.</div>
                @else
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                @endif
            </div>
```

Replace the password row:

```blade
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
```

with:

```blade
        @unless ($clerkIdentity)
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
        @endunless
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=SellerRegistrationTest`
Expected: PASS — all existing tests plus the two new ones.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/StoreSellerRegistrationRequest.php \
        app/Http/Controllers/Seller/RegistrationController.php \
        resources/views/seller/register.blade.php \
        tests/Feature/SellerRegistrationTest.php
git commit -m "Support Clerk-identified seller registration, skipping password and email verification"
```

---

## Task 7: Seller Clerk panel-login endpoint

**Files:**
- Create: `app/Http/Controllers/Seller/ClerkPanelLoginController.php`
- Create: `tests/Feature/Seller/ClerkPanelLoginTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `ClerkAuthenticator::identify()` (Task 3).
- Produces: `POST /auth/clerk/seller/login` (route name `seller.clerk.login`) — logs an approved, Clerk-linked seller into the `seller` guard and responds `{"redirect": "<dashboard URL>"}`; `422 {"error": "..."}` when no seller is linked or the seller isn't approved yet.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Seller;

use App\Models\Seller;
use App\Services\Clerk\ClerkAuthenticator;
use App\Services\Clerk\ClerkIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClerkPanelLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_approved_clerk_linked_seller_can_log_in(): void
    {
        $seller = Seller::factory()->create([
            'clerk_user_id' => 'user_456',
            'status' => 'approved',
        ]);

        $this->mock(ClerkAuthenticator::class, function ($mock) use ($seller) {
            $mock->shouldReceive('identify')
                ->once()
                ->with('valid-token')
                ->andReturn(new ClerkIdentity('user_456', $seller->email, 'Asha Rao'));
        });

        $response = $this->postJson('/auth/clerk/seller/login', ['token' => 'valid-token']);

        $response->assertOk();
        $this->assertAuthenticatedAs($seller->fresh(), 'seller');
    }

    public function test_an_unlinked_google_account_is_rejected(): void
    {
        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->andReturn(new ClerkIdentity('user_999', 'nobody@example.com', 'Nobody'));
        });

        $response = $this->postJson('/auth/clerk/seller/login', ['token' => 'valid-token']);

        $response->assertStatus(422);
        $this->assertGuest('seller');
    }

    public function test_a_not_yet_approved_seller_is_rejected(): void
    {
        Seller::factory()->create([
            'clerk_user_id' => 'user_456',
            'status' => 'pending_admin_approval',
        ]);

        $this->mock(ClerkAuthenticator::class, function ($mock) {
            $mock->shouldReceive('identify')
                ->once()
                ->andReturn(new ClerkIdentity('user_456', 'asha@raotraders.example', 'Asha Rao'));
        });

        $response = $this->postJson('/auth/clerk/seller/login', ['token' => 'valid-token']);

        $response->assertStatus(422);
        $this->assertGuest('seller');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ClerkPanelLoginTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Services\Clerk\ClerkAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClerkPanelLoginController extends Controller
{
    public function store(Request $request, ClerkAuthenticator $clerk): JsonResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $identity = $clerk->identify($request->string('token')->toString());

        $seller = Seller::where('clerk_user_id', $identity->id)->first();

        if (! $seller) {
            return response()->json([
                'error' => 'No seller account is linked to this Google account. Register as a seller first.',
            ], 422);
        }

        if (! $seller->isApproved()) {
            return response()->json([
                'error' => 'Your seller account is still awaiting approval.',
            ], 422);
        }

        Auth::guard('seller')->login($seller);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('filament.seller.pages.dashboard')]);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\Seller\ClerkPanelLoginController;
```

Add, right after the `seller.clerk.register` route from Task 5:

```php
Route::post('/auth/clerk/seller/login', [ClerkPanelLoginController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('seller.clerk.login');
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=ClerkPanelLoginTest`
Expected: PASS (all three tests).

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Seller/ClerkPanelLoginController.php routes/web.php \
        tests/Feature/Seller/ClerkPanelLoginTest.php
git commit -m "Add seller Clerk panel-login endpoint"
```

---

## Task 8: Shared frontend plumbing — layout, button partial, completion page

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Create: `resources/views/partials/clerk-google-button.blade.php`
- Create: `resources/views/auth/clerk-complete.blade.php`
- Create: `tests/Feature/ClerkCompletionPageTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces: `<meta name="csrf-token">` in every page's `<head>`; Clerk's JS SDK loaded on every page (only when `services.clerk.publishable_key` is configured); `@include('partials.clerk-google-button', ['intent' => '<buyer|seller_register|seller_login>'])` — a self-contained button that, on click, runs `Clerk.client.signIn.authenticateWithRedirect(...)` back to `route('auth.clerk.complete')`; `GET /auth/clerk/complete` (route name `auth.clerk.complete`) — reads the Clerk session token, maps `?intent=` to the right POST endpoint from Tasks 4/5/7, and redirects on success.

- [ ] **Step 1: Add the CSRF meta tag and Clerk.js script tag to the layout**

In `resources/views/layouts/app.blade.php`, add inside `<head>`, right after the viewport meta tag:

```blade
    <meta name="csrf-token" content="{{ csrf_token() }}">
```

Near the bottom of the file, right after the existing recaptcha `@if` block and before `@include('partials.cookie-consent-banner')`, add:

```blade
    @if (config('services.clerk.publishable_key'))
        <script
            async
            crossorigin="anonymous"
            data-clerk-publishable-key="{{ config('services.clerk.publishable_key') }}"
            src="https://{{ config('services.clerk.frontend_api') }}/npm/@clerk/clerk-js@latest/dist/clerk.browser.js"
            type="text/javascript"
        ></script>
    @endif
```

- [ ] **Step 2: Write the public button partial**

```blade
{{-- resources/views/partials/clerk-google-button.blade.php --}}
@if (config('services.clerk.publishable_key'))
    <div class="d-grid mb-3">
        <button type="button" id="clerk-google-btn-{{ $intent }}" class="btn btn-outline-dark">
            Continue with Google
        </button>
    </div>
    <div class="text-center text-muted mb-3">or</div>

    <script>
        window.addEventListener('load', async function () {
            await window.Clerk.load();

            document.getElementById('clerk-google-btn-{{ $intent }}').addEventListener('click', async function () {
                await window.Clerk.client.signIn.authenticateWithRedirect({
                    strategy: 'oauth_google',
                    redirectUrl: '{{ route('auth.clerk.complete') }}?intent={{ $intent }}',
                    redirectUrlComplete: '{{ route('auth.clerk.complete') }}?intent={{ $intent }}',
                });
            });
        });
    </script>
@endif
```

- [ ] **Step 3: Write the completion page**

```blade
{{-- resources/views/auth/clerk-complete.blade.php --}}
@extends('layouts.app')

@section('title', 'Signing you in')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <p id="clerk-status">Signing you in&hellip;</p>
        </div>
    </div>

    <script>
        window.addEventListener('load', async function () {
            const statusEl = document.getElementById('clerk-status');

            await window.Clerk.load();

            if (!window.Clerk.session) {
                statusEl.textContent = 'Sign-in did not complete. Please try again.';
                return;
            }

            const endpoints = {
                buyer: @json(route('auth.clerk.buyer')),
                seller_register: @json(route('seller.clerk.register')),
                seller_login: @json(route('seller.clerk.login')),
            };

            const intent = new URLSearchParams(window.location.search).get('intent');
            const endpoint = endpoints[intent];

            if (!endpoint) {
                statusEl.textContent = 'Something went wrong. Please try again.';
                return;
            }

            const token = await window.Clerk.session.getToken();

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ token: token }),
            });

            const data = await response.json();

            if (response.ok) {
                window.location.href = data.redirect;
            } else {
                statusEl.textContent = data.error || 'Something went wrong. Please try again.';
            }
        });
    </script>
@endsection
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add right before the buyer/seller Clerk POST routes:

```php
Route::view('/auth/clerk/complete', 'auth.clerk-complete')->name('auth.clerk.complete');
```

- [ ] **Step 5: Write the test**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ClerkCompletionPageTest extends TestCase
{
    public function test_the_completion_page_loads(): void
    {
        $response = $this->get('/auth/clerk/complete?intent=buyer');

        $response->assertOk();
        $response->assertSee('Signing you in', false);
    }

    public function test_the_layout_always_includes_a_csrf_meta_tag(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
    }

    public function test_clerk_js_is_only_loaded_when_configured(): void
    {
        config(['services.clerk.publishable_key' => null]);
        $this->get('/register')->assertDontSee('clerk-js', false);

        config([
            'services.clerk.publishable_key' => 'pk_test_dummy',
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
        ]);
        $this->get('/register')->assertSee('data-clerk-publishable-key', false);
    }
}
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=ClerkCompletionPageTest`
Expected: PASS.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add resources/views/layouts/app.blade.php resources/views/partials/clerk-google-button.blade.php \
        resources/views/auth/clerk-complete.blade.php routes/web.php \
        tests/Feature/ClerkCompletionPageTest.php
git commit -m "Add shared Clerk frontend plumbing: layout script tag, button partial, completion page"
```

---

## Task 9: Wire the Google button into buyer and seller entry-point pages

**Files:**
- Modify: `resources/views/auth/register.blade.php`
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/seller/landing.blade.php`
- Modify: `resources/views/seller/register.blade.php`
- Create: `tests/Feature/ClerkButtonVisibilityTest.php`

**Interfaces:**
- Consumes: `partials.clerk-google-button` (Task 8).
- Produces: the button appears on `/register`, `/login`, `/sellers`, and `/seller/register` (the last only when not already mid-Clerk-flow, i.e. `$clerkIdentity` is null) whenever Clerk is configured; invisible otherwise.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ClerkButtonVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clerk.publishable_key' => 'pk_test_dummy',
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
        ]);
    }

    public function test_the_button_appears_on_buyer_register(): void
    {
        $this->get('/register')->assertSee('clerk-google-btn-buyer', false);
    }

    public function test_the_button_appears_on_buyer_login(): void
    {
        $this->get('/login')->assertSee('clerk-google-btn-buyer', false);
    }

    public function test_the_button_appears_on_the_seller_landing_page(): void
    {
        $this->get('/sellers')->assertSee('clerk-google-btn-seller_register', false);
    }

    public function test_the_button_appears_on_seller_register_when_not_already_clerk_identified(): void
    {
        $this->get('/seller/register')->assertSee('clerk-google-btn-seller_register', false);
    }

    public function test_the_button_is_hidden_on_seller_register_once_a_clerk_identity_is_in_session(): void
    {
        $this->withSession(['seller_clerk_identity' => [
            'id' => 'user_456',
            'email' => 'asha@raotraders.example',
            'name' => 'Asha Rao',
        ]]);

        $this->get('/seller/register')->assertDontSee('clerk-google-btn-seller_register', false);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ClerkButtonVisibilityTest`
Expected: FAIL on all five — the partial isn't included anywhere yet.

- [ ] **Step 3: Add the button to `resources/views/auth/register.blade.php`**

Replace:

```blade
            <h1>Create an Account</h1>
            <p class="text-muted">Optional — track your past quote requests and save favorites.</p>

            <form method="POST" action="{{ route('register.store') }}">
```

with:

```blade
            <h1>Create an Account</h1>
            <p class="text-muted">Optional — track your past quote requests and save favorites.</p>

            @include('partials.clerk-google-button', ['intent' => 'buyer'])

            <form method="POST" action="{{ route('register.store') }}">
```

- [ ] **Step 4: Add the button to `resources/views/auth/login.blade.php`**

Replace:

```blade
            <h1>Log In</h1>

            <form method="POST" action="{{ route('login.store') }}">
```

with:

```blade
            <h1>Log In</h1>

            @include('partials.clerk-google-button', ['intent' => 'buyer'])

            <form method="POST" action="{{ route('login.store') }}">
```

- [ ] **Step 5: Add the button to `resources/views/seller/landing.blade.php`**

Replace:

```blade
            <div class="d-flex justify-content-center gap-3 mt-4">
                <a href="{{ route('seller.register') }}" class="btn btn-primary btn-lg">Register as a Seller</a>
                <a href="{{ route('filament.seller.auth.login') }}" class="btn btn-outline-secondary btn-lg">Already a seller? Log In</a>
            </div>
```

with:

```blade
            <div class="d-flex justify-content-center gap-3 mt-4">
                <a href="{{ route('seller.register') }}" class="btn btn-primary btn-lg">Register as a Seller</a>
                <a href="{{ route('filament.seller.auth.login') }}" class="btn btn-outline-secondary btn-lg">Already a seller? Log In</a>
            </div>

            <div class="col-md-4 mx-auto mt-4">
                @include('partials.clerk-google-button', ['intent' => 'seller_register'])
            </div>
```

- [ ] **Step 6: Add the button to `resources/views/seller/register.blade.php`, gated on `$clerkIdentity`**

Replace:

```blade
@section('content')
    <h1>Seller Registration</h1>
```

with:

```blade
@section('content')
    <h1>Seller Registration</h1>

    @unless ($clerkIdentity)
        <div class="col-md-6">
            @include('partials.clerk-google-button', ['intent' => 'seller_register'])
        </div>
    @endunless
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=ClerkButtonVisibilityTest`
Expected: PASS (all five).

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add resources/views/auth/register.blade.php resources/views/auth/login.blade.php \
        resources/views/seller/landing.blade.php resources/views/seller/register.blade.php \
        tests/Feature/ClerkButtonVisibilityTest.php
git commit -m "Wire the Google sign-in button into buyer and seller entry-point pages"
```

---

## Task 10: Google button on the Filament seller panel login page

**Files:**
- Create: `resources/views/filament/partials/clerk-login-button.blade.php`
- Modify: `app/Providers/Filament/SellerPanelProvider.php`
- Create: `tests/Feature/Seller/ClerkPanelLoginButtonVisibilityTest.php`

**Interfaces:**
- Produces: on `GET /seller/login` (route `filament.seller.auth.login`), a "Continue with Google" button (`intent=seller_login`) appears above the password form when Clerk is configured, using the same `route('auth.clerk.complete')` completion flow as the public pages. Self-contained (loads its own Clerk.js script tag) since the Filament panel doesn't extend `layouts.app`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Seller;

use Tests\TestCase;

class ClerkPanelLoginButtonVisibilityTest extends TestCase
{
    public function test_the_button_appears_on_the_seller_panel_login_page_when_clerk_is_configured(): void
    {
        config([
            'services.clerk.publishable_key' => 'pk_test_dummy',
            'services.clerk.frontend_api' => 'test.clerk.accounts.dev',
        ]);

        $this->get('/seller/login')->assertSee('clerk-google-seller-login', false);
    }

    public function test_the_button_is_hidden_when_clerk_is_not_configured(): void
    {
        config(['services.clerk.publishable_key' => null]);

        $this->get('/seller/login')->assertDontSee('clerk-google-seller-login', false);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=ClerkPanelLoginButtonVisibilityTest`
Expected: FAIL — the first test finds no such text yet.

- [ ] **Step 3: Write the Filament-side partial**

```blade
{{-- resources/views/filament/partials/clerk-login-button.blade.php --}}
@if (config('services.clerk.publishable_key'))
    <div class="mb-4">
        <button
            type="button"
            id="clerk-google-seller-login"
            class="fi-btn fi-btn-color-gray fi-btn-size-md flex w-full items-center justify-center gap-1 rounded-lg border px-3 py-2 text-sm font-semibold"
        >
            Continue with Google
        </button>
        <p class="mt-2 text-center text-xs text-gray-500">or sign in with your password below</p>
    </div>

    <script
        async
        crossorigin="anonymous"
        data-clerk-publishable-key="{{ config('services.clerk.publishable_key') }}"
        src="https://{{ config('services.clerk.frontend_api') }}/npm/@clerk/clerk-js@latest/dist/clerk.browser.js"
        type="text/javascript"
    ></script>
    <script>
        window.addEventListener('load', async function () {
            await window.Clerk.load();

            document.getElementById('clerk-google-seller-login').addEventListener('click', async function () {
                await window.Clerk.client.signIn.authenticateWithRedirect({
                    strategy: 'oauth_google',
                    redirectUrl: '{{ route('auth.clerk.complete') }}?intent=seller_login',
                    redirectUrlComplete: '{{ route('auth.clerk.complete') }}?intent=seller_login',
                });
            });
        });
    </script>
@endif
```

- [ ] **Step 4: Register the render hook**

In `app/Providers/Filament/SellerPanelProvider.php`, add a second `FilamentView::registerRenderHook(...)` call inside `boot()`, right after the existing `AUTH_LOGIN_FORM_AFTER` registration:

```php
        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
            function (): Htmlable {
                if (Filament::getCurrentPanel()?->getId() !== 'seller') {
                    return new HtmlString('');
                }

                return new HtmlString(view('filament.partials.clerk-login-button')->render());
            },
        );
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=ClerkPanelLoginButtonVisibilityTest`
Expected: PASS.

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/views/filament/partials/clerk-login-button.blade.php \
        app/Providers/Filament/SellerPanelProvider.php \
        tests/Feature/Seller/ClerkPanelLoginButtonVisibilityTest.php
git commit -m "Add Google sign-in button to the seller panel login page"
```

---

## Task 11: Document local-dev setup in CLAUDE.md

**Files:**
- Modify: `CLAUDE.md`

**Interfaces:**
- None — documentation only.

- [ ] **Step 1: Add a Clerk section**

In `CLAUDE.md`, add a new subsection right after the "Testing queued mail locally (optional)" section:

```markdown
### Clerk Google sign-in (optional locally)

Buyer register/login and seller register/panel-login have an optional
"Continue with Google" path via Clerk (see
`docs/superpowers/specs/2026-08-30-clerk-google-auth-design.md`). It's
off by default — every Clerk button and script tag is gated on
`CLERK_PUBLISHABLE_KEY` being set, so an empty `.env` behaves exactly
like before this feature existed.

To exercise it locally: create (or reuse) a Clerk application at
https://dashboard.clerk.com, enable the Google OAuth connection, and add
your local URL (e.g. `http://localhost:8000/auth/clerk/complete`) to its
allowed redirect URLs. Then set in `.env`:

```
CLERK_PUBLISHABLE_KEY=pk_test_...
CLERK_SECRET_KEY=sk_test_...
CLERK_FRONTEND_API=your-app-name.clerk.accounts.dev
```

`CLERK_FRONTEND_API` is the bare host shown on the dashboard's API Keys
page — no `https://` prefix. Restart `php artisan serve` after changing
it (config is cached per-request, not per-file-change, but a fresh
process picks up `.env` cleanly either way).
```

- [ ] **Step 2: Run the full suite one more time**

Run: `php artisan test`
Expected: PASS (docs-only change, but confirms nothing upstream broke).

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md
git commit -m "Document local Clerk setup for Google sign-in"
```

---

## Task 12: Final verification

**Files:** none — verification only.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: PASS, zero failures.

- [ ] **Step 2: Verify the new migrations apply cleanly to the dev database**

Run: `php artisan migrate`
Expected: the two migrations from Task 2 (if not already applied to the dev DB during that task) apply with no errors and no data loss.

- [ ] **Step 3: Confirm the feature is invisible with Clerk unconfigured**

With `.env`'s `CLERK_PUBLISHABLE_KEY` empty (the default), manually load `/register`, `/login`, `/sellers`, `/seller/register`, and `/seller/login` in a browser and confirm no Google button appears and the existing password flows work exactly as before.

- [ ] **Step 4: Note what still needs real Clerk credentials to verify**

This plan's automated tests mock `ClerkAuthenticator` and fake HTTP calls — they don't exercise a real Google OAuth round trip. Before shipping, manually test all four flows (buyer register, buyer login, seller register, seller panel login) end-to-end against a real Clerk application with `CLERK_PUBLISHABLE_KEY`/`CLERK_SECRET_KEY`/`CLERK_FRONTEND_API` set, using an actual Google account — this is the one thing no amount of unit/feature testing here substitutes for.

- [ ] **Step 5: Report status to the user**

Summarize: branch name, commits made, test suite status, and the manual end-to-end verification still needed (Step 4) before this ships.
