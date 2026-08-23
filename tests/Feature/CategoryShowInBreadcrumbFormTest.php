<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Models\Category;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryShowInBreadcrumbFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_an_admin_can_turn_off_show_in_breadcrumb_for_an_existing_category(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $category = Category::factory()->create(['show_in_breadcrumb' => true]);

        Livewire::test(EditCategory::class, ['record' => $category->id])
            ->fillForm(['show_in_breadcrumb' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse((bool) $category->fresh()->show_in_breadcrumb);
    }

    public function test_the_edit_form_pre_fills_show_in_breadcrumb_from_the_record(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        $category = Category::factory()->create(['show_in_breadcrumb' => false]);

        Livewire::test(EditCategory::class, ['record' => $category->id])
            ->assertFormSet(['show_in_breadcrumb' => false]);
    }
}
