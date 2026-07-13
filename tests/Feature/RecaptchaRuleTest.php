<?php

namespace Tests\Feature;

use App\Rules\Recaptcha;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RecaptchaRuleTest extends TestCase
{
    public function test_validation_passes_when_recaptcha_is_not_configured(): void
    {
        config(['services.recaptcha.secret_key' => null]);

        $validator = Validator::make(['g-recaptcha-response' => ''], [
            'g-recaptcha-response' => [new Recaptcha()],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_when_configured_and_google_rejects_the_token(): void
    {
        config(['services.recaptcha.site_key' => 'test-site', 'services.recaptcha.secret_key' => 'test-secret']);
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false]),
        ]);

        $validator = Validator::make(['g-recaptcha-response' => 'bad-token'], [
            'g-recaptcha-response' => [new Recaptcha()],
        ]);

        $this->assertFalse($validator->passes());
    }

    public function test_validation_passes_when_configured_and_google_accepts_the_token(): void
    {
        config(['services.recaptcha.site_key' => 'test-site', 'services.recaptcha.secret_key' => 'test-secret']);
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $validator = Validator::make(['g-recaptcha-response' => 'good-token'], [
            'g-recaptcha-response' => [new Recaptcha()],
        ]);

        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_when_only_the_secret_key_is_configured_and_the_site_key_is_missing(): void
    {
        config(['services.recaptcha.secret_key' => 'test-secret', 'services.recaptcha.site_key' => null]);

        $validator = Validator::make(['g-recaptcha-response' => ''], [
            'g-recaptcha-response' => [new Recaptcha()],
        ]);

        $this->assertTrue($validator->passes());
    }
}
