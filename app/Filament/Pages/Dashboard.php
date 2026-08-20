<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as FilamentDashboard;

class Dashboard extends FilamentDashboard
{
    public static function canAccess(): bool
    {
        return auth('staff')->user()?->hasAnyPermission([
            'dashboard.read',
            'dashboard.write',
            'dashboard.full',
        ]) ?? false;
    }
}