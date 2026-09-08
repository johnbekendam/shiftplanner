<?php

namespace Tests\Unit;

use App\Services\ThemeTokens;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThemeTokensBrandFamilyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Isolate from the real storage/app/private/theme-tokens.json so these
        // tests neither read the shipped theme nor delete a developer's saved one.
        Storage::fake('local');
    }

    public function test_default_brand_family_is_sky(): void
    {
        $this->assertSame('sky', (new ThemeTokens)->load()['brand_family']);
    }

    public function test_render_css_emits_the_brand_ramp_pointing_at_the_family(): void
    {
        $css = (new ThemeTokens)->renderCss();

        $this->assertStringContainsString('--color-brand-500: var(--color-sky-500);', $css);
        $this->assertStringContainsString('--color-brand-950: var(--color-sky-950);', $css);

        Storage::put('theme-tokens.json', json_encode([
            'brand_family' => 'emerald',
            'colors' => [],
        ]));

        $css = (new ThemeTokens)->renderCss();
        $this->assertStringContainsString('--color-brand-500: var(--color-emerald-500);', $css);
        $this->assertStringNotContainsString('--color-brand-500: var(--color-sky-500);', $css);
    }

    public function test_an_unknown_family_falls_back_to_the_default(): void
    {
        Storage::put('theme-tokens.json', json_encode([
            'brand_family' => 'not-a-family',
            'colors' => [],
        ]));

        $this->assertSame('sky', (new ThemeTokens)->load()['brand_family']);
    }

    public function test_email_colours_resolve_brand_values(): void
    {
        Storage::put('theme-tokens.json', json_encode([
            'brand_family' => 'rose',
            'colors' => ['brand_bg' => ['light' => 'brand-600', 'dark' => 'brand-600']],
        ]));

        $hex = (new ThemeTokens)->emailColors()['brand'];
        $this->assertSame('#e11d48', $hex); // rose-600
    }
}
