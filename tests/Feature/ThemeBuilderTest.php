<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThemeBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Never touch the real storage/app/private/theme-tokens.json.
        Storage::fake('local');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/theme-builder')->assertRedirect('/login');
    }

    public function test_index_shares_flat_colors_and_categories(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get('/theme-builder');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ThemeBuilder')
            ->has('categories', 9)
            ->where('categories.0.key', 'chrome')
            ->where('categories.1.key', 'menu')
            ->where('categories.4.key', 'tabs')
            ->where('categories.5.key', 'table')
            ->where('categories.8.key', 'surface')
            ->has('categories.0.colors')
            ->has('colors.header_bg')
            ->has('defaultColors.menu_item_hover_bg')
            ->where('brandFamily', 'sky')
            ->where('defaultBrandFamily', 'sky')
        );
    }

    public function test_save_persists_the_colors_map_and_brand_family(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->postJson('/theme-builder/save', [
            'colors' => ['header_bg' => ['light' => 'red-500', 'dark' => 'red-700']],
            'brand_family' => 'emerald',
        ])->assertOk();

        $stored = json_decode(Storage::get('theme-tokens.json'), true);
        $this->assertSame('red-500', $stored['colors']['header_bg']['light']);
        $this->assertSame('emerald', $stored['brand_family']);

        $this->postJson('/theme-builder/save', [
            'colors' => [],
            'brand_family' => 'not-a-family',
        ])->assertStatus(422);
    }
}
