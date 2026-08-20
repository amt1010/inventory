<?php

namespace App\Policies;

use App\Models\Staff;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function view(Staff $staff, Role $role): bool
    {
        return $staff->hasRole('admin');
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function update(Staff $staff, Role $role): bool
    {
        return $staff->hasRole('admin');
    }

    public function delete(Staff $staff, Role $role): bool
    {
        return $staff->hasRole('admin');
    }
}
