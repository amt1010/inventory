<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Staff;

class ProductPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function view(Staff $staff, Product $product): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function update(Staff $staff, Product $product): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function delete(Staff $staff, Product $product): bool
    {
        return $staff->hasRole('admin');
    }

    public function setPrice(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }

    public function approve(Staff $staff): bool
    {
        return $staff->hasRole('admin');
    }
}
