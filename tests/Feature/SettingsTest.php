<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Page::factory()->create(['slug' => 'home', 'status' => 'published']);
    }

    public function test_current_creates_a_default_row_on_first_access(): void
    {
        $this->assertDatabaseCount('settings', 0);

        $setting = Setting::current();

        $this->assertDatabaseCount('settings', 1);
        $this->assertSame($setting->id, Setting::current()->id);
    }

    public function test_the_public_layout_shows_the_configured_site_name_when_there_is_no_logo(): void
    {
        Setting::current()->update(['site_name' => 'Acme Cable Co.']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Acme Cable Co.');
    }

    public function test_an_admin_can_access_the_settings_page(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('admin');

        $response = $this->actingAs($staff, 'staff')->get('/admin/manage-settings');

        $response->assertOk();
    }

    public function test_a_content_editor_cannot_access_the_settings_page(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('content_editor');

        $response = $this->actingAs($staff, 'staff')->get('/admin/manage-settings');

        $response->assertForbidden();
    }
}
