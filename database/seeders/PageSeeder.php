<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        Page::query()->firstOrCreate(['slug' => 'home'], [
            'title' => 'Home',
            'status' => 'published',
            'content' => [
                ['type' => 'hero_carousel', 'data' => ['slides' => [
                    [
                        'media_type' => 'image',
                        'heading' => 'Sourcing Cable & Wire, Simplified',
                        'subheading' => 'Browse our catalog and request a quote — no account required.',
                        'cta_label' => 'Browse Products',
                        'cta_url' => '/products',
                        'active' => true,
                    ],
                ]]],
                ['type' => 'content_strip', 'data' => [
                    'heading' => 'Why Buy From Us',
                    'body' => '<p>Every listing is reviewed and priced by our sourcing team before it goes live, so you always know you\'re getting quality-tested inventory at a fair price.</p>',
                    'image_position' => 'left',
                ]],
            ],
        ]);

        Page::query()->firstOrCreate(['slug' => 'contact-us'], [
            'title' => 'Contact Us',
            'status' => 'published',
            'content' => [
                [
                    'type' => 'rfq_form_embed',
                    'data' => ['heading' => 'Get in Touch'],
                ],
            ],
        ]);
    }
}
