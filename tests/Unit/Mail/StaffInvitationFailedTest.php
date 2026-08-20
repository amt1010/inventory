<?php

namespace Tests\Unit\Mail;

use App\Mail\StaffInvitation;
use App\Models\Staff;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class StaffInvitationFailedTest extends TestCase
{
    public function test_it_logs_the_staff_id_and_exception_message_but_never_the_password(): void
    {
        Log::spy();
        $staff = new Staff();
        $staff->id = 7;

        (new StaffInvitation($staff, 'super-secret-temp-pw', 'https://example.test/admin/login'))
            ->failed(new \RuntimeException('smtp down'));

        Log::shouldHaveReceived('error')->once()->with(
            'Failed to send staff invitation email.',
            \Mockery::on(function (array $context) {
                $serialized = json_encode($context);

                return $context['staff_id'] === 7
                    && $context['exception'] === 'smtp down'
                    && ! str_contains($serialized, 'super-secret-temp-pw');
            })
        );
    }
}
