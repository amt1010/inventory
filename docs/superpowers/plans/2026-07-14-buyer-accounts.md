# Buyer Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add optional self-service buyer accounts on top of the existing (already-scaffolded, currently unused) `users` table/`web` guard — registration, login/logout, a "Favorites" list, and a read-only "My Quote Requests" history page. This is the last of the three phases split out of the original design spec.

**Architecture:** No new guard, no new panel — buyers use Laravel's stock `web` session guard against the stock `users` table, both already present and already partially wired (`QuoteRequestController` already stamps `user_id => auth('web')->id()` on every submission from the RFQ phase; this plan finally gives buyers a way to actually log in and see the result of that). Registration/login are hand-built controllers + Blade views, matching this codebase's established pattern for auth flows (no Breeze/Fortify/Jetstream installed — Seller registration/activation was hand-built the same way). Favorites are a new lightweight pivot-style table (`user_id`, `product_id`) with ownership enforced by plain `where('user_id', auth('web')->id())` query scoping in ordinary controllers — no Filament, no Gate/Policy involved, so none of the Staff-typed-policy pitfalls from the Seller Portal phase apply here.

**Tech Stack:** Laravel 11, PHP 8.2, Blade + Bootstrap 5 (existing public-site convention), MySQL (dev) / SQLite in-memory (tests, per `phpunit.xml`).

## Global Constraints

- **No new guard.** Buyers authenticate via the existing `web` guard / `users` provider (`config/auth.php`, already configured, untouched by every prior phase). Do not add a `buyer` guard — that pattern is reserved for actors with their own Filament panel (staff, seller); buyers have no CMS panel.
- **No email verification, no password reset, no GST/document upload.** Per spec: "Accounts are optional (used only to view past requests and favorites)" — this is a low-friction signup, not a vetted-supplier workflow like Seller registration. Keep the form to name/email/password.
- **Route ordering.** Every new root-level route (`/register`, `/login`, `/logout`, `/favorites`, `/my-quote-requests`) is a single path segment and must be registered in `routes/web.php` **before** the catch-all `/{slug}` route (added in the Content Pages & Navigation phase) — otherwise `/{slug}` would swallow them first. Add them alongside the other root-level routes (`/search`, `/quote-requests`), not after `/products/{path?}`.
- **"My Quote Requests" is read-only for the buyer.** No status changes, no notes, no reassignment — those stay exclusively on the `/admin` Sales dashboard (`QuoteRequestResource`, already built in the RFQ phase). This page is a plain `SELECT ... WHERE user_id = ?`, nothing more.
- **Favorites/quote-history ownership is enforced by direct query scoping, not a Policy class.** Unlike the Seller Portal phase, there is no Filament involvement here at all — a buyer-facing Blade controller checking `->where('user_id', auth('web')->id())` is sufficient and matches how `QuoteRequestController` already stamps ownership. Do not introduce `App\Policies\FavoritePolicy` or similar; it would add Gate/Filament-shaped ceremony this plain-controller code path doesn't need.
- **Registration/login POST routes get basic rate limiting** (`throttle:6,1`), matching Laravel's own scaffolding defaults (Breeze/Fortify apply the same) — a standard, low-risk hardening default for public auth endpoints, not scope creep.
- `APP_TIMEZONE=Asia/Kolkata`; tests run against SQLite in-memory (`phpunit.xml`), never the dev MySQL database.

## Context for the implementer

