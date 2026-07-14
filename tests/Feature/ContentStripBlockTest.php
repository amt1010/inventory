<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentStripBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_content_strip_renders_heading_and_body(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'content_strip', 'data' => [
                    'heading' => 'Why Buy From Us',
                    'body' => '<p>Quality-tested inventory, fast quotes.</p>',
                    'image_position' => 'left',
                ]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Why Buy From Us');
        $response->assertSee('Quality-tested inventory, fast quotes.', escape: false);
    }

    public function test_image_position_right_puts_the_text_column_first_in_the_markup(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'content_strip', 'data' => [
                    'heading' => 'Right Positioned',
                    'body' => '<p>Body text.</p>',
                    'image' => 'page-blocks/example.jpg',
                    'image_position' => 'right',
                ]],
            ],
        ]);

        $response = $this->get('/');
        $html = $response->getContent();

        $headingPosition = strpos($html, 'Right Positioned');
        $imagePosition = strpos($html, 'page-blocks/example.jpg');

        $response->assertOk();
        $this->assertLessThan($imagePosition, $headingPosition, 'Expected the text column to precede the image column in the markup when image_position is right.');
    }
}
