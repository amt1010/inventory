<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Staff;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplatePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_admin_can_view_and_update_email_templates(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $template = EmailTemplate::first();

        $this->assertTrue($admin->can('viewAny', EmailTemplate::class));
        $this->assertTrue($admin->can('update', $template));
    }

    public function test_content_editor_can_view_and_update_email_templates(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');

        $template = EmailTemplate::first();

        $this->assertTrue($editor->can('viewAny', EmailTemplate::class));
        $this->assertTrue($editor->can('update', $template));
    }

    public function test_sales_cannot_view_email_templates(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');

        $this->assertFalse($sales->can('viewAny', EmailTemplate::class));
    }

    public function test_a_system_template_cannot_be_deleted_even_by_admin(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $template = EmailTemplate::first(); // is_system = true

        $this->assertFalse($admin->can('delete', $template));
    }

    public function test_a_custom_template_can_be_deleted_by_admin(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');

        $template = EmailTemplate::create([
            'key' => 'custom_template', 'label' => 'Custom', 'is_system' => false,
            'subject' => 's', 'body' => 'b', 'draft_subject' => 's', 'draft_body' => 'b',
        ]);

        $this->assertTrue($admin->can('delete', $template));
    }
}
