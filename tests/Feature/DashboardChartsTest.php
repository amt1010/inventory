<?php

namespace Tests\Feature;

use App\Filament\Widgets\CategoriesByStatusChart;
use App\Filament\Widgets\ProductsByStatusChart;
use App\Filament\Widgets\QuoteRequestsByDateChart;
use App\Filament\Widgets\SellersByStatusChart;
use App\Filament\Widgets\StaffByRoleChart;
use App\Filament\Widgets\SubscribersOverTimeChart;
use App\Models\Category;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\Seller;
use App\Models\Staff;
use App\Models\Subscriber;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
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

    public function test_staff_by_role_chart_counts_staff_per_role(): void
    {
        Staff::factory()->create()->assignRole('admin');
        Staff::factory()->count(2)->create()->each(fn (Staff $staff) => $staff->assignRole('content_editor'));

        $counts = (new StaffByRoleChart())->roleCounts();

        $this->assertSame([
            'Admin' => 1,
            'Content Editor' => 2,
            'Sales' => 0,
        ], $counts);
    }

    public function test_subscribers_over_time_chart_counts_signups_per_day(): void
    {
        Subscriber::factory()->count(2)->create(['created_at' => now()]);
        Subscriber::factory()->count(1)->create(['created_at' => now()->subDay()]);
        // Older than the 30-day window, must not appear anywhere.
        Subscriber::factory()->count(5)->create(['created_at' => now()->subDays(45)]);

        $counts = (new SubscribersOverTimeChart())->dailyCounts();

        $this->assertCount(30, $counts);
        $this->assertSame(2, $counts[now()->toDateString()]);
        $this->assertSame(1, $counts[now()->subDay()->toDateString()]);
        $this->assertSame(0, $counts[now()->subDays(3)->toDateString()]);
        $this->assertSame(3, array_sum($counts));
    }

    public function test_quote_requests_by_date_chart_counts_requests_per_day(): void
    {
        QuoteRequest::factory()->count(2)->create(['created_at' => now()]);
        QuoteRequest::factory()->count(1)->create(['created_at' => now()->subDay()]);
        QuoteRequest::factory()->count(4)->create(['created_at' => now()->subDays(5)->setTime(23, 45)]);
        // Older than the 30-day window, must not appear anywhere.
        QuoteRequest::factory()->count(9)->create(['created_at' => now()->subDays(45)]);

        $counts = (new QuoteRequestsByDateChart())->dailyCounts();

        $this->assertCount(30, $counts);
        $this->assertSame(2, $counts[now()->toDateString()]);
        $this->assertSame(1, $counts[now()->subDay()->toDateString()]);
        $this->assertSame(4, $counts[now()->subDays(5)->toDateString()]);
        $this->assertSame(0, $counts[now()->subDays(3)->toDateString()]);
        $this->assertSame(7, array_sum($counts));
    }

    /**
     * Each of these three widgets draws one differently-colored bar per
     * status, but Chart.js's default legend renderer only shows a single
     * swatch for the whole dataset, and it doesn't reflect the per-bar
     * colors correctly. Each widget must override getOptions() with a
     * generateLabels callback so the legend shows one correctly-colored
     * entry per status.
     */
    public function test_products_by_status_chart_options_generate_one_legend_label_per_bar(): void
    {
        $method = new \ReflectionMethod(ProductsByStatusChart::class, 'getOptions');
        $method->setAccessible(true);
        $js = (string) $method->invoke(new ProductsByStatusChart());

        $this->assertStringContainsString('generateLabels', $js);
    }

    public function test_categories_by_status_chart_options_generate_one_legend_label_per_bar(): void
    {
        $method = new \ReflectionMethod(CategoriesByStatusChart::class, 'getOptions');
        $method->setAccessible(true);
        $js = (string) $method->invoke(new CategoriesByStatusChart());

        $this->assertStringContainsString('generateLabels', $js);
    }

    public function test_sellers_by_status_chart_options_generate_one_legend_label_per_bar(): void
    {
        $method = new \ReflectionMethod(SellersByStatusChart::class, 'getOptions');
        $method->setAccessible(true);
        $js = (string) $method->invoke(new SellersByStatusChart());

        $this->assertStringContainsString('generateLabels', $js);
    }

    /**
     * These three charts label each bar via the legend (generateLabels
     * above), so both axes' tick labels are redundant -- the x-axis repeats
     * the same status names the legend already shows under each swatch, and
     * the y-axis repeats the counts each bar's height already conveys.
     */
    public function test_products_by_status_chart_hides_both_axes_ticks(): void
    {
        $method = new \ReflectionMethod(ProductsByStatusChart::class, 'getOptions');
        $method->setAccessible(true);
        $js = (string) $method->invoke(new ProductsByStatusChart());

        $this->assertSame(2, substr_count($js, 'ticks: { display: false }'));
    }

    public function test_categories_by_status_chart_hides_both_axes_ticks(): void
    {
        $method = new \ReflectionMethod(CategoriesByStatusChart::class, 'getOptions');
        $method->setAccessible(true);
        $js = (string) $method->invoke(new CategoriesByStatusChart());

        $this->assertSame(2, substr_count($js, 'ticks: { display: false }'));
    }

    public function test_sellers_by_status_chart_hides_both_axes_ticks(): void
    {
        $method = new \ReflectionMethod(SellersByStatusChart::class, 'getOptions');
        $method->setAccessible(true);
        $js = (string) $method->invoke(new SellersByStatusChart());

        $this->assertSame(2, substr_count($js, 'ticks: { display: false }'));
    }

    /**
     * Unlike the three status charts, this one has no per-bar legend
     * (legend is disabled outright), so its y-axis counts must stay visible.
     */
    public function test_quote_requests_chart_keeps_the_y_axis_ticks_visible(): void
    {
        QuoteRequest::factory()->create(['created_at' => now()]);

        $method = new \ReflectionMethod(QuoteRequestsByDateChart::class, 'getOptions');
        $method->setAccessible(true);
        $js = $method->invoke(new QuoteRequestsByDateChart())->toHtml();

        $this->assertStringNotContainsString('ticks:', $js);
    }

    /**
     * Filament renders a chart widget's options with `@js()` straight into the
     * `x-data="chart({...})"` attribute. Unlike the dataset, RawJs is emitted
     * verbatim, so a single raw `"` in it closes the attribute early, Alpine
     * gets a syntax error, and the canvas stays blank.
     */
    public function test_quote_requests_chart_options_js_is_safe_inside_a_double_quoted_html_attribute(): void
    {
        QuoteRequest::factory()->create(['created_at' => now()]);

        $method = new \ReflectionMethod(QuoteRequestsByDateChart::class, 'getOptions');
        $method->setAccessible(true);
        $js = $method->invoke(new QuoteRequestsByDateChart())->toHtml();

        $this->assertStringNotContainsString('"', $js);
    }

    public function test_the_quote_requests_chart_x_data_attribute_is_not_truncated_on_the_dashboard(): void
    {
        QuoteRequest::factory()->create(['created_at' => now()]);

        $staff = Staff::factory()->create();
        $staff->assignRole('admin');
        $this->actingAs($staff, 'staff');

        $html = $this->get('/admin')->assertOk()->getContent();

        preg_match_all('/x-data="([^"]*)"/', $html, $matches);

        $chartAttribute = collect($matches[1])->first(
            fn (string $value): bool => str_contains($value, 'onClick')
        );

        $this->assertNotNull($chartAttribute, 'The quote requests chart x-data attribute was not found.');
        // The whole `chart({...})` call has to survive inside one attribute.
        $this->assertStringContainsString("type: 'bar'", $chartAttribute);
        $this->assertStringEndsWith('})', trim($chartAttribute));
    }

    public function test_an_admin_sees_all_six_dashboard_charts(): void
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
        $response->assertSee('Staff by Role');
        $response->assertSee('Subscribers Over Time');
    }

    public function test_a_content_editor_does_not_see_the_sellers_quote_requests_staff_or_subscribers_charts(): void
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
        $response->assertDontSee('Staff by Role');
        $response->assertDontSee('Subscribers Over Time');
    }

    public function test_sales_sees_the_quote_requests_and_subscribers_charts_but_not_the_sellers_or_staff_charts(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('sales');
        $this->actingAs($staff, 'staff');

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('Quote Requests by Date');
        $response->assertSee('Subscribers Over Time');
        $response->assertDontSee('Sellers by Status');
        $response->assertDontSee('Staff by Role');
    }
}
