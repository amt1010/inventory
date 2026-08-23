<?php

namespace Tests\Feature;

use App\Filament\Imports\CategoryProductImporter;
use App\Models\Category;
use App\Models\Product;
use App\Models\Staff;
use App\Services\CategoryChainResolver;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryProductImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImport(): Import
    {
        Import::polymorphicUserRelationship();

        return Import::create([
            'file_name' => 'catalog.xlsx',
            'file_path' => 'catalog.xlsx',
            'importer' => CategoryProductImporter::class,
            'total_rows' => 1,
        ]);
    }

    private function columnMap(): array
    {
        return [
            'product_name' => 'product_name',
            'type' => 'type',
            'parent_name' => 'parent_name',
            'parent_description' => 'parent_description',
            'sub1_name' => 'sub1_name',
            'sub1_description' => 'sub1_description',
            'sub2_name' => 'sub2_name',
            'sub2_description' => 'sub2_description',
            'sku' => 'sku',
            'short_description' => 'short_description',
            'features' => 'features',
            'applications' => 'applications',
            'price_display' => 'price_display',
            'quantity' => 'quantity',
        ];
    }

    private function baseRow(array $overrides = []): array
    {
        return array_merge([
            'product_name' => 'PC DANA-BLACK',
            'type' => 'Raw Material',
            'parent_name' => 'Plastic',
            'parent_description' => 'This is dedicated plastics category',
            'sub1_name' => 'Plastic Granules',
            'sub1_description' => null,
            'sub2_name' => null,
            'sub2_description' => null,
            'sku' => 'RMPLGB0001',
            'short_description' => null,
            'features' => null,
            'applications' => null,
            'price_display' => null,
            'quantity' => null,
        ], $overrides);
    }

    public function test_a_row_with_no_product_name_creates_only_the_category_chain(): void
    {
        $importer = new CategoryProductImporter($this->makeImport(), $this->columnMap(), []);

        $importer($this->baseRow(['product_name' => '']));

        $this->assertSame(2, Category::count());
        $this->assertSame(0, Product::count());
    }

    public function test_a_fully_populated_row_creates_the_category_chain_and_a_pending_review_product(): void
    {
        $importer = new CategoryProductImporter($this->makeImport(), $this->columnMap(), []);

        $importer($this->baseRow());

        $product = Product::where('name', 'PC DANA-BLACK')->firstOrFail();
        $this->assertSame('pending_review', $product->status);
        $this->assertNull($product->seller_id);
        $this->assertSame('admin_bulk_upload', $product->created_by);
        $this->assertSame('raw_material', $product->material_type);
        $this->assertSame('Plastic Granules', $product->category->name);
        $this->assertSame('Plastic', $product->category->parent->name);
    }

    public function test_finished_goods_maps_to_finished_material_type_case_insensitively(): void
    {
        $importer = new CategoryProductImporter($this->makeImport(), $this->columnMap(), []);

        $importer($this->baseRow(['product_name' => 'CURROGATED BOX', 'type' => 'finished goods', 'sku' => 'FG0001']));

        $product = Product::where('name', 'CURROGATED BOX')->firstOrFail();
        $this->assertSame('finished_good', $product->material_type);
    }

    public function test_a_blank_type_cell_fails_the_row(): void
    {
        // Mirrors how Filament's own import job invokes an Importer in
        // production (vendor/filament/actions/src/Imports/Jobs/ImportCsv.php)
        // -- it catches ValidationException from a row and logs it as a
        // failed row rather than letting it abort the whole batch.
        $importer = new CategoryProductImporter($this->makeImport(), $this->columnMap(), []);

        try {
            $importer($this->baseRow(['type' => '']));
        } catch (ValidationException) {
            // expected -- a real import job would log this as a failed row
        }

        $this->assertSame(0, Product::count());
    }

    public function test_a_row_matching_an_existing_product_in_the_same_category_is_skipped(): void
    {
        $category = (new CategoryChainResolver())->resolve([
            'parent_name' => 'Plastic',
            'sub1_name' => 'Plastic Granules',
        ]);
        Product::factory()->create([
            'name' => 'PC DANA-BLACK',
            'category_id' => $category->id,
        ]);

        $importer = new CategoryProductImporter($this->makeImport(), $this->columnMap(), []);
        $importer($this->baseRow());

        $this->assertSame(1, Product::where('name', 'PC DANA-BLACK')->count());
    }

    public function test_the_import_action_is_available_on_the_admin_products_list(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $response = $this->get('/admin/products');

        $response->assertOk();
        $response->assertSee('Import Categories &amp; Products', false);
    }
}
