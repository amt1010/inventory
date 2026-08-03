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

        Page::query()->firstOrCreate(['slug' => 'terms-and-conditions'], [
            'title' => 'Terms & Conditions',
            'status' => 'published',
            'content' => [
                ['type' => 'content_strip', 'data' => [
                    'heading' => 'Terms & Conditions',
                    'body' => '<p>Welcome to our platform. By creating an account, you agree to use this '
                        .'site for legitimate sourcing and supply purposes only. Quote requests are '
                        .'non-binding enquiries; final pricing and terms are negotiated directly between '
                        .'buyer and seller off-platform. We do not process payments on this site.</p>'
                        .'<p>Update this content any time from <strong>Admin &rsaquo; Pages &rsaquo; '
                        .'Terms & Conditions</strong>.</p>',
                    'image_position' => 'left',
                ]],
            ],
        ]);
    }
}
