<?php

namespace Tests\Feature;

use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerCodeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_seller_is_assigned_a_seller_code_automatically(): void
    {
        $seller = Seller::factory()->create();

        $this->assertNotNull($seller->seller_code);
        $this->assertMatchesRegularExpression('/^\d{10}S\d{5}$/', $seller->seller_code);
    }

    public function test_two_sellers_created_in_the_same_minute_get_different_seller_codes(): void
    {
        $first = Seller::factory()->create();
        $second = Seller::factory()->create();

        $this->assertNotSame($first->seller_code, $second->seller_code);
    }

    public function test_email_no_longer_needs_to_be_unique_at_the_database_level(): void
    {
        Seller::factory()->create(['email' => 'shared@example.com']);
        $second = Seller::factory()->create(['email' => 'shared@example.com']);

        $this->assertSame('shared@example.com', $second->fresh()->email);
    }

    public function test_the_placeholder_constant_is_the_literal_string_to_be_added(): void
    {
        $this->assertSame('To be Added', Seller::PLACEHOLDER);
    }
}
