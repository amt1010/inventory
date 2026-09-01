<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\Subscriber;

class SubscriberPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['subscribers.read', 'subscribers.write', 'subscribers.full']);
    }

    public function view(Staff $staff, Subscriber $subscriber): bool
    {
        return $staff->hasAnyPermission(['subscribers.read', 'subscribers.write', 'subscribers.full']);
    }

    public function create(Staff $staff): bool
    {
        return false;
    }

    public function delete(Staff $staff, Subscriber $subscriber): bool
    {
        return $staff->hasPermissionTo('subscribers.full');
    }
}
