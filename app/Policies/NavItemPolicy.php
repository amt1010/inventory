<?php

namespace App\Policies;

use App\Models\NavItem;
use App\Models\Staff;

class NavItemPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function view(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function update(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function delete(Staff $staff, NavItem $navItem): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }
}
