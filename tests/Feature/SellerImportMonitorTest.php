<?php

namespace Tests\Feature;

use App\Filament\Imports\SellerImporter;
use App\Mail\SellerImportStuck;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerImportMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_table_has_a_stuck_notified_at_column(): void
    {
        $this->assertTrue(Schema::hasColumn('imports', 'stuck_notified_at'));
    }

    public function test_the_stuck_mail_names_the_file_and_shows_progress(): void
    {
        Import::polymorphicUserRelationship();

        $import = Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 500,
            'processed_rows' => 214,
        ]);

        $mail = new SellerImportStuck($import);

        $mail->assertHasSubject('Seller import appears stuck: sellers.csv');
        $mail->assertSeeInHtml('214');
        $mail->assertSeeInHtml('500');
    }
}
