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

    public function test_copyright_text_resolves_the_year_placeholder(): void
    {
        $setting = Setting::current();
        $setting->update(['footer_copyright' => '© {year} Acme Corp']);

        $this->assertSame('© '.now()->year.' Acme Corp', $setting->fresh()->copyrightText());
    }

    public function test_social_links_returns_only_configured_platforms(): void
    {
        $setting = Setting::current();
        $setting->update([
            'social_facebook' => 'https://facebook.com/acme',
            'social_youtube' => 'https://youtube.com/@acme',
        ]);

        $links = $setting->fresh()->socialLinks();

        $this->assertSame([
            'facebook' => 'https://facebook.com/acme',
            'youtube' => 'https://youtube.com/@acme',
        ], $links);
    }

    public function test_the_footer_renders_configured_copyright_address_contact_and_social_links(): void
    {
        Setting::current()->update([
            'footer_copyright' => '© {year} Acme Corp',
            'footer_address' => '12 Cable Street, Mumbai',
            'footer_phone' => '+91 22 1234 5678',
            'footer_email' => 'hello@acme.test',
            'social_facebook' => 'https://facebook.com/acme',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('© '.now()->year.' Acme Corp');
        $response->assertSee('12 Cable Street, Mumbai');
        $response->assertSee('hello@acme.test');
        $response->assertSee('https://facebook.com/acme', false);
        // Cookie-settings placeholder slot is present for future use.
        $response->assertSee('data-cookies-placeholder', false);
        // Unconfigured platforms are not rendered.
        $response->assertDontSee('youtube.com', false);
    }

    public function test_an_admin_can_save_footer_settings(): void
    {
        $staff = Staff::factory()->create();
        $staff->assignRole('admin');
        $this->actingAs($staff, 'staff');

        \Livewire\Livewire::test(\App\Filament\Pages\ManageSettings::class)
            ->fillForm([
                'site_name' => 'Acme',
                'footer_copyright' => '© {year} Acme',
                'footer_email' => 'hello@acme.test',
                'social_linkedin' => 'https://linkedin.com/company/acme',
            ])
            ->call('save');

        $setting = Setting::current();
        $this->assertSame('© {year} Acme', $setting->footer_copyright);
        $this->assertSame('hello@acme.test', $setting->footer_email);
        $this->assertSame('https://linkedin.com/company/acme', $setting->social_linkedin);
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
