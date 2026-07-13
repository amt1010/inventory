<?php

namespace Tests\Feature;

use App\Models\QuoteRequest;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_sales_and_admin_can_view_quote_requests_but_content_editor_cannot(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $this->assertTrue($sales->can('viewAny', QuoteRequest::class));
        $this->assertTrue($admin->can('viewAny', QuoteRequest::class));
        $this->assertFalse($editor->can('viewAny', QuoteRequest::class));
    }

    public function test_only_admin_can_delete_a_quote_request(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $quoteRequest = QuoteRequest::factory()->create();

        $this->assertFalse($sales->can('delete', $quoteRequest));
        $this->assertTrue($admin->can('delete', $quoteRequest));
    }

    public function test_no_one_can_create_a_quote_request_through_the_policy(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $this->assertFalse($admin->can('create', QuoteRequest::class));
    }
}
