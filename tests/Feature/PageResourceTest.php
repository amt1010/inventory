<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Models\Page;
use App\Models\Staff;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_create_a_page_with_a_hero_block(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'About Us',
                'slug' => 'about',
                'status' => 'published',
                'content' => [
                    [
                        'type' => 'hero',
                        'data' => ['heading' => 'Welcome', 'subheading' => 'We build cable.'],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::where('slug', 'about')->firstOrFail();

        $this->assertSame('hero', $page->content[0]['type']);
        $this->assertSame('Welcome', $page->content[0]['data']['heading']);
    }

    public function test_a_second_page_cannot_reuse_an_existing_slug(): void
    {
        $admin = Staff::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'staff');

        Page::factory()->create(['slug' => 'contact-us']);

        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'Contact Us Duplicate',
                'slug' => 'contact-us',
                'status' => 'draft',
                'content' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_content_editor_gets_a_403_visiting_pages_if_not_authorized(): void
    {
        $sales = Staff::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($sales, 'staff');

        $response = $this->get('/admin/pages/create');

        $response->assertForbidden();
    }
}
