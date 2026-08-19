<?php

namespace Tests\Feature;

use App\Filament\Widgets\CategoriesByStatusChart;
use App\Filament\Widgets\ProductsByStatusChart;
use App\Filament\Widgets\QuoteRequestsByDateChart;
use App\Filament\Widgets\SellersByStatusChart;
use App\Models\Category;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_categories_by_status_chart_counts_each_status(): void
    {
        Category::factory()->count(2)->create(['status' => 'draft']);
        Category::factory()->count(3)->create(['status' => 'published']);

        $counts = (new CategoriesByStatusChart())->statusCounts();

        $this->assertSame(['Draft' => 2, 'Published' => 3], $counts);
    }

    public function test_products_by_status_chart_counts_each_status(): void
    {
        Product::factory()->count(2)->create(['status' => 'pending_review']);
        Product::factory()->count(1)->create(['status' => 'published']);
        Product::factory()->count(4)->create(['status' => 'rejected']);

        $counts = (new ProductsByStatusChart())->statusCounts();

        $this->assertSame([
            'Pending Review' => 2,
            'Published' => 1,
            'Rejected' => 4,
            'Archived' => 0,
            'Pending Seller Acceptance' => 0,
        ], $counts);
    }

    public function test_sellers_by_status_chart_counts_each_status(): void
    {
        Seller::factory()->count(1)->create(['status' => 'approved']);
        Seller::factory()->count(2)->create(['status' => 'pending_admin_approval']);

        $counts = (new SellersByStatusChart())->statusCounts();

        $this->assertSame([
            'Pending Email Verification' => 0,
            'Pending Admin Approval' => 2,
            'Approved' => 1,
            'Rejected' => 0,
            'Suspended' => 0,
        ], $counts);
    }

    public function test_quote_requests_by_date_chart_counts_requests_per_day(): void
    {
        QuoteRequest::factory()->count(2)->create(['created_at' => now()]);
        QuoteRequest::factory()->count(1)->create(['created_at' => now()->subDay()]);

        $counts = (new QuoteRequestsByDateChart())->dailyCounts();

        $this->assertSame(2, $counts[now()->toDateString()]);
        $this->assertSame(1, $counts[now()->subDay()->toDateString()]);
    }

    public function test_an_admin_sees_all_four_dashboard_charts(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('admin');
        $this->actingAs($staff, 'staff');

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('Categories by Status');
        $response->assertSee('Products by Status');
        $response->assertSee('Sellers by Status');
        $response->assertSee('Quote Requests by Date');
    }

    public function test_a_content_editor_does_not_see_the_sellers_or_quote_requests_charts(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('content_editor');
        $this->actingAs($staff, 'staff');

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('Categories by Status');
        $response->assertSee('Products by Status');
        $response->assertDontSee('Sellers by Status');
        $response->assertDontSee('Quote Requests by Date');
    }

    public function test_sales_sees_the_quote_requests_chart_but_not_the_sellers_chart(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('sales');
        $this->actingAs($staff, 'staff');

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('Quote Requests by Date');
        $response->assertDontSee('Sellers by Status');
    }
}
