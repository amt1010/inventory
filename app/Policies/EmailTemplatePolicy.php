<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\Staff;

class EmailTemplatePolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['email_templates.read', 'email_templates.write', 'email_templates.full']);
    }

    public function view(Staff $staff, EmailTemplate $emailTemplate): bool
    {
        return $staff->hasAnyPermission(['email_templates.read', 'email_templates.write', 'email_templates.full']);
    }

    public function create(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['email_templates.write', 'email_templates.full']);
    }

    public function update(Staff $staff, EmailTemplate $emailTemplate): bool
    {
        return $staff->hasAnyPermission(['email_templates.write', 'email_templates.full']);
    }

    public function delete(Staff $staff, EmailTemplate $emailTemplate): bool
    {
        return ! $emailTemplate->is_system && $staff->hasPermissionTo('email_templates.full');
    }
}
