<?php

namespace App\Providers\Filament;

use App\Models\Setting;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SellerPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            function (): Htmlable {
                // AUTH_LOGIN_FORM_AFTER isn't scopable by panel in this Filament
                // version -- both panels render the same Filament\Pages\Auth\Login
                // class, so `registerRenderHook(..., scopes: 'seller')` never
                // matches. Registering unscoped and checking the current panel at
                // render time is what actually confines this to /seller/login.
                if (Filament::getCurrentPanel()?->getId() !== 'seller') {
                    return new HtmlString('');
                }

                return new HtmlString(
                    '<p class="mt-4 text-center">New seller? <a href="'.route('seller.register').'">Register here</a></p>'
                );
            },
        );

        // Makes the sidebar/content divider draggable. Same manual panel guard
        // as above: both panel providers boot on every request, so registering
        // this unscoped in each one would inject the partial twice.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): View|string => Filament::getCurrentPanel()?->getId() === 'seller'
                ? view('filament.partials.resizable-sidebar')
                : '',
        );
    }

    public function panel(Panel $panel): Panel
    {
        // Reading branding from the database at boot means this must survive
        // the `settings` table (or its seller_* columns) not existing yet --
        // this provider boots on every artisan invocation, including the
        // `migrate` command that creates that very table.
        $branding = Schema::hasTable('settings') ? Setting::current() : null;
        $accentColor = filled($branding?->theme_accent_color)
            ? $branding->theme_accent_color
            : '#ff6a00';
        $brandName = filled($branding?->site_name)
            ? $branding->site_name
            : config('app.name');

        return $panel
            ->id('seller')
            ->path('seller')
            ->login()
            ->authGuard('seller')
            ->colors([
                'primary' => Color::hex($accentColor),
            ])
            ->brandName($brandName)
            ->brandLogo($branding?->logo_path ? asset('storage/'.$branding->logo_path) : null)
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
