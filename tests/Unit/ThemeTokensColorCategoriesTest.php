<?php

namespace Tests\Unit;

use App\Services\ThemeTokens;
use Tests\TestCase;

class ThemeTokensColorCategoriesTest extends TestCase
{
    private function categories(): array
    {
        return (new ThemeTokens)->colorCategories();
    }

    public function test_nine_tabs_in_order(): void
    {
        $this->assertSame(
            ['chrome', 'menu', 'text', 'buttons', 'tabs', 'table', 'forms', 'status', 'surface'],
            array_column($this->categories(), 'key'),
        );
    }

    public function test_partitions_every_listed_token_exactly_once(): void
    {
        $seen = [];
        foreach ($this->categories() as $cat) {
            foreach ($cat['colors'] as $c) {
                $this->assertArrayNotHasKey($c['key'], $seen, "{$c['key']} shown twice");
                $seen[$c['key']] = true;
            }
        }

        $themeTokens = new ThemeTokens;
        $tokenKeys = array_diff(
            array_keys($themeTokens->colorDefaults()),
            $themeTokens->unlistedColors(),
        );
        sort($tokenKeys);
        $partitioned = array_keys($seen);
        sort($partitioned);
        $this->assertSame($tokenKeys, $partitioned);
    }

    public function test_unlisted_colors_appear_in_no_category(): void
    {
        $seen = [];
        foreach ($this->categories() as $cat) {
            foreach ($cat['colors'] as $c) {
                $seen[] = $c['key'];
            }
        }

        foreach ((new ThemeTokens)->unlistedColors() as $key) {
            $this->assertNotContains($key, $seen);
        }
    }

    public function test_chrome_and_menu_hold_their_keys(): void
    {
        $byKey = collect($this->categories())->keyBy('key');

        $this->assertSame(
            ['header_bg', 'header_text', 'sidebar_bg', 'sidebar_text', 'content_bg', 'content_text', 'separator_horizontal', 'separator_vertical'],
            array_column($byKey['chrome']['colors'], 'key'),
        );
        $this->assertSame(
            [
                'menu_item_bg', 'menu_item_text',
                'menu_item_hover_bg', 'menu_item_hover_text',
                'menu_item_selected_bg', 'menu_item_selected_text',
                'menu_item_disabled_bg', 'menu_item_disabled_text',
            ],
            array_column($byKey['menu']['colors'], 'key'),
        );
    }

    public function test_tabs_and_table_hold_their_keys(): void
    {
        $byKey = collect($this->categories())->keyBy('key');

        $this->assertSame(
            [
                'tab_bg', 'tab_text', 'tab_separator', 'tab_inactive_bg',
                'tab_active_bg', 'tab_active_text', 'tab_active_border',
                'tab_hover_bg', 'tab_hover_text', 'tab_hover_border',
            ],
            array_column($byKey['tabs']['colors'], 'key'),
        );
        $this->assertSame(
            [
                'table_header_bg', 'table_header_text', 'table_header_separator',
                'table_row_bg', 'table_row_text', 'table_row_separator',
                'table_row_hover_bg', 'table_row_hover_text',
                'table_row_selected_bg', 'table_row_selected_text',
                'pagination_active_bg', 'pagination_active_text',
            ],
            array_column($byKey['table']['colors'], 'key'),
        );
    }

    public function test_no_families_key(): void
    {
        foreach ($this->categories() as $cat) {
            $this->assertArrayNotHasKey('families', $cat);
            $this->assertArrayNotHasKey('roles', $cat);
        }
    }

    public function test_labels_are_humanised(): void
    {
        $byKey = collect($this->categories())->keyBy('key');
        $labels = collect($byKey['buttons']['colors'])->keyBy('key');

        $this->assertSame('Btn secondary hover bg', $labels['btn_secondary_hover_bg']['label']);
        $this->assertSame('Btn primary disabled text', $labels['btn_primary_disabled_text']['label']);
    }
}
