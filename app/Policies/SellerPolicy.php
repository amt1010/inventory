<?php

namespace App\Policies;

use App\Models\Seller;
use App\Models\Staff;

class SellerPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['sellers.read', 'sellers.write', 'sellers.full']);
    }

    public function view(Staff $staff, Seller $seller): bool
    {
        return $staff->hasAnyPermission(['sellers.read', 'sellers.write', 'sellers.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['sellers.write', 'sellers.full']);
    }

    public function update(Staff $staff, Seller $seller): bool
    {
        return $staff->hasAnyPermission(['sellers.write', 'sellers.full']);
    }

    public function delete(Staff $staff, Seller $seller): bool
    {
        return $staff->hasPermissionTo('sellers.full');
    }
}
