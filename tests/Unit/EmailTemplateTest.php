<?php

namespace Tests\Unit;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(array $overrides = []): EmailTemplate
    {
        return EmailTemplate::create(array_merge([
            'key' => 'sample_key',
            'label' => 'Sample',
            'is_system' => true,
            'subject' => 'Live subject',
            'body' => '<p>Live body</p>',
            'default_cc' => null,
            'default_bcc' => null,
            'draft_subject' => 'Live subject',
            'draft_body' => '<p>Live body</p>',
            'draft_default_cc' => null,
            'draft_default_bcc' => null,
        ], $overrides));
    }

    public function test_for_key_finds_a_template_by_its_key(): void
    {
        $this->makeTemplate(['key' => 'seller_rejected']);

        $found = EmailTemplate::forKey('seller_rejected');

        $this->assertSame('seller_rejected', $found->key);
    }

    public function test_for_key_throws_when_the_key_does_not_exist(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        EmailTemplate::forKey('does_not_exist');
    }

    public function test_is_modified_is_false_when_draft_matches_live(): void
    {
        $template = $this->makeTemplate();

        $this->assertFalse($template->isModified());
    }

    public function test_is_modified_is_true_when_draft_body_differs(): void
    {
        $template = $this->makeTemplate(['draft_body' => '<p>Edited body</p>']);

        $this->assertTrue($template->isModified());
    }

    public function test_publish_copies_draft_columns_onto_live_columns(): void
    {
        $template = $this->makeTemplate([
            'draft_subject' => 'New subject',
            'draft_body' => '<p>New body</p>',
            'draft_default_cc' => 'ops@example.com',
        ]);

        $template->publish();
        $template->refresh();

        $this->assertSame('New subject', $template->subject);
        $this->assertSame('<p>New body</p>', $template->body);
        $this->assertSame('ops@example.com', $template->default_cc);
        $this->assertFalse($template->isModified());
    }

    public function test_reset_draft_copies_live_columns_onto_draft_columns(): void
    {
        $template = $this->makeTemplate([
            'draft_subject' => 'Unsaved edit',
            'draft_body' => '<p>Unsaved edit</p>',
        ]);

        $template->resetDraft();
        $template->refresh();

        $this->assertSame($template->subject, $template->draft_subject);
        $this->assertSame($template->body, $template->draft_body);
        $this->assertFalse($template->isModified());
    }

    public function test_cc_addresses_splits_trims_and_filters_invalid_emails(): void
    {
        $template = $this->makeTemplate(['default_cc' => 'ops@example.com, not-an-email, sales@example.com ,']);

        $this->assertSame(['ops@example.com', 'sales@example.com'], $template->ccAddresses());
    }

    public function test_cc_addresses_is_empty_array_when_column_is_null(): void
    {
        $template = $this->makeTemplate(['default_cc' => null]);

        $this->assertSame([], $template->ccAddresses());
    }

    public function test_bcc_addresses_splits_trims_and_filters_invalid_emails(): void
    {
        $template = $this->makeTemplate(['default_bcc' => 'a@example.com,b@example.com']);

        $this->assertSame(['a@example.com', 'b@example.com'], $template->bccAddresses());
    }
}
