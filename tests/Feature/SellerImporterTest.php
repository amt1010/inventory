<?php

namespace Tests\Feature;

use App\Filament\Imports\SellerImporter;
use App\Models\Seller;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImport(): Import
    {
        Import::polymorphicUserRelationship();

        return Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 1,
        ]);
    }

    private function columnMap(): array
    {
        return [
            'company_name' => 'company_name',
            'manufacturing_activity' => 'manufacturing_activity',
            'business_address' => 'business_address',
            'phone' => 'phone',
            'email' => 'email',
            'availability_hours' => 'availability_hours',
            'contact_person' => 'contact_person',
            'gst_number' => 'gst_number',
        ];
    }

    public function test_a_fully_populated_row_creates_a_seller_pending_review(): void
    {
        $importer = new SellerImporter($this->makeImport(), $this->columnMap(), []);

        $importer([
            'company_name' => 'Rao Traders',
            'manufacturing_activity' => 'Cable manufacturing',
            'business_address' => '123 Industrial Estate, Mumbai',
            'phone' => '9876543210',
            'email' => 'bulk1@raotraders.example',
            'availability_hours' => 'Mon-Sat 9am-6pm',
            'contact_person' => 'Asha Rao',
            'gst_number' => '27AAAAA0000A1Z5',
        ]);

        $seller = Seller::where('email', 'bulk1@raotraders.example')->firstOrFail();
        $this->assertSame('Rao Traders', $seller->company_name);
        $this->assertSame('pending_admin_approval', $seller->status);
        $this->assertSame('admin_bulk_upload', $seller->created_by);
        $this->assertNull($seller->password_set_at);
        $this->assertNotNull($seller->seller_code);
    }

    public function test_blank_cells_are_stored_as_the_placeholder(): void
    {
        $importer = new SellerImporter($this->makeImport(), $this->columnMap(), []);

        $importer([
            'company_name' => 'Partial Co',
            'manufacturing_activity' => '',
            'business_address' => '',
            'phone' => '9999999999',
            'email' => 'bulk2@example.com',
            'availability_hours' => '',
            'contact_person' => 'Jane Doe',
            'gst_number' => '',
        ]);

        $seller = Seller::where('email', 'bulk2@example.com')->firstOrFail();
        $this->assertSame(Seller::PLACEHOLDER, $seller->manufacturing_activity);
        $this->assertSame(Seller::PLACEHOLDER, $seller->business_address);
        $this->assertSame(Seller::PLACEHOLDER, $seller->availability_hours);
        $this->assertSame(Seller::PLACEHOLDER, $seller->gst_number);
    }

    public function test_a_blank_email_cell_is_also_stored_as_the_placeholder(): void
    {
        $importer = new SellerImporter($this->makeImport(), $this->columnMap(), []);

        $importer([
            'company_name' => 'No Email Co',
            'manufacturing_activity' => 'Weaving',
            'business_address' => '1 Market Road',
            'phone' => '9999999998',
            'email' => '',
            'availability_hours' => 'Mon-Fri 9-5',
            'contact_person' => 'John Doe',
            'gst_number' => '27BBBBB1111B1Z6',
        ]);

        $seller = Seller::where('company_name', 'No Email Co')->firstOrFail();
        $this->assertSame(Seller::PLACEHOLDER, $seller->email);
    }

    public function test_a_comma_separated_email_is_stored_verbatim(): void
    {
        $importer = new SellerImporter($this->makeImport(), $this->columnMap(), []);

        $importer([
            'company_name' => 'Multi Email Co',
            'manufacturing_activity' => 'Weaving',
            'business_address' => '1 Market Road',
            'phone' => '9999999997',
            'email' => 'a@example.com, b@example.com',
            'availability_hours' => 'Mon-Fri 9-5',
            'contact_person' => 'John Doe',
            'gst_number' => '27CCCCC2222C1Z7',
        ]);

        $seller = Seller::where('company_name', 'Multi Email Co')->firstOrFail();
        $this->assertSame('a@example.com, b@example.com', $seller->email);
    }

    public function test_the_import_action_is_available_on_the_admin_sellers_list(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $response = $this->get('/admin/sellers');

        $response->assertOk();
        $response->assertSee('Import Sellers');
    }
}
