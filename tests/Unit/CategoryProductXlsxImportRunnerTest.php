<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryProductXlsxImportRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class CategoryProductXlsxImportRunnerTest extends TestCase
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
    private function makeXlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-import-test').'.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADER));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return $path;
    }

    public function test_a_row_with_no_product_name_creates_only_the_category_chain(): void
    {
        $path = $this->makeXlsx([
            ['', 'Raw Material', 'Plastic', 'Plastics category', 'Plastic Granules', '', '', '', '', '', '', '', '', ''],
        ]);

        $result = (new CategoryProductXlsxImportRunner())->run($path);

        $this->assertSame(['created' => 0, 'skipped' => 1, 'failed' => 0, 'errors' => []], $result);
        $this->assertSame(2, Category::count());
        $this->assertSame(0, Product::count());
    }

    public function test_a_fully_populated_row_creates_the_category_chain_and_a_pending_review_product(): void
    {
        $path = $this->makeXlsx([
            ['PC DANA-BLACK', 'Raw Material', 'Plastic', 'Plastics category', 'Plastic Granules', '', '', '', 'RMPLGB0001', 'Short desc', '', '', '', 500],
        ]);

        $result = (new CategoryProductXlsxImportRunner())->run($path);

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['failed']);

        $product = Product::where('name', 'PC DANA-BLACK')->firstOrFail();
        $this->assertSame('pending_review', $product->status);
        $this->assertNull($product->seller_id);
        $this->assertSame('admin_bulk_upload', $product->created_by);
        $this->assertSame('raw_material', $product->material_type);
        $this->assertSame('RMPLGB0001', $product->sku);
        $this->assertSame('Short desc', $product->short_description);
        $this->assertSame(500, $product->quantity);
        $this->assertSame('Plastic Granules', $product->category->name);
        $this->assertSame('Plastic', $product->category->parent->name);
    }

    public function test_finished_goods_maps_to_finished_material_type_case_insensitively(): void
    {
        $path = $this->makeXlsx([
            ['CURROGATED BOX', 'finished goods', 'Paper', '', 'Currogated Box', '', '', '', 'FG0001', '', '', '', '', ''],
        ]);

        $result = (new CategoryProductXlsxImportRunner())->run($path);

        $this->assertSame(1, $result['created']);
        $product = Product::where('name', 'CURROGATED BOX')->firstOrFail();
        $this->assertSame('finished_good', $product->material_type);
    }

    public function test_an_invalid_type_cell_is_counted_as_a_failed_row_not_thrown(): void
    {
        $path = $this->makeXlsx([
            ['Mystery Item', 'Not A Real Type', 'Plastic', '', '', '', '', '', '', '', '', '', '', ''],
        ]);

        $result = (new CategoryProductXlsxImportRunner())->run($path);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['failed']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Row 2', $result['errors'][0]);
        $this->assertSame(0, Product::count());
    }

    public function test_a_row_matching_an_existing_product_in_the_same_category_is_skipped(): void
    {
        $category = (new \App\Services\CategoryChainResolver())->resolve(['parent_name' => 'Plastic']);
        Product::factory()->create(['name' => 'PC DANA-BLACK', 'category_id' => $category->id]);

        $path = $this->makeXlsx([
            ['PC DANA-BLACK', 'Raw Material', 'Plastic', '', '', '', '', '', '', '', '', '', '', ''],
        ]);

        $result = (new CategoryProductXlsxImportRunner())->run($path);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, Product::where('name', 'PC DANA-BLACK')->count());
    }

    public function test_a_completely_blank_row_is_ignored_and_creates_no_categories(): void
    {
        $path = $this->makeXlsx([
            ['', '', '', '', '', '', '', '', '', '', '', '', '', ''],
        ]);

        $result = (new CategoryProductXlsxImportRunner())->run($path);

        $this->assertSame(['created' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []], $result);
        $this->assertSame(0, Category::count());
    }
}
