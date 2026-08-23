<?php

namespace Tests\Feature;

use App\Filament\Imports\CategoryProductImporter;
use App\Filament\Imports\SellerImporter;
use App\Models\AuditLog;
use App\Models\Staff;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_category_product_import_records_an_audit_log_with_the_acting_staff(): void
    {
        Import::polymorphicUserRelationship();
        $admin = Staff::factory()->create();
        $this->actingAs($admin, 'staff');

        $import = Import::create([
            'file_name' => 'catalog.xlsx',
            'file_path' => 'catalog.xlsx',
            'importer' => CategoryProductImporter::class,
            'total_rows' => 10,
        ]);

        $log = AuditLog::where('filament_import_id', $import->id)->firstOrFail();
        $this->assertSame('Category & Product Import', $log->importer_label);
        $this->assertSame($admin->id, $log->performed_by_staff_id);
        $this->assertSame('catalog.xlsx', $log->file_name);
    }

    public function test_creating_a_seller_import_records_an_audit_log_with_a_friendly_label(): void
    {
        Import::polymorphicUserRelationship();
        $admin = Staff::factory()->create();
        $this->actingAs($admin, 'staff');

        $import = Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 5,
        ]);

        $log = AuditLog::where('filament_import_id', $import->id)->firstOrFail();
        $this->assertSame('Seller Import', $log->importer_label);
    }
}
