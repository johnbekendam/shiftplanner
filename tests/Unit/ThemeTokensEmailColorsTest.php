<?php

namespace Tests\Unit;

use App\Services\ThemeTokens;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThemeTokensEmailColorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_email_colors_resolves_roles_to_literal_hex(): void
    {
        $colors = (new ThemeTokens)->emailColors();

        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $colors['text_primary']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $colors['brand']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $colors['brand_strong']);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $colors['on_brand_fill']);
    }
}
