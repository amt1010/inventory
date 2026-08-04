<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustBadgesBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_a_label_and_icon_for_each_badge(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'trust_badges', 'data' => ['items' => [
                    ['icon' => 'shield-check', 'label' => 'Verified Suppliers'],
                    ['icon' => 'package-check', 'label' => 'Quality Inspected'],
                ]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Verified Suppliers');
        $response->assertSee('Quality Inspected');
        $response->assertSee('<svg', escape: false);
    }

    public function test_a_badge_with_an_unrecognized_icon_still_renders_its_label(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'trust_badges', 'data' => ['items' => [
                    ['icon' => 'not-a-real-icon', 'label' => 'Direct Supplier Contact'],
                ]]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Direct Supplier Contact');
    }
}
