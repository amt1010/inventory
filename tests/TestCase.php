<?php

namespace Tests;

use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed email templates for all tests that use RefreshDatabase
        if (in_array('Illuminate\Foundation\Testing\RefreshDatabase', class_uses($this), true)) {
            $this->seed(EmailTemplateSeeder::class);
        }
    }
}
