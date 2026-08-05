<?php

namespace Tests\Unit;

use App\Services\SellerCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SellerCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_formats_the_code_as_yymmddhhmm_plus_s_plus_a_five_digit_sequence(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 5, 14, 23, 0));

        $code = (new SellerCodeGenerator())->generate();

        $this->assertSame('2608051423', substr($code, 0, 10));
        $this->assertSame('S', substr($code, 10, 1));
        $this->assertSame('00001', substr($code, 11, 5));
        $this->assertMatchesRegularExpression('/^\d{10}S\d{5}$/', $code);

        Carbon::setTestNow();
    }

    public function test_sequence_increments_for_repeated_calls_within_the_same_minute(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 5, 14, 23, 0));

        $generator = new SellerCodeGenerator();
        $first = $generator->generate();
        $second = $generator->generate();
        $third = $generator->generate();

        $this->assertSame('00001', substr($first, 11, 5));
        $this->assertSame('00002', substr($second, 11, 5));
        $this->assertSame('00003', substr($third, 11, 5));

        Carbon::setTestNow();
    }

    public function test_sequence_resets_in_a_new_minute(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 5, 14, 23, 59));
        $generator = new SellerCodeGenerator();
        $lastOfMinute = $generator->generate();

        Carbon::setTestNow(Carbon::create(2026, 8, 5, 14, 24, 0));
        $firstOfNextMinute = $generator->generate();

        $this->assertSame('00001', substr($lastOfMinute, 11, 5));
        $this->assertSame('00001', substr($firstOfNextMinute, 11, 5));
        $this->assertNotSame(substr($lastOfMinute, 0, 10), substr($firstOfNextMinute, 0, 10));

        Carbon::setTestNow();
    }
}
