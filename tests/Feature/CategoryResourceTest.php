<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Models\Category;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_top_level_categories_cannot_share_a_slug(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Category::factory()->create(['parent_id' => null, 'slug' => 'fiber-optic-cable']);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Fiber Optic Cable Duplicate',
                'slug' => 'fiber-optic-cable',
                'status' => 'draft',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }
}
