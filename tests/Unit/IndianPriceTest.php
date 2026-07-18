<?php

namespace Tests\Unit;

use App\Support\IndianPrice;
use PHPUnit\Framework\TestCase;

class IndianPriceTest extends TestCase
{
    public function test_it_adds_the_rupee_symbol_and_groups_a_single_price(): void
    {
        $this->assertSame('₹1,200', IndianPrice::format('1200'));
    }

    public function test_it_uses_indian_grouping_for_large_numbers(): void
    {
        $this->assertSame('₹1,00,000', IndianPrice::format('100000'));
        $this->assertSame('₹1,23,45,678', IndianPrice::format('12345678'));
    }

    public function test_it_formats_both_numbers_in_a_range_and_keeps_words(): void
    {
        $this->assertSame('₹1,200 – ₹1,800 per reel', IndianPrice::format('1200 – 1800 per reel'));
    }

    public function test_it_is_idempotent_and_does_not_double_the_symbol_or_commas(): void
    {
        $formatted = IndianPrice::format('₹1,00,000');
        $this->assertSame('₹1,00,000', $formatted);
        $this->assertSame('₹1,00,000', IndianPrice::format($formatted));
    }

    public function test_it_leaves_empty_and_null_values_untouched(): void
    {
        $this->assertNull(IndianPrice::format(null));
        $this->assertSame('', IndianPrice::format(''));
        $this->assertSame('  ', IndianPrice::format('  '));
    }

    public function test_it_leaves_text_without_numbers_untouched(): void
    {
        $this->assertSame('Contact for pricing', IndianPrice::format('Contact for pricing'));
    }
}
