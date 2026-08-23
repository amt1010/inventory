<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\Staff;

class AuditLogPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasPermissionTo('audit_logs.full');
    }

    public function view(Staff $staff, AuditLog $auditLog): bool
    {
        return $staff->hasPermissionTo('audit_logs.full');
    }
}
