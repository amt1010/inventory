<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class CategoryProductXlsxImportActionTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = [
        'Product NAME', 'TYPE', 'PARENT CATEGORY NAME', 'PARENT CATEGORY Description',
        'Sub-Category-1 Name', 'Sub-Category-1 Description', 'Sub-Category-2 Name', 'Sub-Category-2 Description',
        'SKU / Product Number', 'Product Short Description', 'Product Feature', 'Product Application',
        'Price Range (INR)', 'Quantity',
    ];

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function makeUploadedXlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-action-test').'.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADER));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return UploadedFile::fake()->createWithContent('catalog.xlsx', file_get_contents($path));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_the_import_from_excel_action_is_available_on_the_admin_products_list(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $response = $this->get('/admin/products');

        $response->assertOk();
        $response->assertSee('Import from Excel', false);
    }

    public function test_uploading_an_xlsx_file_creates_categories_products_and_an_audit_log(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $file = $this->makeUploadedXlsx([
            ['PC DANA-BLACK', 'Raw Material', 'Plastic', 'Plastics category', 'Plastic Granules', '', '', '', 'RMPLGB0001', '', '', '', '', ''],
            ['', '', 'Metal', '', 'Screws', '', '', '', '', '', '', '', '', ''],
        ]);

        Livewire::test(ListProducts::class)
            ->callAction('importXlsx', data: ['file' => $file]);

        $product = Product::where('name', 'PC DANA-BLACK')->firstOrFail();
        $this->assertSame('pending_review', $product->status);
        $this->assertSame('admin_bulk_upload', $product->created_by);

        $this->assertNotNull(Category::where('name', 'Screws')->first());

        $log = AuditLog::where('importer_label', 'Category & Product Import (Excel)')->firstOrFail();
        $this->assertSame($admin->id, $log->performed_by_staff_id);
        $this->assertSame('catalog.xlsx', $log->file_name);
        $this->assertSame(1, $log->successful_rows);
        $this->assertSame(0, $log->failed_rows);
    }
}
