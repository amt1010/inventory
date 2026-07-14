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
                [
                    'type' => 'hero',
                    'data' => [
                        'heading' => 'Sourcing Cable & Wire, Simplified',
                        'subheading' => 'Browse our catalog and request a quote — no account required.',
                        'cta_label' => 'Browse Products',
                        'cta_url' => '/products',
                    ],
                ],
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
