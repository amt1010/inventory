<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\Staff;

class PagePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function view(Staff $staff, Page $page): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor', 'sales']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function update(Staff $staff, Page $page): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }

    public function delete(Staff $staff, Page $page): bool
    {
        return $staff->hasAnyRole(['admin', 'content_editor']);
    }
}
