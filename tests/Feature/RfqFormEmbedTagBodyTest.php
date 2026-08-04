<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqFormEmbedTagBodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_optional_tag_and_body(): void
    {
        Page::factory()->create([
            'slug' => 'home',
            'status' => 'published',
            'content' => [
                ['type' => 'rfq_form_embed', 'data' => [
                    'heading' => "Can't find exactly what you need?",
                    'tag' => 'Request for Quote',
                    'body' => "Tell us what you're looking for and our sourcing team will get back to you.",
                ]],
            ],
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Request for Quote');
        $response->assertSee("Tell us what you're looking for");
    }

    public function test_tag_and_body_are_optional_and_the_form_still_renders_without_them(): void
    {
        Page::factory()->create([
            'slug' => 'contact-us',
            'status' => 'published',
            'content' => [
                ['type' => 'rfq_form_embed', 'data' => ['heading' => 'Get in Touch']],
            ],
        ]);

        $response = $this->get('/contact-us');

        $response->assertOk();
        $response->assertSee('Get in Touch');
        $response->assertSee(route('quote-requests.store'), escape: false);
    }
}
