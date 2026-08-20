<?php

namespace App\Policies;

use App\Models\Staff;

class StaffPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function view(Staff $staff, Staff $model): bool
    {
        return $staff->hasRole('admin');
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function update(Staff $staff, Staff $model): bool
    {
        return $staff->hasRole('admin');
    }

    public function delete(Staff $staff, Staff $model): bool
    {
        return $staff->hasRole('admin');
    }
}
