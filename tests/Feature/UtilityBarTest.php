<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityBarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Page::factory()->create(['slug' => 'home', 'status' => 'published']);
    }

    public function test_the_utility_bar_links_to_seller_registration(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Become a Seller');
        $response->assertSee(route('seller.register'), escape: false);
    }

    public function test_the_utility_bar_shows_a_help_center_link_when_that_page_exists(): void
    {
        Page::factory()->create(['slug' => 'help-center', 'status' => 'published', 'title' => 'Help Center']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Help Center');
        $response->assertSee('/help-center', escape: false);
    }

    public function test_the_utility_bar_omits_the_help_center_link_when_no_such_page_exists(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('Help Center');
    }
}
