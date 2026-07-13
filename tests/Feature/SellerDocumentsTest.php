<?php

namespace Tests\Feature;

use App\Filament\Seller\Pages\Documents;
use App\Models\Seller;
use App\Models\SellerDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SellerDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_seller_can_upload_a_document(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $this->actingAs($seller, 'seller');

        Livewire::test(Documents::class)
            ->callTableAction('create', data: [
                'label' => 'GST Certificate',
                'file_path' => UploadedFile::fake()->create('gst.pdf', 100),
            ]);

        $this->assertSame(1, $seller->documents()->count());
        $this->assertSame('GST Certificate', $seller->documents()->first()->label);
    }

    public function test_a_seller_can_delete_their_own_document(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $document = SellerDocument::factory()->for($seller)->create();
        $this->actingAs($seller, 'seller');

        Livewire::test(Documents::class)
            ->callTableAction('delete', $document);

        $this->assertSame(0, $seller->documents()->count());
    }

    public function test_a_seller_only_sees_their_own_documents(): void
    {
        $seller = Seller::factory()->create(['status' => 'approved']);
        $ownDocument = SellerDocument::factory()->for($seller)->create();
        $otherDocument = SellerDocument::factory()->create();
        $this->actingAs($seller, 'seller');

        Livewire::test(Documents::class)
            ->assertCanSeeTableRecords([$ownDocument])
            ->assertCanNotSeeTableRecords([$otherDocument]);
    }
}
