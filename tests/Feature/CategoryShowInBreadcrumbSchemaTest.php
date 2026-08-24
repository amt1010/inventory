<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryShowInBreadcrumbSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_in_breadcrumb_defaults_to_true(): void
    {
        $category = Category::factory()->create();

        $this->assertTrue((bool) $category->fresh()->show_in_breadcrumb);
    }

    public function test_show_in_breadcrumb_can_be_set_to_false(): void
    {
        $category = Category::factory()->create(['show_in_breadcrumb' => false]);

        $this->assertFalse((bool) $category->fresh()->show_in_breadcrumb);
    }
}
