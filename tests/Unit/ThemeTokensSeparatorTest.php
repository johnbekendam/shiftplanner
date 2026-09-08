<?php

namespace Tests\Unit;

use App\Services\ThemeTokens;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThemeTokensSeparatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_separators_carry_an_enabled_flag_and_default_off(): void
    {
        $colors = (new ThemeTokens)->colorDefaults();

        $this->assertFalse($colors['separator_horizontal']['enabled']);
        $this->assertFalse($colors['separator_vertical']['enabled']);
    }

    public function test_disabled_separator_renders_transparent(): void
    {
        $css = (new ThemeTokens)->renderCss();

        $this->assertStringContainsString('--color-separator-horizontal: transparent;', $css);
        $this->assertStringContainsString('--color-separator-vertical: transparent;', $css);
    }

    public function test_enabled_separator_renders_its_colour(): void
    {
        Storage::put('theme-tokens.json', json_encode(['colors' => [
            'separator_vertical' => ['enabled' => true, 'light' => 'red-500', 'dark' => 'red-700'],
        ]]));

        $css = (new ThemeTokens)->renderCss();

        $this->assertStringContainsString('--color-separator-vertical: var(--color-red-500);', $css);
        $this->assertStringContainsString('--color-separator-horizontal: transparent;', $css);
    }
}
