<?php

namespace Tests\Feature;

use App\Filament\Resources\QuoteRequestResource\Pages\ListQuoteRequests;
use App\Models\QuoteRequest;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuoteRequestExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_can_export_quote_requests_to_csv(): void
    {
        $this->seed(RoleSeeder::class);
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        QuoteRequest::factory()->create(['email' => 'export-target@example.com']);

        // Note: this is a table header action (registered via Table::headerActions()),
        // not a page-level action, so it's exercised through callTableAction()/
        // Filament\Tables\Testing\TestsActions, not callAction() (which only looks at
        // the page's own $cachedActions, populated by Filament\Actions\Concerns\
        // InteractsWithActions — a different registry from the Table object's own
        // action cache that ->headerActions() populates). Confirmed by reading
        // vendor/filament/tables/src/Concerns/HasActions.php (getMountedTableAction()
        // resolves via $this->getTable()->getAction(...)) alongside
        // vendor/filament/actions/src/Concerns/InteractsWithActions.php (getAction()
        // only reads $this->cachedActions, never the table's).
        Livewire::test(ListQuoteRequests::class)
            ->callTableAction('export')
            ->assertFileDownloaded('quote-requests.csv');
    }
}
