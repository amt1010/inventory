<?php

namespace Tests\Feature;

use App\Filament\Resources\QuoteRequestResource\Pages\EditQuoteRequest;
use App\Filament\Resources\QuoteRequestResource\Pages\ListQuoteRequests;
use App\Filament\Resources\QuoteRequestResource\RelationManagers\NotesRelationManager;
use App\Models\QuoteRequest;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Filament\Tables\Actions\CreateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_sales_can_view_the_quote_requests_list(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $quoteRequest = QuoteRequest::factory()->create(['first_name' => 'Asha']);

        Livewire::test(ListQuoteRequests::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$quoteRequest]);
    }

    public function test_the_quote_number_column_is_shown(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $quoteRequest = QuoteRequest::factory()->create(['quote_number' => '26080317300001']);

        Livewire::test(ListQuoteRequests::class)
            ->assertSuccessful()
            ->assertSee('26080317300001');
    }

    public function test_the_category_column_shows_the_products_category(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $category = \App\Models\Category::factory()->create(['name' => 'Fibre Optic Cables']);
        $product = \App\Models\Product::factory()->create(['category_id' => $category->id]);
        QuoteRequest::factory()->create(['product_id' => $product->id]);

        Livewire::test(ListQuoteRequests::class)
            ->assertSuccessful()
            ->assertSee('Fibre Optic Cables');
    }

    public function test_the_category_column_shows_general_inquiry_when_there_is_no_product(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        QuoteRequest::factory()->create(['product_id' => null]);

        Livewire::test(ListQuoteRequests::class)
            ->assertSuccessful()
            ->assertSee('General inquiry');
    }

    public function test_the_date_filter_shows_only_requests_from_the_selected_day(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $today = QuoteRequest::factory()->create(['created_at' => now()]);
        $yesterday = QuoteRequest::factory()->create(['created_at' => now()->subDay()]);

        Livewire::test(ListQuoteRequests::class)
            ->filterTable('date', ['date' => now()->toDateString()])
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$yesterday]);
    }

    public function test_content_editor_gets_a_403_visiting_the_quote_requests_list(): void
    {
        $editor = Staff::factory()->create();
        $editor->assignRole('content_editor');
        $this->actingAs($editor, 'staff');

        $response = $this->get('/admin/quote-requests');

        $response->assertForbidden();
    }

    public function test_sales_can_add_a_note_to_a_quote_request(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $quoteRequest = QuoteRequest::factory()->create();

        $quoteRequest->notes()->create([
            'staff_id' => $sales->id,
            'note' => 'Left a voicemail.',
        ]);

        $this->assertCount(1, $quoteRequest->fresh()->notes);
    }

    public function test_the_notes_relation_manager_stamps_staff_id_from_the_acting_staff_member(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $quoteRequest = QuoteRequest::factory()->create();

        // pageClass must be EditQuoteRequest, not ViewQuoteRequest: Filament
        // defaults relation managers to read-only on ViewRecord pages
        // (Panel::hasReadOnlyRelationManagersOnResourceViewPagesByDefault()
        // is true out of the box), so the create action is hidden there.
        Livewire::test(NotesRelationManager::class, [
            'ownerRecord' => $quoteRequest,
            'pageClass' => EditQuoteRequest::class,
        ])
            ->callTableAction(CreateAction::class, data: ['note' => 'Left a voicemail.'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('quote_request_notes', [
            'quote_request_id' => $quoteRequest->id,
            'staff_id' => $sales->id,
            'note' => 'Left a voicemail.',
        ]);
    }
}
