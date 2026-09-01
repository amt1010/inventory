<?php

namespace Tests\Feature;

use App\Filament\Resources\SubscriberResource\Pages\ListSubscribers;
use App\Models\Staff;
use App\Models\Subscriber;
use Database\Seeders\RoleSeeder;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriberManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_an_admin_can_view_the_subscribers_list(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $subscriber = Subscriber::factory()->create(['email' => 'buyer@example.com']);

        Livewire::test(ListSubscribers::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$subscriber])
            ->assertSee('buyer@example.com');
    }

    public function test_sales_can_view_the_subscribers_list(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $subscriber = Subscriber::factory()->create();

        Livewire::test(ListSubscribers::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$subscriber]);
    }

    public function test_a_content_editor_gets_a_403_visiting_the_subscribers_list(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $response = $this->get('/admin/subscribers');

        $response->assertForbidden();
    }

    public function test_an_admin_can_delete_a_subscriber(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $subscriber = Subscriber::factory()->create();

        Livewire::test(ListSubscribers::class)
            ->callTableAction(DeleteAction::class, $subscriber)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('subscribers', ['id' => $subscriber->id]);
    }

    public function test_sales_cannot_delete_a_subscriber(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $subscriber = Subscriber::factory()->create();

        Livewire::test(ListSubscribers::class)
            ->assertTableActionHidden(DeleteAction::class, $subscriber);

        $this->assertDatabaseHas('subscribers', ['id' => $subscriber->id]);
    }
}
