<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_page_can_be_unpublished(): void
    {
        $page = Page::factory()->create(['status' => 'published']);

        $page->unpublish();

        $this->assertSame('draft', $page->fresh()->status);
    }
}