Existing pieces already in place (do not re-build these):
- `app/Models/User.php` — stock Laravel `Authenticatable` model, `fillable` = `name`, `email`, `password`. Untouched by every prior phase.
- `database/migrations/0001_01_01_000000_create_users_table.php` — stock `users`/`password_reset_tokens`/`sessions` tables. No migration changes needed for `users` itself.
- `config/auth.php` — `web` guard already uses `session` driver + `users` provider. No config changes needed.
- `app/Http/Controllers/QuoteRequestController.php` — already does `'user_id' => auth('web')->id()` on every RFQ submission (RFQ phase). This plan doesn't touch that file; it just gives buyers a session to be logged into when they submit.
- `app/Http/Controllers/Seller/RegistrationController.php` + `app/Http/Requests/StoreSellerRegistrationRequest.php` — the structural precedent for a hand-built registration controller/request in this codebase (buyer registration is simpler: no approval workflow, no documents, no GSTIN — just create the row and log the buyer in immediately).
- `resources/views/layouts/app.blade.php` — the shared layout (Content Pages & Navigation phase added `$headerNavItems`/`$footerNavItems` via a view composer here; this plan adds a small, separate auth-state chrome block — login/register vs. logout/account links — which is site chrome like the existing search bar, not a CMS-managed `nav_items` entry).
- `resources/views/catalog/product.blade.php` — the Product Detail page; Task 4 adds a favorite-toggle button next to the existing "Get a Quote" button (`line ~53`).
- `routes/web.php` — currently ends with `/products/{path?}` then the catch-all `/{slug}`. New routes go in the root-level group near `/search`/`/quote-requests`, before `/products/{path?}`.

## Task 1: `favorites` schema, model, relations

**Files:**
- Create: `database/migrations/2026_07_14_150000_create_favorites_table.php`
- Create: `app/Models/Favorite.php`
- Modify: `app/Models/User.php` (add `favorites()`)
- Modify: `app/Models/Product.php` (add `favoritedBy()`)
- Create: `database/factories/FavoriteFactory.php`
- Test: `tests/Feature/FavoriteModelTest.php`

