<?php

namespace App\Policies;

use App\Models\QuoteRequest;
use App\Models\Staff;

class QuoteRequestPolicy
{
    public function viewAny(Staff $staff): bool
    {
        return $staff->hasAnyRole(['admin', 'sales']);
    }

    public function view(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasAnyRole(['admin', 'sales']);
    }

    public function create(Staff $staff): bool
    {
        return false;
    }

    public function update(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasAnyRole(['admin', 'sales']);
    }

    public function delete(Staff $staff, QuoteRequest $quoteRequest): bool
    {
        return $staff->hasRole('admin');
    }
}
