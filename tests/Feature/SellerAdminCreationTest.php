<?php

namespace Tests\Feature;

use App\Filament\Resources\SellerResource\Pages\CreateSeller;
use App\Mail\SellerActivationMail;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class SellerAdminCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creating_a_seller_sends_an_activation_email_and_the_seller_becomes_approved_immediately_after_activating(): void
    {
        Mail::fake();

        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateSeller::class)
            ->fillForm([
                'company_name' => 'Vikram Supplies',
                'contact_person' => 'Vikram Singh',
                'phone' => '9876500000',
                'email' => 'vikram@vikramsupplies.example',
                'business_address' => '45 MG Road, Pune',
                'gst_number' => '27BBBBB1111B1Z6',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $seller = Seller::where('email', 'vikram@vikramsupplies.example')->firstOrFail();
        $this->assertSame('pending_email_verification', $seller->status);
        $this->assertSame('admin', $seller->created_by);

        Mail::assertQueued(SellerActivationMail::class, fn ($mail) => $mail->seller->is($seller));

        $url = URL::temporarySignedRoute('seller.activate.store', now()->addDays(7), ['seller' => $seller->id]);
        $this->post($url, ['password' => 'brandnewpass1', 'password_confirmation' => 'brandnewpass1']);

        $this->assertSame('approved', $seller->fresh()->status);
    }

    public function test_admin_creating_a_seller_can_include_manufacturing_activity_and_availability_hours(): void
    {
        Mail::fake();

        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreateSeller::class)
            ->fillForm([
                'company_name' => 'Vikram Supplies',
                'contact_person' => 'Vikram Singh',
                'phone' => '9876500000',
                'email' => 'vikram2@vikramsupplies.example',
                'manufacturing_activity' => 'Textile weaving',
                'availability_hours' => 'Mon-Fri 10am-5pm',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $seller = Seller::where('email', 'vikram2@vikramsupplies.example')->firstOrFail();
        $this->assertSame('Textile weaving', $seller->manufacturing_activity);
        $this->assertSame('Mon-Fri 10am-5pm', $seller->availability_hours);
    }
}
