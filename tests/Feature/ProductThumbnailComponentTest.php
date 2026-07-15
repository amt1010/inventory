<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProductThumbnailComponentTest extends TestCase
{
    public function test_it_renders_a_fixed_size_image_when_given_a_path(): void
    {
        $html = Blade::render('<x-product-thumbnail path="product-images/cable.jpg" alt="Test Product" />');

        $this->assertStringContainsString('width="132"', $html);
        $this->assertStringContainsString('height="132"', $html);
        $this->assertStringContainsString('product-images/cable.jpg', $html);
        $this->assertStringContainsString('alt="Test Product"', $html);
    }

    public function test_it_renders_nothing_when_given_no_path(): void
    {
        $html = Blade::render('<x-product-thumbnail :path="null" alt="Test Product" />');

        $this->assertSame('', trim($html));
    }
}
