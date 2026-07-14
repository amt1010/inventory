<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\Staff;

class SettingPolicy
{
    public function manage(Staff $staff, ?Setting $setting = null): bool
    {
        return $staff->hasRole('admin');
    }
}
