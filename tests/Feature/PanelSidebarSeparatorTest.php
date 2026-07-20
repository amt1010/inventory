<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelSidebarSeparatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seller_panel_renders_a_full_height_sidebar_divider(): void
    {
        $response = $this->get('/seller/login');

        $response->assertOk();
        $response->assertSee('.fi-sidebar { border-inline-end', escape: false);
    }

    public function test_the_admin_panel_renders_a_full_height_sidebar_divider(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('.fi-sidebar { border-inline-end', escape: false);
    }
}
