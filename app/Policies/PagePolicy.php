<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\Staff;

class PagePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['pages.read', 'pages.write', 'pages.full']);
    }

    public function view(Staff $staff, Page $page): bool
    {
        return $staff->hasAnyPermission(['pages.read', 'pages.write', 'pages.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['pages.write', 'pages.full']);
    }

    public function update(Staff $staff, Page $page): bool
    {
        return $staff->hasAnyPermission(['pages.write', 'pages.full']);
    }

    public function delete(Staff $staff, Page $page): bool
    {
        return $staff->hasPermissionTo('pages.full');
    }
}
