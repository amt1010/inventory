<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Staff;

class ProductPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['products.read', 'products.write', 'products.full']);
    }

    public function view(Staff $staff, Product $product): bool
    {
        return $staff->hasAnyPermission(['products.read', 'products.write', 'products.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['products.write', 'products.full']);
    }

    public function update(Staff $staff, Product $product): bool
    {
        return $staff->hasAnyPermission(['products.write', 'products.full']);
    }

    public function delete(Staff $staff, Product $product): bool
    {
        return $staff->hasPermissionTo('products.full');
    }

    public function setPrice(Staff $staff): bool
    {
        return $staff->hasPermissionTo('products.full');
    }

    public function approve(Staff $staff): bool
    {
        return $staff->hasPermissionTo('products.full');
    }
}
