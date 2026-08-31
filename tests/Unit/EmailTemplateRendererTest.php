<?php

namespace Tests\Unit;

use App\Services\EmailTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    private function renderer(): EmailTemplateRenderer
    {
        return new EmailTemplateRenderer();
    }

    public function test_substitutes_a_scalar_token(): void
    {
        $html = $this->renderer()->render('<p>Hello {{name}}</p>', ['name' => 'Asha']);

        $this->assertSame('<p>Hello Asha</p>', $html);
    }

    public function test_escapes_a_scalar_token_by_default(): void
    {
        $html = $this->renderer()->render('<p>{{name}}</p>', ['name' => '<script>alert(1)</script>']);

        $this->assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $html);
    }

    public function test_does_not_escape_a_token_ending_in_html(): void
    {
        $html = $this->renderer()->render('{{thumbnail_html}}', ['thumbnail_html' => '<img src="x.jpg">']);

        $this->assertSame('<img src="x.jpg">', $html);
    }

    public function test_leaves_an_unknown_token_as_literal_text(): void
    {
        $html = $this->renderer()->render('<p>{{unknown_token}}</p>', []);

        $this->assertSame('<p>{{unknown_token}}</p>', $html);
    }

    public function test_keeps_a_section_when_its_token_is_present(): void
    {
        $html = $this->renderer()->render(
            '<p>Before</p>{{#reason}}<p>Reason: {{reason}}</p>{{/reason}}<p>After</p>',
            ['reason' => 'Not a fit']
        );

        $this->assertSame('<p>Before</p><p>Reason: Not a fit</p><p>After</p>', $html);
    }

    public function test_drops_a_section_when_its_token_is_absent(): void
    {
        $html = $this->renderer()->render(
            '<p>Before</p>{{#reason}}<p>Reason: {{reason}}</p>{{/reason}}<p>After</p>',
            []
        );

        $this->assertSame('<p>Before</p><p>After</p>', $html);
    }

    public function test_drops_a_section_when_its_token_is_an_empty_string(): void
    {
        $html = $this->renderer()->render('{{#reason}}<p>{{reason}}</p>{{/reason}}', ['reason' => '']);

        $this->assertSame('', $html);
    }

    public function test_merges_in_the_global_site_name_token(): void
    {
        \App\Models\Setting::current()->update(['site_name' => 'ExcessKart']);

        $html = $this->renderer()->render('<p>{{site_name}}</p>', []);

        $this->assertSame('<p>ExcessKart</p>', $html);
    }

    public function test_a_caller_supplied_token_overrides_the_global_default(): void
    {
        $html = $this->renderer()->render('<p>{{site_name}}</p>', ['site_name' => 'Override']);

        $this->assertSame('<p>Override</p>', $html);
    }

    public function test_does_not_escape_when_escape_html_is_false(): void
    {
        $html = $this->renderer()->render('{{name}}', ['name' => "O'Brien & Sons"], escapeHtml: false);

        $this->assertSame("O'Brien & Sons", $html);
    }

    public function test_still_escapes_by_default_when_escape_html_omitted(): void
    {
        $html = $this->renderer()->render('{{name}}', ['name' => "O'Brien & Sons"]);

        $this->assertSame('O&#039;Brien &amp; Sons', $html);
    }

    public function test_a_non_scalar_token_value_is_left_literal_instead_of_crashing(): void
    {
        $html = $this->renderer()->render('{{data}}', ['data' => ['nested' => 'array']]);

        $this->assertSame('{{data}}', $html);
    }
}
