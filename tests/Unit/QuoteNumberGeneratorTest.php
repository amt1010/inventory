<?php

namespace Tests\Unit;

use App\Services\QuoteNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QuoteNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_formats_the_number_as_yymmddhhmm_plus_a_four_digit_sequence(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 17, 30, 0));

        $number = (new QuoteNumberGenerator())->generate();

        $this->assertSame('2608031730', substr($number, 0, 10));
        $this->assertSame('0001', substr($number, 10, 4));
        $this->assertMatchesRegularExpression('/^\d{14}$/', $number);

        Carbon::setTestNow();
    }

    public function test_sequence_increments_for_repeated_calls_within_the_same_minute(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 17, 30, 0));

        $generator = new QuoteNumberGenerator();
        $first = $generator->generate();
        $second = $generator->generate();
        $third = $generator->generate();

        $this->assertSame('0001', substr($first, 10, 4));
        $this->assertSame('0002', substr($second, 10, 4));
        $this->assertSame('0003', substr($third, 10, 4));

        Carbon::setTestNow();
    }

    public function test_sequence_resets_in_a_new_minute(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 17, 30, 59));
        $generator = new QuoteNumberGenerator();
        $lastOfMinute = $generator->generate();

        Carbon::setTestNow(Carbon::create(2026, 8, 3, 17, 31, 0));
        $firstOfNextMinute = $generator->generate();

        $this->assertSame('0001', substr($lastOfMinute, 10, 4));
        $this->assertSame('0001', substr($firstOfNextMinute, 10, 4));
        $this->assertNotSame(substr($lastOfMinute, 0, 10), substr($firstOfNextMinute, 0, 10));

        Carbon::setTestNow();
    }
}
