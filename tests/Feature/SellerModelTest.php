<?php

namespace Tests\Feature;

use App\Models\CustomAttribute;
use App\Models\Seller;
use App\Models\SellerDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_can_have_documents(): void
    {
        $seller = Seller::factory()->create();
        $document = SellerDocument::create([
            'seller_id' => $seller->id,
            'label' => 'GST Certificate',
            'file_path' => 'seller-documents/gst.pdf',
        ]);

        $this->assertTrue($seller->documents->contains($document));
    }

    public function test_a_seller_can_have_custom_attributes(): void
    {
        $seller = Seller::factory()->create();
        $seller->customAttributes()->create(['label' => 'Import License', 'value' => 'IL-12345']);

        $this->assertCount(1, $seller->fresh()->customAttributes);
        $this->assertSame('Import License', $seller->fresh()->customAttributes->first()->label);
    }
}
