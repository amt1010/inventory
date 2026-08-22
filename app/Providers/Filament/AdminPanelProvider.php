<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureStaffPasswordIsCurrent;
use App\Models\Setting;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        Import::polymorphicUserRelationship();

        // Makes the sidebar/content divider draggable. Registered per panel and
        // guarded on the current panel id rather than passed as `scopes:` --
        // render-hook scopes match Livewire component classes, not panel ids,
        // and both panel providers boot on every request, so an unguarded
        // registration in each would inject the partial twice. See the same
        // pattern in SellerPanelProvider::boot().
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): View|string => Filament::getCurrentPanel()?->getId() === 'admin'
                ? view('filament.partials.resizable-sidebar')
                : '',
        );
    }

    public function panel(Panel $panel): Panel
    {
        // Panel providers boot once per process (not per request under a
        // long-running server), but PHP-FPM's traditional one-request-per-boot
        // model means this has never surfaced -- still, resolving branding via
        // closures rather than baking in values read here keeps it correct
        // either way, and matches the public layout's view-composer pattern
        // (AppServiceProvider::boot()), which already re-reads on every render.
        $branding = fn () => Schema::hasTable('settings') ? Setting::current() : null;
        $brandName = fn () => filled($branding()?->site_name) ? $branding()->site_name : config('app.name');

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('staff')
            ->favicon(asset('favicon.svg'))
            ->colors(fn () => [
                'primary' => Color::hex(filled($branding()?->theme_accent_color) ? $branding()->theme_accent_color : '#ff6a00'),
            ])
            ->brandName($brandName)
            ->brandLogo(fn () => $branding()?->logo_path
                ? view('filament.partials.brand', [
                    'logoUrl' => asset('storage/'.$branding()->logo_path),
                    'brandName' => $brandName(),
                ])
                : null)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
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
                EnsureStaffPasswordIsCurrent::class,
            ]);
    }
}
