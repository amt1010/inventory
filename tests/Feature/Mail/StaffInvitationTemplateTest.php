<?php

namespace Tests\Feature\Mail;

use App\Mail\StaffInvitation;
use App\Models\EmailTemplate;
use App\Models\Staff;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffInvitationTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailTemplateSeeder::class);
    }

    public function test_the_email_uses_the_published_template_content(): void
    {
        EmailTemplate::forKey('staff_invitation')->update([
            'body' => '<p>Welcome {{staff_name}}. Login: {{login_url}}. Temp password: {{temporary_password}}.</p>',
        ]);

        $staff = Staff::factory()->create(['name' => 'Priya']);

        $mailable = new StaffInvitation($staff, 'Temp1234!', 'https://example.test/admin/login');

        $mailable->assertSeeInHtml('Welcome Priya. Login: https://example.test/admin/login. Temp password: Temp1234!.');
    }
}
