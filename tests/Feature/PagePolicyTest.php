<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_content_editor_can_create_and_update_pages(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $this->assertTrue($editor->can('create', Page::class));

        $page = Page::factory()->create();
        $this->assertTrue($editor->can('update', $page));
    }

    public function test_sales_can_view_but_not_create_or_update_pages(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $page = Page::factory()->create();

        $this->assertTrue($sales->can('viewAny', Page::class));
        $this->assertFalse($sales->can('create', Page::class));
        $this->assertFalse($sales->can('update', $page));
    }
}
