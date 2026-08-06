<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerImportMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_table_has_a_stuck_notified_at_column(): void
    {
        $this->assertTrue(Schema::hasColumn('imports', 'stuck_notified_at'));
    }
}
