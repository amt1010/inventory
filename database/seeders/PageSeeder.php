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
                ['type' => 'hero_banner', 'data' => [
                    'tag' => 'B2B Sourcing Marketplace',
                    'heading' => 'Sourcing Cable & Wire — and Everything Else — Simplified',
                    'body' => 'Browse our catalog and request a quote — no account required.',
                    'search_placeholder' => 'Search for item by keyword or product number',
                    'cta_primary_label' => 'Browse Products',
                    'cta_primary_url' => '/products',
                    'cta_secondary_label' => 'Request a Quote',
                    'cta_secondary_url' => '/#rfq',
                ]],
                ['type' => 'trust_badges', 'data' => ['items' => [
                    ['icon' => 'shield-check', 'label' => 'Verified Suppliers'],
                    ['icon' => 'package-check', 'label' => 'Quality Inspected'],
                    ['icon' => 'handshake', 'label' => 'Direct Supplier Contact'],
                    ['icon' => 'message-square', 'label' => 'RFQ Support'],
                ]]],
                ['type' => 'featured_categories', 'data' => ['heading' => 'Shop by Category', 'category_ids' => []]],
                ['type' => 'deals_banner', 'data' => [
                    'heading' => 'Bulk Deals This Week',
                    'body' => 'Save on high-volume orders across select categories.',
                    'cta_label' => 'Shop Deals',
                    'cta_url' => '/products',
                ]],
                ['type' => 'featured_products', 'data' => ['heading' => 'Featured Products', 'product_ids' => []]],
                ['type' => 'rfq_form_embed', 'data' => [
                    'tag' => 'Request for Quote',
                    'heading' => "Can't find exactly what you need?",
                    'body' => "Tell us what you're looking for and our sourcing team will get back to you.",
                ]],
                ['type' => 'newsletter_signup', 'data' => [
                    'heading' => 'Get sourcing updates & deals',
                    'subheading' => 'One email a month, no spam.',
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
