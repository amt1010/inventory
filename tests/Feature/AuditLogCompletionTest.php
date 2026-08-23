<?php

namespace Tests\Feature;

use App\Filament\Imports\CategoryProductImporter;
use App\Models\AuditLog;
use App\Models\Staff;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_an_import_fills_in_the_counts_and_summary(): void
    {
        Import::polymorphicUserRelationship();
        $this->actingAs(Staff::factory()->create(), 'staff');

        $import = Import::create([
            'file_name' => 'catalog.xlsx',
            'file_path' => 'catalog.xlsx',
            'importer' => CategoryProductImporter::class,
            'total_rows' => 10,
            'successful_rows' => 8,
        ]);

        CategoryProductImporter::getCompletedNotificationBody($import);

        $log = AuditLog::where('filament_import_id', $import->id)->firstOrFail();
        $this->assertSame(10, $log->total_rows);
        $this->assertSame(8, $log->successful_rows);
        $this->assertSame(2, $log->failed_rows);
        $this->assertStringContainsString('8', $log->summary);
    }
}
