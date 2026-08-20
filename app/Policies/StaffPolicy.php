<?php

namespace App\Policies;

use App\Models\Staff;

class StaffPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasRole('admin');
            return $staff->hasAnyPermission(['staff.read', 'staff.write', 'staff.full']);
    }

    public function view(Staff $staff, Staff $model): bool
    {
        return $staff->hasAnyPermission(['staff.read', 'staff.write', 'staff.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['staff.write', 'staff.full']);
    }

    public function update(Staff $staff, Staff $model): bool
    {
        return $staff->hasAnyPermission(['staff.write', 'staff.full']);
    }

    public function delete(Staff $staff, Staff $model): bool
    {
        return $staff->hasPermissionTo('staff.full');
    }
}
