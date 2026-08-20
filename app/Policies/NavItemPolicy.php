<?php

namespace App\Policies;

use App\Models\NavItem;
use App\Models\Staff;

class NavItemPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['nav_items.read', 'nav_items.write', 'nav_items.full']);
    }

    public function view(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasAnyPermission(['nav_items.read', 'nav_items.write', 'nav_items.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['nav_items.write', 'nav_items.full']);
    }

    public function update(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasAnyPermission(['nav_items.write', 'nav_items.full']);
    }

    public function delete(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasPermissionTo('nav_items.full');
    }
}
