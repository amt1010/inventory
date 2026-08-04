<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\NavItem;
use App\Models\Setting;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Give the Filament admin/seller panels a full-height vertical divider
        // between the sidebar and the content. The sidebar is a sticky,
        // viewport-tall element, so a border on its content-facing edge reads
        // as a continuous separator that stays put as the page scrolls —
        // instead of the sidebar blending into the content on short pages.
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): string => <<<'HTML'
                <style>
                    @media (min-width: 1024px) {
                        .fi-sidebar { border-inline-end: 1px solid rgb(228 228 231); }
                        .dark .fi-sidebar { border-inline-end-color: rgb(255 255 255 / 0.1); }
                    }
                </style>
                HTML,
        );

        View::composer('layouts.app', function ($view) {
            $view->with('headerNavItems', NavItem::query()
                ->whereNull('parent_id')
                ->where('location', 'header')
                ->with('children')
                ->orderBy('sort_order')
                ->get());

            $view->with('footerNavItems', NavItem::query()
                ->whereNull('parent_id')
                ->where('location', 'footer')
                ->orderBy('sort_order')
                ->get());

            $view->with('siteSettings', Setting::current());

            $view->with('topLevelCategories', Category::query()
                ->whereNull('parent_id')
                ->where('status', 'published')
                ->with(['children' => fn ($query) => $query->where('status', 'published')->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get());
        });
    }
}
