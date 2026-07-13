<?php

namespace App\Policies;

use App\Models\Seller;
use App\Models\Staff;

class SellerPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function view(Staff $staff, Seller $seller): bool
    {
        return $staff->hasRole('admin');
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function update(Staff $staff, Seller $seller): bool
    {
        return $staff->hasRole('admin');
    }

    public function delete(Staff $staff, Seller $seller): bool
    {
        return $staff->hasRole('admin');
    }
}
