<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailTemplateResource\Pages\CreateEmailTemplate;
use App\Filament\Resources\EmailTemplateResource\Pages\EditEmailTemplate;
use App\Models\EmailTemplate;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplateCustomCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function actingAsAdmin(): Staff
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        return $admin;
    }

    public function test_creating_a_custom_template_auto_generates_the_key_from_the_label(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateEmailTemplate::class)
            ->fillForm([
                'label' => 'Seasonal Promo Email',
                'draft_subject' => 'Big sale!',
                'draft_body' => '<p>Save today.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = EmailTemplate::where('label', 'Seasonal Promo Email')->firstOrFail();
        $this->assertSame('seasonal_promo_email', $template->key);
        $this->assertFalse($template->is_system);
        $this->assertSame('Big sale!', $template->subject);
    }

    public function test_a_custom_template_can_be_deleted(): void
    {
        $this->actingAsAdmin();

        $template = EmailTemplate::create([
            'key' => 'custom_one', 'label' => 'Custom One', 'is_system' => false,
            'subject' => 's', 'body' => 'b', 'draft_subject' => 's', 'draft_body' => 'b',
        ]);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->assertActionVisible('delete')
            ->callAction('delete');

        $this->assertNull($template->fresh());
    }
}
