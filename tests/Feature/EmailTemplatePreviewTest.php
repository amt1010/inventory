<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Models\EmailTemplate;
use App\Models\Staff;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EmailTemplateSeeder::class);

        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');
    }

    public function test_preview_renders_the_draft_with_sample_data_and_sends_no_mail(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $template = EmailTemplate::forKey('staff_invitation');
        $template->update(['draft_body' => '<p>Hello {{staff_name}}</p>']);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->mountAction('preview')
            ->assertActionMounted('preview')
            ->assertSee('Hello Priya');

        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    public function test_preview_leaves_a_key_specific_unknown_token_untouched_for_custom_templates(): void
    {
        $template = EmailTemplate::create([
            'key' => 'custom_one', 'label' => 'Custom One', 'is_system' => false,
            'subject' => 's', 'body' => 'b',
            'draft_subject' => 's', 'draft_body' => '<p>{{not_a_real_token}}</p>',
        ]);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->mountAction('preview')
            ->assertActionMounted('preview')
            ->assertSee('{{not_a_real_token}}');
    }
}
