<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_an_admin_can_view_the_audit_logs_page_and_see_a_completed_import(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        AuditLog::create([
            'importer_label' => 'Category & Product Import',
            'performed_by_staff_id' => $admin->id,
            'file_name' => 'catalog.xlsx',
            'total_rows' => 10,
            'successful_rows' => 9,
            'failed_rows' => 1,
            'summary' => 'Your category & product import has completed and 9 rows imported.',
        ]);

        $response = $this->get('/admin/audit-logs');

        $response->assertOk();
        $response->assertSee('catalog.xlsx');
        $response->assertSee('Category & Product Import');
    }

    public function test_a_content_editor_cannot_view_the_audit_logs_page(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $response = $this->get('/admin/audit-logs');

        $response->assertForbidden();
    }
}
