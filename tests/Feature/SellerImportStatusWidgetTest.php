<?php

namespace Tests\Feature;

use App\Filament\Imports\SellerImporter;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerImportStatusWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): Staff
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        return $admin;
    }

    public function test_no_widget_content_when_there_is_no_incomplete_import(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/admin/sellers');

        $response->assertOk();
        $response->assertDontSee('Importing sellers:');
    }

    public function test_shows_live_progress_for_an_incomplete_import(): void
    {
        $this->actingAsAdmin();
        Import::polymorphicUserRelationship();

        Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 500,
            'processed_rows' => 214,
        ]);

        $response = $this->get('/admin/sellers');

        $response->assertOk();
        $response->assertSee('Importing sellers: 214 of 500 rows');
    }

    public function test_shows_a_stuck_banner_past_the_threshold(): void
    {
        config(['imports.stuck_after_minutes' => 15]);
        $this->actingAsAdmin();
        Import::polymorphicUserRelationship();

        $import = Import::create([
            'file_name' => 'sellers.csv',
            'file_path' => 'sellers.csv',
            'importer' => SellerImporter::class,
            'total_rows' => 500,
            'processed_rows' => 0,
        ]);
        $import->timestamps = false;
        $import->update(['updated_at' => now()->subMinutes(20)]);

        $response = $this->get('/admin/sellers');

        $response->assertOk();
        $response->assertSee('queue worker may be offline');
        $response->assertDontSee('Importing sellers:');
    }
}
