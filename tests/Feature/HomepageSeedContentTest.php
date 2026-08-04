<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageSeedContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_home_page_renders_every_new_block_without_error(): void
    {
        $this->seed(PageSeeder::class);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Sourcing Cable & Wire'); // assertSee escapes this for us — matches Blade's auto-escaped output
        $response->assertSee('Verified Suppliers');
        $response->assertSee('Bulk Deals This Week');
        $response->assertSee('Get sourcing updates & deals');
    }
}
