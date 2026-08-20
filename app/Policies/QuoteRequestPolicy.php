<?php

namespace App\Policies;

use App\Models\QuoteRequest;
use App\Models\Staff;

class QuoteRequestPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyPermission(['quote_requests.read', 'quote_requests.write', 'quote_requests.full']);
    }

    public function view(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasAnyPermission(['quote_requests.read', 'quote_requests.write', 'quote_requests.full']);
    }

    public function create(Staff $staff): bool
    {
        return false;
    }

    public function update(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasAnyPermission(['quote_requests.write', 'quote_requests.full']);
    }

    public function delete(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasPermissionTo('quote_requests.full');
    }
}
