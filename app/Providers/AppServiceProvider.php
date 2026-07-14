<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\NavItem;
use App\Models\Setting;
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