**Interfaces:**
- Produces: `User::favorites(): HasMany` (consumed by Task 4's Favorites list page).
- Produces: `Product::favoritedBy(): HasMany` (consumed by Task 4's favorite-toggle button, to check whether the current buyer has already favorited a given product).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_have_many_favorites(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        $this->assertCount(1, $user->favorites);
    }

    public function test_a_product_knows_who_favorited_it(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->create();

        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

        // favoritedBy() returns Favorite rows (not User rows), so assert on
        // the foreign key column, not the Favorite's own id.
        $this->assertTrue($product->favoritedBy->contains('user_id', $user->id));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FavoriteModelTest`
Expected: FAIL — `Favorite` model, relations, and factory don't exist yet.

- [ ] **Step 3: Add the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
```

(the `unique(['user_id', 'product_id'])` constraint makes "favorite" idempotent at the DB level — a buyer can't favorite the same product twice, which Task 4's toggle action relies on via `firstOrCreate`.)

- [ ] **Step 4: Add the model, relations, and factory**

```php
<?php
// app/Models/Favorite.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'product_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

In `app/Models/User.php`, add the import and relation:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

// ... inside the class:

public function favorites(): HasMany
{
    return $this->hasMany(Favorite::class);
}
```

In `app/Models/Product.php`, add:

```php
public function favoritedBy(): HasMany
{
    return $this->hasMany(Favorite::class);
}
```

(`HasMany` is already imported in `Product.php` for the `images()`/`quoteRequests()` relations — no new `use` statement needed. `favoritedBy()` returns `Favorite` rows, not `User` rows directly, which is why Step 1's second test asserts on the `user_id` column rather than `id`.)

```php
<?php
// database/factories/FavoriteFactory.php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=FavoriteModelTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_14_150000_create_favorites_table.php \
        app/Models/Favorite.php app/Models/User.php app/Models/Product.php \
        database/factories/FavoriteFactory.php \
        tests/Feature/FavoriteModelTest.php
git commit -m "Add favorites schema, model, and User/Product relations"
```

---

## Task 2: Buyer self-registration

**Files:**
- Create: `app/Http/Requests/StoreUserRegistrationRequest.php`
- Create: `app/Http/Controllers/RegistrationController.php`
- Create: `resources/views/auth/register.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/BuyerRegistrationTest.php`

**Interfaces:**
- Produces: route `GET /register` (`register`), `POST /register` (`register.store`).
- Consumes: nothing from other tasks — this task is independently testable (a registered buyer isn't logged in automatically by this task's minimal version... actually per UX convention and since spec treats accounts as low-friction, log the buyer in immediately after successful registration — no email verification step).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_and_is_logged_in_immediately(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated('web');

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('Jane Buyer', $user->name);
    }

    public function test_registration_with_a_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->post('/register', [
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_registration_with_mismatched_passwords_is_rejected(): void
    {
        $response = $this->post('/register', [
            'name' => 'Jane Buyer',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'not-matching',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest('web');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=BuyerRegistrationTest`
Expected: FAIL — route doesn't exist yet.

- [ ] **Step 3: Implement**

```php
<?php
// app/Http/Requests/StoreUserRegistrationRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
```

```php
<?php
// app/Http/Controllers/RegistrationController.php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRegistrationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
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

```blade
{{-- resources/views/auth/register.blade.php --}}
@extends('layouts.app')

@section('title', 'Create an Account')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1>Create an Account</h1>
            <p class="text-muted">Optional — track your past quote requests and save favorites.</p>

            <form method="POST" action="{{ route('register.store') }}">
                @csrf

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Create Account</button>
                <a href="{{ route('login') }}" class="btn btn-link">Already have an account?</a>
            </form>
        </div>
    </div>
@endsection
```

In `routes/web.php`, add near the top (alongside `/search`, before `/products/{path?}` and `/{slug}`):

```php
use App\Http\Controllers\RegistrationController;

Route::get('/register', [RegistrationController::class, 'create'])->name('register');
Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=BuyerRegistrationTest`
Expected: PASS

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/StoreUserRegistrationRequest.php \
        app/Http/Controllers/RegistrationController.php \
        resources/views/auth/register.blade.php \
        routes/web.php \
        tests/Feature/BuyerRegistrationTest.php
git commit -m "Add buyer self-registration"
```

---

## Task 3: Buyer login/logout

**Files:**
- Create: `app/Http/Requests/AuthenticateUserRequest.php`
- Create: `app/Http/Controllers/SessionController.php`
- Create: `resources/views/auth/login.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/BuyerLoginTest.php`

**Interfaces:**
- Produces: route `GET /login` (`login`), `POST /login` (`login.store`), `POST /logout` (`logout`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BuyerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_registered_buyer_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make('password123')]);

        $response = $this->post('/login', [
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => Hash::make('password123')]);

        $response = $this->post('/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_a_logged_in_buyer_can_log_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->post('/logout');

        $response->assertRedirect();
        $this->assertGuest('web');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=BuyerLoginTest`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Implement**

```php
<?php
// app/Http/Requests/AuthenticateUserRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthenticateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

```php
<?php
// app/Http/Controllers/SessionController.php

namespace App\Http\Controllers;

use App\Http\Requests\AuthenticateUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(AuthenticateUserRequest $request): RedirectResponse
    {
        if (! Auth::guard('web')->attempt($request->validated(), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }
}
```

```blade
{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.app')

@section('title', 'Log In')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1>Log In</h1>

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary">Log In</button>
                <a href="{{ route('register') }}" class="btn btn-link">Create an account</a>
            </form>
        </div>
    </div>
@endsection
```

In `routes/web.php`, add next to the registration routes:

```php
use App\Http\Controllers\SessionController;

Route::get('/login', [SessionController::class, 'create'])->name('login');
Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=BuyerLoginTest`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/AuthenticateUserRequest.php \
        app/Http/Controllers/SessionController.php \
        resources/views/auth/login.blade.php \
        routes/web.php \
        tests/Feature/BuyerLoginTest.php
git commit -m "Add buyer login/logout"
```

---

## Task 4: Favorites — toggle + list page

**Files:**
- Create: `app/Http/Controllers/FavoriteController.php`
- Create: `resources/views/favorites/index.blade.php`
- Modify: `resources/views/catalog/product.blade.php` (add the toggle button)
- Modify: `routes/web.php`
- Test: `tests/Feature/FavoriteControllerTest.php`

**Interfaces:**
- Consumes: `User::favorites()`, `Product::favoritedBy()` (Task 1).
- Produces: route `GET /favorites` (`favorites.index`), `POST /favorites` (`favorites.store`), `DELETE /favorites/{product}` (`favorites.destroy`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Favorite;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login_when_favoriting(): void
    {
        $product = Product::factory()->create();

        $response = $this->post('/favorites', ['product_id' => $product->id]);

        $response->assertRedirect(route('login'));
    }

    public function test_a_logged_in_buyer_can_favorite_a_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->post('/favorites', ['product_id' => $product->id]);

        $response->assertRedirect();
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_favoriting_the_same_product_twice_does_not_duplicate(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs($user, 'web');

        $this->post('/favorites', ['product_id' => $product->id]);
        $this->post('/favorites', ['product_id' => $product->id]);

        $this->assertSame(1, Favorite::where('user_id', $user->id)->where('product_id', $product->id)->count());
    }

    public function test_a_buyer_can_remove_their_own_favorite(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);
        $this->actingAs($user, 'web');

        $response = $this->delete("/favorites/{$product->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id, 'product_id' => $product->id]);
    }

    public function test_a_buyer_cannot_remove_another_buyers_favorite(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create();
        Favorite::factory()->create(['user_id' => $owner->id, 'product_id' => $product->id]);
        $this->actingAs($other, 'web');

        $this->delete("/favorites/{$product->id}");

        $this->assertDatabaseHas('favorites', ['user_id' => $owner->id, 'product_id' => $product->id]);
    }

    public function test_the_favorites_page_only_lists_the_current_buyers_favorites(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = Category::factory()->create();
        $ownFavorite = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'My Favorite']);
        $othersFavorite = Product::factory()->create(['category_id' => $category->id, 'status' => 'published', 'name' => 'Not Mine']);
        Favorite::factory()->create(['user_id' => $user->id, 'product_id' => $ownFavorite->id]);
        Favorite::factory()->create(['user_id' => $other->id, 'product_id' => $othersFavorite->id]);
        $this->actingAs($user, 'web');

        $response = $this->get('/favorites');

        $response->assertOk();
        $response->assertSee('My Favorite');
        $response->assertDontSee('Not Mine');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=FavoriteControllerTest`
Expected: FAIL — routes/controller don't exist yet.

- [ ] **Step 3: Implement**

```php
<?php
// app/Http/Controllers/FavoriteController.php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function index(): View
    {
        $favorites = Favorite::query()
            ->where('user_id', auth('web')->id())
            ->whereHas('product', fn ($query) => $query->where('status', 'published'))
            ->with('product.images')
            ->get();

        return view('favorites.index', ['favorites' => $favorites]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['product_id' => ['required', 'exists:products,id']]);

        Favorite::query()->firstOrCreate([
            'user_id' => auth('web')->id(),
            'product_id' => $request->input('product_id'),
        ]);

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        Favorite::query()
            ->where('user_id', auth('web')->id())
            ->where('product_id', $product->id)
            ->delete();

        return back();
    }
}
```

```blade
{{-- resources/views/favorites/index.blade.php --}}
@extends('layouts.app')

@section('title', 'My Favorites')

@section('content')
    <h1>My Favorites</h1>

    @if ($favorites->isEmpty())
        <p class="text-muted">You haven't favorited any products yet.</p>
    @else
        <div class="row row-cols-1 row-cols-md-3 g-4">
            @foreach ($favorites as $favorite)
                <div class="col">
                    <div class="card h-100">
                        <a href="{{ url('/products/'.$favorite->product->path()) }}" class="text-decoration-none">
                            @if ($favorite->product->images->first())
                                <img src="{{ asset('storage/'.$favorite->product->images->first()->path) }}" class="card-img-top" alt="{{ $favorite->product->name }}">
                            @endif
                            <div class="card-body">
                                <h5 class="card-title text-dark">{{ $favorite->product->name }}</h5>
                            </div>
                        </a>
                        <div class="card-footer">
                            <form method="POST" action="{{ route('favorites.destroy', $favorite->product) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
```

In `resources/views/catalog/product.blade.php`, add a favorite-toggle button next to the existing "Get a Quote" button (find the line with `<button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#quoteRequestModal-{{ $product->id }}">Get a Quote</button>` and add immediately after it):

```blade
@auth('web')
    @if (auth('web')->user()->favorites()->where('product_id', $product->id)->exists())
        <form method="POST" action="{{ route('favorites.destroy', $product) }}" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger mt-3">Remove Favorite</button>
        </form>
    @else
        <form method="POST" action="{{ route('favorites.store') }}" class="d-inline">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <button type="submit" class="btn btn-outline-secondary mt-3">Add to Favorites</button>
        </form>
    @endif
@endauth
```

In `routes/web.php`, add (guarded by `auth:web` middleware — a guest posting/deleting is redirected to `/login`, matching `test_a_guest_is_redirected_to_login_when_favoriting`):

```php
use App\Http\Controllers\FavoriteController;

Route::middleware('auth:web')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/favorites/{product}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
});
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=FavoriteControllerTest`
Expected: PASS

- [ ] **Step 5: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: PASS — in particular confirm existing Product Detail page tests still pass (the new favorite-toggle markup must not break the page for a guest visitor, since `@auth('web')` hides it entirely for guests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FavoriteController.php \
        resources/views/favorites/index.blade.php \
        resources/views/catalog/product.blade.php \
        routes/web.php \
        tests/Feature/FavoriteControllerTest.php
git commit -m "Add favorites toggle and list page"
```

---

## Task 5: "My Quote Requests" read-only history page

**Files:**
- Create: `app/Http/Controllers/QuoteRequestHistoryController.php`
- Create: `resources/views/quote-requests/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/QuoteRequestHistoryTest.php`

**Interfaces:**
- Produces: route `GET /my-quote-requests` (`quote-requests.history`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/my-quote-requests');

        $response->assertRedirect(route('login'));
    }

    public function test_a_buyer_only_sees_their_own_quote_requests(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        QuoteRequest::factory()->create(['user_id' => $user->id, 'first_name' => 'Own', 'last_name' => 'Request']);
        QuoteRequest::factory()->create(['user_id' => $other->id, 'first_name' => 'Someone', 'last_name' => 'Else']);
        $this->actingAs($user, 'web');

        $response = $this->get('/my-quote-requests');

        $response->assertOk();
        $response->assertSee('Own Request');
        $response->assertDontSee('Someone Else');
    }

    public function test_the_page_has_no_edit_or_status_change_controls(): void
    {
        $user = User::factory()->create();
        QuoteRequest::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user, 'web');

        $response = $this->get('/my-quote-requests');

        $response->assertOk();
        $response->assertDontSee('<form', escape: false);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=QuoteRequestHistoryTest`
Expected: FAIL — route doesn't exist yet.

- [ ] **Step 3: Implement**

```php
<?php
// app/Http/Controllers/QuoteRequestHistoryController.php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use Illuminate\View\View;

class QuoteRequestHistoryController extends Controller
{
    public function index(): View
    {
        $quoteRequests = QuoteRequest::query()
            ->where('user_id', auth('web')->id())
            ->with('product')
            ->latest()
            ->get();

        return view('quote-requests.index', ['quoteRequests' => $quoteRequests]);
    }
}
```

```blade
{{-- resources/views/quote-requests/index.blade.php --}}
@extends('layouts.app')

@section('title', 'My Quote Requests')

@section('content')
    <h1>My Quote Requests</h1>

    @if ($quoteRequests->isEmpty())
        <p class="text-muted">You haven't submitted any quote requests yet.</p>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reason</th>
                    <th>Product</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quoteRequests as $quoteRequest)
                    <tr>
                        <td>{{ $quoteRequest->created_at->format('d M Y') }}</td>
                        <td>{{ $quoteRequest->reason }}</td>
                        <td>{{ $quoteRequest->product->name ?? 'General Inquiry' }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $quoteRequest->status)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
```

In `routes/web.php`, add inside the existing `auth:web` middleware group from Task 4:

```php
use App\Http\Controllers\QuoteRequestHistoryController;

Route::middleware('auth:web')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/favorites/{product}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
    Route::get('/my-quote-requests', [QuoteRequestHistoryController::class, 'index'])->name('quote-requests.history');
});
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=QuoteRequestHistoryTest`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/QuoteRequestHistoryController.php \
        resources/views/quote-requests/index.blade.php \
        routes/web.php \
        tests/Feature/QuoteRequestHistoryTest.php
git commit -m "Add read-only My Quote Requests history page"
```

---

## Task 6: Header auth chrome (login/register/logout/account links)

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/AuthChromeTest.php`

**Interfaces:**
- Consumes: routes from Tasks 2/3/4/5 (`register`, `login`, `logout`, `favorites.index`, `quote-requests.history`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthChromeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::factory()->create(['slug' => 'home', 'status' => 'published']);
    }

    public function test_a_guest_sees_login_and_register_links(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('login'), escape: false);
        $response->assertSee(route('register'), escape: false);
    }

    public function test_a_logged_in_buyer_sees_account_links_instead(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('favorites.index'), escape: false);
        $response->assertSee(route('quote-requests.history'), escape: false);
        $response->assertDontSee(route('login'), escape: false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AuthChromeTest`
Expected: FAIL — no auth-aware chrome in the layout yet.

- [ ] **Step 3: Implement**

In `resources/views/layouts/app.blade.php`, inside the `<nav>` block, right before the closing `</div>` of `#mainNav` (after the existing search `<form>`), add:

```blade
<ul class="navbar-nav ms-2">
    @guest('web')
        <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Log In</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('register') }}">Register</a></li>
    @else
        <li class="nav-item"><a class="nav-link" href="{{ route('favorites.index') }}">My Favorites</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('quote-requests.history') }}">My Quote Requests</a></li>
        <li class="nav-item">
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="nav-link btn btn-link">Log Out</button>
            </form>
        </li>
    @endguest
</ul>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AuthChromeTest`
Expected: PASS

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/app.blade.php tests/Feature/AuthChromeTest.php
git commit -m "Add auth-aware header chrome (login/register vs. account links)"
```

---

## Task 7: Full verification pass

**Files:** none (verification only)

- [ ] **Step 1: Run the complete test suite**

Run: `php artisan test`
Expected: All tests pass (existing suite + every test added in Tasks 1-6), 0 failures.

- [ ] **Step 2: Confirm no stray vendor asset diffs**

Run: `git status --short`
Expected: Only the files created/modified by Tasks 1-6 are listed. Discard any benign CRLF-only `public/css/filament/*`/`public/js/filament/*` noise per the pattern documented in `CLAUDE.md`.

- [ ] **Step 3: Confirm route ordering**

Run: `php artisan route:list`
Expected: `/register`, `/login`, `/logout`, `/favorites`, `/favorites/{product}`, `/my-quote-requests` all resolve to their new controllers; `/{slug}` (`pages.show`) remains the last GET route in the app's own route list (after Filament's own catch-alls, which are separate route groups under `/admin`/`/seller`).

- [ ] **Step 4: Confirm no unintended interaction with the Filament panels**

Run: `php artisan test --filter=SellerPanelAccessTest`
Run: `php artisan test --filter=StaffPanelAccessTest`
Expected: PASS — the `web` guard changes in this plan must not affect the separate `staff`/`seller` guards at all (three fully independent guards, per `config/auth.php`).
