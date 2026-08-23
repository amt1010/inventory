<?php

namespace App\Services;

use App\Support\MaterialType;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Reads an .xlsx workbook directly (Filament's native Importer only reads
 * CSV -- see CategoryProductImporter) and runs each data row through the
 * same CategoryProductRowResolver/MaterialType rules that importer uses,
 * so both paths behave identically. Runs synchronously (no queue): sheets
 * from this feature are small enough that this is fine, and it keeps the
 * implementation simple compared to building a second queued-job pipeline.
 */
class CategoryProductXlsxImportRunner
{
    /**
     * Canonical field key => the header text variants (lowercased, trimmed)
     * that map to it. Matches the labels used by CategoryProductImporter's
     * ImportColumn::label() calls, so the same sheet works with either path.
     */
    private const HEADER_MAP = [
        'product_name' => ['product name'],
        'type' => ['type'],
        'parent_name' => ['parent category name'],
        'parent_description' => ['parent category description'],
        'sub1_name' => ['sub-category-1 name'],
        'sub1_description' => ['sub-category-1 description'],
        'sub2_name' => ['sub-category-2 name'],
        'sub2_description' => ['sub-category-2 description'],
        'sku' => ['sku / product number'],
        'short_description' => ['product short description'],
        'features' => ['product feature'],
        'applications' => ['product application'],
        'price_display' => ['price range (inr)'],
        'quantity' => ['quantity'],
    ];

    /**
     * @return array{created: int, skipped: int, failed: int, errors: list<string>}
     */
    public function run(string $filePath): array
    {
        $created = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $reader = new Reader();
        $reader->open($filePath);

        foreach ($reader->getSheetIterator() as $sheet) {
            $headerMap = null;
            $rowNumber = 0;

            foreach ($sheet->getRowIterator() as $row) {
                $rowNumber++;
                $values = $row->toArray();

                if ($headerMap === null) {
                    $headerMap = $this->buildHeaderMap($values);

                    continue;
                }

                $data = $this->mapRow($values, $headerMap);

                if ($this->isBlankRow($data)) {
                    continue;
                }

                try {
                    if ($this->importRow($data)) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                } catch (\Throwable $exception) {
                    $failed++;
                    $errors[] = "Row {$rowNumber}: {$exception->getMessage()}";
                }
            }

            break;
        }

        $reader->close();

        return [
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<mixed>  $headerRow
     * @return array<int, string> column index => canonical field key
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];

        foreach ($headerRow as $index => $value) {
            $normalized = mb_strtolower(trim((string) $value));

            foreach (self::HEADER_MAP as $key => $variants) {
                if (in_array($normalized, $variants, true)) {
                    $map[$index] = $key;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<mixed>  $values
     * @param  array<int, string>  $headerMap
     * @return array<string, ?string>
     */
    private function mapRow(array $values, array $headerMap): array
    {
        $data = [];

        foreach ($headerMap as $index => $key) {
            $value = $values[$index] ?? null;
            $data[$key] = ($value === null || $value === '') ? null : trim((string) $value);
        }

        return $data;
    }

    private function isBlankRow(array $data): bool
    {
        foreach (['product_name', 'parent_name', 'sub1_name', 'sub2_name'] as $key) {
            if (filled($data[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool true if a product was created, false if the row was
     *              skipped (category-only row, or the product already exists)
     */
    private function importRow(array $row): bool
    {
        $product = (new CategoryProductRowResolver())->resolveProduct($row);

        if ($product === null) {
            return false;
        }

        $materialType = MaterialType::normalize($row['type'] ?? null);

        if ($materialType === null) {
            throw new \RuntimeException('TYPE must be "Raw Material" or "Finished Good" (or "Finished Goods").');
        }

        $product->material_type = $materialType;
        $product->status = 'pending_review';
        $product->seller_id = null;
        $product->created_by = 'admin_bulk_upload';
        $product->sku = $row['sku'] ?? null;
        $product->short_description = $row['short_description'] ?? null;
        $product->features = $row['features'] ?? null;
        $product->applications = $row['applications'] ?? null;
        $product->price_display = $row['price_display'] ?? null;
        $product->quantity = is_numeric($row['quantity'] ?? null) ? (int) $row['quantity'] : null;
        $product->save();

        return true;
    }
}
