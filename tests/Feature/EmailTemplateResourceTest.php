<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\ListEmailTemplates;
use App\Models\EmailTemplate;
use App\Models\Staff;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplateResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    private function actingAsAdmin(): Staff
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        return $admin;
    }

    public function test_sales_gets_a_403_visiting_the_email_templates_list(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $response = $this->get('/admin/email-templates');

        $response->assertForbidden();
    }

    public function test_a_system_templates_key_and_label_are_locked_in_the_edit_form(): void
    {
        $this->actingAsAdmin();

        $template = EmailTemplate::forKey('staff_invitation');

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->assertFormFieldIsDisabled('key')
            ->assertFormFieldIsDisabled('label');
    }

    public function test_editing_the_draft_fields_does_not_change_the_live_columns(): void
    {
        $this->actingAsAdmin();

        $template = EmailTemplate::forKey('staff_invitation');

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm([
                'draft_subject' => 'A brand new subject',
                'draft_body' => '<p>New body</p>',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $template->refresh();
        $this->assertSame('A brand new subject', $template->draft_subject);
        $this->assertNotSame('A brand new subject', $template->subject);
    }

    public function test_publish_action_copies_draft_onto_live_and_is_hidden_when_unmodified(): void
    {
        $this->actingAsAdmin();

        $template = EmailTemplate::forKey('staff_invitation');
        $template->update(['draft_subject' => 'Edited subject']);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->assertActionVisible('publish')
            ->callAction('publish');

        $this->assertSame('Edited subject', $template->fresh()->subject);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->assertActionHidden('publish');
    }

    public function test_reset_draft_action_discards_unsaved_draft_changes(): void
    {
        $this->actingAsAdmin();

        $template = EmailTemplate::forKey('staff_invitation');
        $originalSubject = $template->subject;
        $template->update(['draft_subject' => 'Unsaved edit']);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('resetDraft');

        $this->assertSame($originalSubject, $template->fresh()->draft_subject);
    }
}
