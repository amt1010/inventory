<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\Staff;

class CategoryPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['categories.read', 'categories.write', 'categories.full']);
    }

    public function view(Staff $staff, Category $category): bool
    {
        return $staff->hasAnyPermission(['categories.read', 'categories.write', 'categories.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['categories.write', 'categories.full']);
    }

    public function update(Staff $staff, Category $category): bool
    {
        return $staff->hasAnyPermission(['categories.write', 'categories.full']);
    }

    public function delete(Staff $staff, Category $category): bool
    {
        return $staff->hasPermissionTo('categories.full');
    }
}
