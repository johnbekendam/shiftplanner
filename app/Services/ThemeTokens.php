<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Two-layer token model: a small set of "roles" (~30 directly stored — including the
 * brand-identity roles, individually settable) plus four badge families that expand into
 * ramps via a fixed shade table are what a person actually edits. The ~100 component-level
 * `--color-*` CSS vars used throughout the app stay exactly as they are — each just becomes
 * `var(--role-x)` instead of an independently stored value. See doc/roadmap.md phase 1 and
 * git history for how this was derived (1b introduced a brand-family formula; 1d replaced it
 * with direct per-role control since a single shade ramp didn't give enough branding control).
 */
class ThemeTokens
{
    /**
     * The branding family. A token value of `brand-600` resolves through the
     * `--color-brand-*` ramp, which points at this family's shades. Change the
     * family and every brand-* token re-skins.
     *
     * ShiftPlanner ships on `sky`. This and the `brand-*` COLOR_DEFAULTS below
     * are the theme saved from the Theme Builder, baked in as the app default.
     */
    public const BRAND_FAMILY_DEFAULT = 'sky';

    private const SHADES = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];

    private const BRAND_FAMILIES = [
        'slate', 'gray', 'zinc', 'neutral', 'stone',
        'red', 'orange', 'amber', 'yellow',
        'lime', 'green', 'emerald', 'teal',
        'cyan', 'sky', 'blue', 'indigo',
        'violet', 'purple', 'fuchsia', 'pink', 'rose',
    ];

    /**
     * Flat token model: every editable component `--color-*` var as its own
     * stored token. These defaults are the ShiftPlanner theme saved from the
     * Theme Builder — the accent tokens point at `brand-*` (the `sky` ramp) in
     * both light and dark, the rest are the template's neutral baseline. The
     * five always-transparent borders (menu item borders, inactive tab border)
     * are not tokens — they stay hard-coded. Edit and re-save in the Theme
     * Builder, then re-bake (`app.css` + this array) for a new shipped default.
     */
    private const COLOR_DEFAULTS = [
        'badge_custom_bg' => ['light' => 'indigo-100', 'dark' => 'brand-900'],
        'badge_custom_border' => ['light' => 'brand-200', 'dark' => 'brand-800'],
        'badge_custom_text' => ['light' => 'brand-700', 'dark' => 'brand-300'],
        'badge_error_bg' => ['light' => 'red-100', 'dark' => 'red-900'],
        'badge_error_border' => ['light' => 'red-200', 'dark' => 'red-800'],
        'badge_error_text' => ['light' => 'red-700', 'dark' => 'red-300'],
        'badge_muted_bg' => ['light' => 'zinc-100', 'dark' => 'zinc-700'],
        'badge_muted_border' => ['light' => 'zinc-200', 'dark' => 'zinc-600'],
        'badge_muted_text' => ['light' => 'zinc-400', 'dark' => 'zinc-400'],
        'badge_standard_bg' => ['light' => 'zinc-100', 'dark' => 'zinc-800'],
        'badge_standard_border' => ['light' => 'zinc-200', 'dark' => 'zinc-700'],
        'badge_standard_text' => ['light' => 'zinc-700', 'dark' => 'zinc-300'],
        'badge_success_bg' => ['light' => 'green-100', 'dark' => 'green-900'],
        'badge_success_border' => ['light' => 'green-200', 'dark' => 'green-800'],
        'badge_success_text' => ['light' => 'green-700', 'dark' => 'green-300'],
        'badge_warning_bg' => ['light' => 'yellow-100', 'dark' => 'yellow-900'],
        'badge_warning_border' => ['light' => 'yellow-200', 'dark' => 'yellow-800'],
        'badge_warning_text' => ['light' => 'yellow-700', 'dark' => 'yellow-300'],
        // brand_bg / brand_text drive the checkbox check, radio dot, toggle,
        // slider fill, and the email accent. Pagination has its own
        // pagination_active_* tokens now. Not shown in the builder — see
        // UNLISTED_COLORS; brand_bg follows the brand ramp until it gets its
        // own picker. The primary button has its own btn_primary_* tokens.
        'brand_bg' => ['light' => 'brand-600', 'dark' => 'brand-600'],
        'brand_text' => ['light' => 'white', 'dark' => 'white'],

        // Buttons: variant x state x slot. Disabled shares one muted look.
        'btn_primary_bg' => ['light' => 'brand-600', 'dark' => 'brand-600'],
        'btn_primary_text' => ['light' => 'white', 'dark' => 'white'],
        'btn_primary_border' => ['light' => 'transparent', 'dark' => 'transparent'],
        'btn_primary_hover_bg' => ['light' => 'brand-500', 'dark' => 'brand-500'],
        'btn_primary_hover_text' => ['light' => 'white', 'dark' => 'white'],
        'btn_primary_hover_border' => ['light' => 'transparent', 'dark' => 'transparent'],
        'btn_primary_disabled_bg' => ['light' => 'zinc-100', 'dark' => 'zinc-800'],
        'btn_primary_disabled_text' => ['light' => 'zinc-400', 'dark' => 'zinc-600'],
        'btn_primary_disabled_border' => ['light' => 'transparent', 'dark' => 'transparent'],

        'btn_secondary_bg' => ['light' => 'white', 'dark' => 'zinc-800'],
        'btn_secondary_text' => ['light' => 'zinc-950', 'dark' => 'zinc-100'],
        'btn_secondary_border' => ['light' => 'zinc-300', 'dark' => 'zinc-600'],
        'btn_secondary_hover_bg' => ['light' => 'zinc-50', 'dark' => 'zinc-700'],
        'btn_secondary_hover_text' => ['light' => 'zinc-950', 'dark' => 'zinc-100'],
        'btn_secondary_hover_border' => ['light' => 'zinc-300', 'dark' => 'zinc-600'],
        'btn_secondary_disabled_bg' => ['light' => 'zinc-100', 'dark' => 'zinc-800'],
        'btn_secondary_disabled_text' => ['light' => 'zinc-400', 'dark' => 'zinc-600'],
        'btn_secondary_disabled_border' => ['light' => 'transparent', 'dark' => 'transparent'],

        'btn_danger_bg' => ['light' => 'red-600', 'dark' => 'red-600'],
        'btn_danger_text' => ['light' => 'white', 'dark' => 'white'],
        'btn_danger_border' => ['light' => 'transparent', 'dark' => 'transparent'],
        'btn_danger_hover_bg' => ['light' => 'red-500', 'dark' => 'red-500'],
        'btn_danger_hover_text' => ['light' => 'white', 'dark' => 'white'],
        'btn_danger_hover_border' => ['light' => 'transparent', 'dark' => 'transparent'],
        'btn_danger_disabled_bg' => ['light' => 'zinc-100', 'dark' => 'zinc-800'],
        'btn_danger_disabled_text' => ['light' => 'zinc-400', 'dark' => 'zinc-600'],
        'btn_danger_disabled_border' => ['light' => 'transparent', 'dark' => 'transparent'],
        // Two chrome dividers. Off by default; each has an enable flag — when
        // false, renderCss() emits transparent.
        'separator_horizontal' => ['enabled' => false, 'light' => 'zinc-200', 'dark' => 'zinc-700'],
        'separator_vertical' => ['enabled' => false, 'light' => 'zinc-200', 'dark' => 'zinc-700'],
        'content_bg' => ['light' => 'white', 'dark' => 'zinc-800'],
        'content_text' => ['light' => 'zinc-700', 'dark' => 'zinc-300'],
        'dropdown_option_bg' => ['light' => 'white', 'dark' => 'zinc-800'],
        'dropdown_option_hover_bg' => ['light' => 'zinc-50', 'dark' => 'zinc-700'],
        'dropdown_option_hover_text' => ['light' => 'zinc-700', 'dark' => 'zinc-300'],
        'dropdown_option_selected_bg' => ['light' => 'indigo-50', 'dark' => 'brand-900'],
        'dropdown_option_selected_text' => ['light' => 'brand-700', 'dark' => 'brand-300'],
        'dropdown_option_text' => ['light' => 'zinc-700', 'dark' => 'zinc-300'],
        'dropdown_panel_bg' => ['light' => 'white', 'dark' => 'zinc-800'],
        'dropdown_panel_border' => ['light' => 'zinc-200', 'dark' => 'zinc-700'],
        // Logo merged into header: no separate logo_bg/logo_text token. The
        // --color-logo-* CSS vars still exist, derived from these in app.css.
        'header_bg' => ['light' => 'brand-800', 'dark' => 'brand-800'],
        'header_text' => ['light' => 'brand-200', 'dark' => 'indigo-200'],
        'input_bg' => ['light' => 'white', 'dark' => 'zinc-800'],
        'input_border' => ['light' => 'zinc-300', 'dark' => 'zinc-600'],
        'input_disabled_bg' => ['light' => 'zinc-50', 'dark' => 'zinc-900'],
        'input_disabled_border' => ['light' => 'zinc-200', 'dark' => 'zinc-700'],
        'input_disabled_text' => ['light' => 'zinc-400', 'dark' => 'zinc-600'],
        'input_focus_border' => ['light' => 'brand-500', 'dark' => 'brand-400'],
        'input_focus_text' => ['light' => 'zinc-700', 'dark' => 'zinc-300'],
        'input_invalid_border' => ['light' => 'red-600', 'dark' => 'red-600'],
        'input_invalid_text' => ['light' => 'red-600', 'dark' => 'red-600'],
        'input_placeholder' => ['light' => 'zinc-400', 'dark' => 'zinc-500'],
        'input_text' => ['light' => 'zinc-700', 'dark' => 'zinc-300'],
        'menu_item_bg' => ['light' => 'transparent', 'dark' => 'transparent'],
        'menu_item_disabled_bg' => ['light' => 'transparent', 'dark' => 'transparent'],
        'menu_item_disabled_text' => ['light' => 'zinc-400', 'dark' => 'zinc-600'],
        'menu_item_hover_bg' => ['light' => 'zinc-100', 'dark' => 'zinc-800'],
        'menu_item_hover_text' => ['light' => 'brand-600', 'dark' => 'brand-400'],
        'menu_item_selected_bg' => ['light' => 'zinc-100', 'dark' => 'zinc-800'],
        'menu_item_selected_text' => ['light' => 'brand-600', 'dark' => 'brand-400'],
        'menu_item_text' => ['light' => 'zinc-700', 'dark' => 'zinc-300'],
        'overlay' => ['light' => 'black/40', 'dark' => 'black/40'],
        // The active-page pill on the Table tab. Was derived from brand_bg/text;
        // now its own token so it doesn't drag the checkbox/toggle/slider colour
        // along with it.
        'pagination_active_bg' => ['light' => 'brand-600', 'dark' => 'brand-600'],
        'pagination_active_text' => ['light' => 'white', 'dark' => 'white'],
        'sidebar_bg' => ['light' => 'zinc-50', 'dark' => 'zinc-900'],
        'sidebar_text' => ['light' => 'zinc-400', 'dark' => 'zinc-500'],
        'surface_bg' => ['light' => 'white', 'dark' => 'zinc-800'],
        'surface_border' => ['light' => 'zinc-200', 'dark' => 'zinc-700'],
        'surface_secondary_bg' => ['light' => 'zinc-50', 'dark' => 'zinc-900'],
        'tab_active_bg' => ['light' => 'white', 'dark' => 'zinc-800'],
        'tab_active_border' => ['light' => 'brand-600', 'dark' => 'brand-400'],
        'tab_active_text' => ['light' => 'brand-600', 'dark' => 'brand-400'],
        'tab_bg' => ['light' => 'white', 'dark' => 'zinc-800'],
        'tab_hover_bg' => ['light' => 'zinc-50', 'dark' => 'zinc-700'],
        'tab_hover_border' => ['light' => 'zinc-200', 'dark' => 'zinc-700'],
        'tab_hover_text' => ['light' => 'zinc-700', 'dark' => 'zinc-200'],
        'tab_inactive_bg' => ['light' => 'white', 'dark' => 'zinc-800'],
        'tab_separator' => ['light' => 'zinc-200', 'dark' => 'zinc-700'],
        'tab_text' => ['light' => 'zinc-500', 'dark' => 'zinc-400'],
        'table_header_bg' => ['light' => 'zinc-50', 'dark' => 'zinc-900'],
        'table_header_separator' => ['light' => 'zinc-200', 'dark' => 'zinc-700'],
        'table_header_text' => ['light' => 'zinc-500', 'dark' => 'zinc-400'],
        'table_row_bg' => ['light' => 'white', 'dark' => 'zinc-800'],
        'table_row_hover_bg' => ['light' => 'zinc-50', 'dark' => 'zinc-700'],
        'table_row_hover_text' => ['light' => 'zinc-700', 'dark' => 'zinc-300'],
        'table_row_selected_bg' => ['light' => 'brand-50', 'dark' => 'brand-900'],
        'table_row_selected_text' => ['light' => 'brand-700', 'dark' => 'indigo-300'],
        'table_row_separator' => ['light' => 'zinc-200', 'dark' => 'zinc-700'],
        'table_row_text' => ['light' => 'zinc-700', 'dark' => 'zinc-300'],
        // text_body derives from text_primary; text_secondary from text_muted.
        'text_heading' => ['light' => 'zinc-700', 'dark' => 'zinc-200'],
        'text_link' => ['light' => 'brand-600', 'dark' => 'brand-500'],
        'text_link_hover' => ['light' => 'brand-500', 'dark' => 'brand-400'],
        'text_muted' => ['light' => 'zinc-400', 'dark' => 'zinc-500'],
        'text_primary' => ['light' => 'zinc-700', 'dark' => 'zinc-300'],
    ];

    public function colorDefaults(): array
    {
        return self::COLOR_DEFAULTS;
    }

    /**
     * The nine flat-model tabs, in order, each with the token keys that live
     * under it. A straight partition of every token not in UNLISTED_COLORS —
     * each appears exactly once. Chrome, Menu, Tabs, and Table get a table
     * layout in the builder; the rest a plain list.
     */
    private const CATEGORY_COLORS = [
        'chrome' => ['label' => 'Chrome', 'keys' => [
            'header_bg', 'header_text', 'sidebar_bg', 'sidebar_text',
            'content_bg', 'content_text',
            'separator_horizontal', 'separator_vertical',
        ]],
        'menu' => ['label' => 'Menu', 'keys' => [
            'menu_item_bg', 'menu_item_text',
            'menu_item_hover_bg', 'menu_item_hover_text',
            'menu_item_selected_bg', 'menu_item_selected_text',
            'menu_item_disabled_bg', 'menu_item_disabled_text',
        ]],
        'text' => ['label' => 'Text', 'keys' => [
            'text_heading', 'text_primary', 'text_muted',
            'text_link', 'text_link_hover',
        ]],
        'buttons' => ['label' => 'Buttons', 'keys' => [
            'btn_primary_bg', 'btn_primary_text', 'btn_primary_border',
            'btn_primary_hover_bg', 'btn_primary_hover_text', 'btn_primary_hover_border',
            'btn_primary_disabled_bg', 'btn_primary_disabled_text', 'btn_primary_disabled_border',
            'btn_secondary_bg', 'btn_secondary_text', 'btn_secondary_border',
            'btn_secondary_hover_bg', 'btn_secondary_hover_text', 'btn_secondary_hover_border',
            'btn_secondary_disabled_bg', 'btn_secondary_disabled_text', 'btn_secondary_disabled_border',
            'btn_danger_bg', 'btn_danger_text', 'btn_danger_border',
            'btn_danger_hover_bg', 'btn_danger_hover_text', 'btn_danger_hover_border',
            'btn_danger_disabled_bg', 'btn_danger_disabled_text', 'btn_danger_disabled_border',
        ]],
        'tabs' => ['label' => 'Tabs', 'keys' => [
            'tab_bg', 'tab_text', 'tab_separator', 'tab_inactive_bg',
            'tab_active_bg', 'tab_active_text', 'tab_active_border',
            'tab_hover_bg', 'tab_hover_text', 'tab_hover_border',
        ]],
        'table' => ['label' => 'Table', 'keys' => [
            'table_header_bg', 'table_header_text', 'table_header_separator',
            'table_row_bg', 'table_row_text', 'table_row_separator',
            'table_row_hover_bg', 'table_row_hover_text',
            'table_row_selected_bg', 'table_row_selected_text',
            'pagination_active_bg', 'pagination_active_text',
        ]],
        'forms' => ['label' => 'Forms', 'keys' => [
            'input_bg', 'input_text', 'input_border', 'input_placeholder',
            'input_focus_border', 'input_focus_text',
            'input_disabled_bg', 'input_disabled_text', 'input_disabled_border',
            'input_invalid_border', 'input_invalid_text',
            'dropdown_panel_bg', 'dropdown_panel_border',
            'dropdown_option_bg', 'dropdown_option_text',
            'dropdown_option_hover_bg', 'dropdown_option_hover_text',
            'dropdown_option_selected_bg', 'dropdown_option_selected_text',
        ]],
        'status' => ['label' => 'Status & feedback', 'keys' => [
            'badge_success_bg', 'badge_success_text', 'badge_success_border',
            'badge_warning_bg', 'badge_warning_text', 'badge_warning_border',
            'badge_error_bg', 'badge_error_text', 'badge_error_border',
            'badge_custom_bg', 'badge_custom_text', 'badge_custom_border',
            'badge_standard_bg', 'badge_standard_text', 'badge_standard_border',
            'badge_muted_bg', 'badge_muted_text', 'badge_muted_border',
        ]],
        'surface' => ['label' => 'Surface', 'keys' => [
            'surface_bg', 'surface_secondary_bg', 'surface_border', 'overlay',
        ]],
    ];

    /**
     * Real, stored tokens with no picker in the builder — kept off the tab
     * partition intentionally. brand_bg/brand_text still drive the checkbox
     * check, radio dot, toggle, slider fill, and the email accent; pagination
     * has its own pagination_active_* tokens. See doc/features/data-tab/spec.md.
     */
    private const UNLISTED_COLORS = ['brand_bg', 'brand_text'];

    public function unlistedColors(): array
    {
        return self::UNLISTED_COLORS;
    }

    /** The nine tabs, each with its ordered `colors` list of `{key, label}`. */
    public function colorCategories(): array
    {
        $result = [];
        foreach (self::CATEGORY_COLORS as $key => $meta) {
            $result[] = [
                'key' => $key,
                'label' => $meta['label'],
                'colors' => array_map(
                    fn ($k) => ['key' => $k, 'label' => $this->humanizeToken($k)],
                    $meta['keys'],
                ),
            ];
        }

        return $result;
    }

    private function humanizeToken(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Not tokens — emitted straight to :root as transparent. The borders are
     * always transparent; page_bg is transparent because the layout covers the
     * whole screen, so an outer background is never seen.
     */
    private const KEPT_LITERALS = [
        'menu_item_border', 'menu_item_hover_border', 'menu_item_selected_border',
        'menu_item_disabled_border', 'tab_border', 'page_bg',
    ];

    /** Twelve keys the email templates read, each mapped to a flat token. */
    private const EMAIL_COLOR_MAP = [
        'surface' => 'content_bg',
        'surface_secondary' => 'surface_secondary_bg',
        'text_primary' => 'text_primary',
        'text_secondary' => 'text_muted',
        'text_heading' => 'text_heading',
        'text_link' => 'text_link',
        'brand' => 'brand_bg',
        'brand_strong' => 'header_bg',
        'on_brand' => 'header_text',
        'on_brand_fill' => 'brand_text',
        'border' => 'surface_border',
        'page' => 'surface_secondary_bg',
    ];

    public function brandFamilyDefault(): string
    {
        return self::BRAND_FAMILY_DEFAULT;
    }

    public function brandFamilies(): array
    {
        return self::BRAND_FAMILIES;
    }

    public function load(): array
    {
        $colors = self::COLOR_DEFAULTS;
        $brandFamily = self::BRAND_FAMILY_DEFAULT;

        if (Storage::exists('theme-tokens.json')) {
            $stored = json_decode(Storage::get('theme-tokens.json'), true) ?? [];

            foreach ($stored['colors'] ?? [] as $key => $values) {
                if (isset($colors[$key])) {
                    if (isset($values['light'])) {
                        $colors[$key]['light'] = $values['light'];
                    }
                    if (isset($values['dark'])) {
                        $colors[$key]['dark'] = $values['dark'];
                    }
                    if (array_key_exists('enabled', $colors[$key]) && isset($values['enabled'])) {
                        $colors[$key]['enabled'] = (bool) $values['enabled'];
                    }
                }
            }

            if (in_array($stored['brand_family'] ?? null, self::BRAND_FAMILIES, true)) {
                $brandFamily = $stored['brand_family'];
            }
        }

        return ['colors' => $colors, 'brand_family' => $brandFamily];
    }

    /** The twelve email keys resolved to literal light-mode hex. */
    public function emailColors(): array
    {
        $state = $this->load();
        $colors = $state['colors'];
        $brandFamily = $state['brand_family'];

        $result = [];
        foreach (self::EMAIL_COLOR_MAP as $emailKey => $tokenKey) {
            $value = $colors[$tokenKey]['light'];
            if (str_starts_with($value, 'brand-')) {
                $value = $brandFamily.substr($value, strlen('brand'));
            }
            $result[$emailKey] = $this->tailwindToHex($value);
        }

        return $result;
    }

    private function tailwindToHex(string $color): string
    {
        if ($color === '' || $color === 'transparent') {
            return 'transparent';
        }

        $base = explode('/', $color, 2)[0];
        if (in_array($base, ['white', 'black'])) {
            return self::TAILWIND_HEX[$base];
        }

        return self::TAILWIND_HEX[$base] ?? '#000000';
    }

    /**
     * Standard Tailwind CSS color palette — static, stable data, not project-specific.
     * Covers every family/shade ColorTokenPicker lets a user pick, so any role value chosen
     * in the theme builder resolves to a real color in emails rather than silently going black.
     */
    private const TAILWIND_HEX = [
        'white' => '#ffffff', 'black' => '#000000',
        'slate-50' => '#f8fafc', 'slate-100' => '#f1f5f9', 'slate-200' => '#e2e8f0', 'slate-300' => '#cbd5e1',
        'slate-400' => '#94a3b8', 'slate-500' => '#64748b', 'slate-600' => '#475569', 'slate-700' => '#334155',
        'slate-800' => '#1e293b', 'slate-900' => '#0f172a', 'slate-950' => '#020617',
        'gray-50' => '#f9fafb', 'gray-100' => '#f3f4f6', 'gray-200' => '#e5e7eb', 'gray-300' => '#d1d5db',
        'gray-400' => '#9ca3af', 'gray-500' => '#6b7280', 'gray-600' => '#4b5563', 'gray-700' => '#374151',
        'gray-800' => '#1f2937', 'gray-900' => '#111827', 'gray-950' => '#030712',
        'zinc-50' => '#fafafa', 'zinc-100' => '#f4f4f5', 'zinc-200' => '#e4e4e7', 'zinc-300' => '#d4d4d8',
        'zinc-400' => '#a1a1aa', 'zinc-500' => '#71717a', 'zinc-600' => '#52525b', 'zinc-700' => '#3f3f46',
        'zinc-800' => '#27272a', 'zinc-900' => '#18181b', 'zinc-950' => '#09090b',
        'neutral-50' => '#fafafa', 'neutral-100' => '#f5f5f5', 'neutral-200' => '#e5e5e5', 'neutral-300' => '#d4d4d4',
        'neutral-400' => '#a3a3a3', 'neutral-500' => '#737373', 'neutral-600' => '#525252', 'neutral-700' => '#404040',
        'neutral-800' => '#262626', 'neutral-900' => '#171717', 'neutral-950' => '#0a0a0a',
        'stone-50' => '#fafaf9', 'stone-100' => '#f5f5f4', 'stone-200' => '#e7e5e4', 'stone-300' => '#d6d3d1',
        'stone-400' => '#a8a29e', 'stone-500' => '#78716c', 'stone-600' => '#57534e', 'stone-700' => '#44403c',
        'stone-800' => '#292524', 'stone-900' => '#1c1917', 'stone-950' => '#0c0a09',
        'red-50' => '#fef2f2', 'red-100' => '#fee2e2', 'red-200' => '#fecaca', 'red-300' => '#fca5a5',
        'red-400' => '#f87171', 'red-500' => '#ef4444', 'red-600' => '#dc2626', 'red-700' => '#b91c1c',
        'red-800' => '#991b1b', 'red-900' => '#7f1d1d', 'red-950' => '#450a0a',
        'orange-50' => '#fff7ed', 'orange-100' => '#ffedd5', 'orange-200' => '#fed7aa', 'orange-300' => '#fdba74',
        'orange-400' => '#fb923c', 'orange-500' => '#f97316', 'orange-600' => '#ea580c', 'orange-700' => '#c2410c',
        'orange-800' => '#9a3412', 'orange-900' => '#7c2d12', 'orange-950' => '#431407',
        'amber-50' => '#fffbeb', 'amber-100' => '#fef3c7', 'amber-200' => '#fde68a', 'amber-300' => '#fcd34d',
        'amber-400' => '#fbbf24', 'amber-500' => '#f59e0b', 'amber-600' => '#d97706', 'amber-700' => '#b45309',
        'amber-800' => '#92400e', 'amber-900' => '#78350f', 'amber-950' => '#451a03',
        'yellow-50' => '#fefce8', 'yellow-100' => '#fef9c3', 'yellow-200' => '#fef08a', 'yellow-300' => '#fde047',
        'yellow-400' => '#facc15', 'yellow-500' => '#eab308', 'yellow-600' => '#ca8a04', 'yellow-700' => '#a16207',
        'yellow-800' => '#854d0e', 'yellow-900' => '#713f12', 'yellow-950' => '#422006',
        'lime-50' => '#f7fee7', 'lime-100' => '#ecfccb', 'lime-200' => '#d9f99d', 'lime-300' => '#bef264',
        'lime-400' => '#a3e635', 'lime-500' => '#84cc16', 'lime-600' => '#65a30d', 'lime-700' => '#4d7c0f',
        'lime-800' => '#3f6212', 'lime-900' => '#365314', 'lime-950' => '#1a2e05',
        'green-50' => '#f0fdf4', 'green-100' => '#dcfce7', 'green-200' => '#bbf7d0', 'green-300' => '#86efac',
        'green-400' => '#4ade80', 'green-500' => '#22c55e', 'green-600' => '#16a34a', 'green-700' => '#15803d',
        'green-800' => '#166534', 'green-900' => '#14532d', 'green-950' => '#052e16',
        'emerald-50' => '#ecfdf5', 'emerald-100' => '#d1fae5', 'emerald-200' => '#a7f3d0', 'emerald-300' => '#6ee7b7',
        'emerald-400' => '#34d399', 'emerald-500' => '#10b981', 'emerald-600' => '#059669', 'emerald-700' => '#047857',
        'emerald-800' => '#065f46', 'emerald-900' => '#064e3b', 'emerald-950' => '#022c22',
        'teal-50' => '#f0fdfa', 'teal-100' => '#ccfbf1', 'teal-200' => '#99f6e4', 'teal-300' => '#5eead4',
        'teal-400' => '#2dd4bf', 'teal-500' => '#14b8a6', 'teal-600' => '#0d9488', 'teal-700' => '#0f766e',
        'teal-800' => '#115e59', 'teal-900' => '#134e4a', 'teal-950' => '#042f2e',
        'cyan-50' => '#ecfeff', 'cyan-100' => '#cffafe', 'cyan-200' => '#a5f3fc', 'cyan-300' => '#67e8f9',
        'cyan-400' => '#22d3ee', 'cyan-500' => '#06b6d4', 'cyan-600' => '#0891b2', 'cyan-700' => '#0e7490',
        'cyan-800' => '#155e75', 'cyan-900' => '#164e63', 'cyan-950' => '#083344',
        'sky-50' => '#f0f9ff', 'sky-100' => '#e0f2fe', 'sky-200' => '#bae6fd', 'sky-300' => '#7dd3fc',
        'sky-400' => '#38bdf8', 'sky-500' => '#0ea5e9', 'sky-600' => '#0284c7', 'sky-700' => '#0369a1',
        'sky-800' => '#075985', 'sky-900' => '#0c4a6e', 'sky-950' => '#082f49',
        'blue-50' => '#eff6ff', 'blue-100' => '#dbeafe', 'blue-200' => '#bfdbfe', 'blue-300' => '#93c5fd',
        'blue-400' => '#60a5fa', 'blue-500' => '#3b82f6', 'blue-600' => '#2563eb', 'blue-700' => '#1d4ed8',
        'blue-800' => '#1e40af', 'blue-900' => '#1e3a8a', 'blue-950' => '#172554',
        'indigo-50' => '#eef2ff', 'indigo-100' => '#e0e7ff', 'indigo-200' => '#c7d2fe', 'indigo-300' => '#a5b4fc',
        'indigo-400' => '#818cf8', 'indigo-500' => '#6366f1', 'indigo-600' => '#4f46e5', 'indigo-700' => '#4338ca',
        'indigo-800' => '#3730a3', 'indigo-900' => '#312e81', 'indigo-950' => '#1e1b4b',
        'violet-50' => '#f5f3ff', 'violet-100' => '#ede9fe', 'violet-200' => '#ddd6fe', 'violet-300' => '#c4b5fd',
        'violet-400' => '#a78bfa', 'violet-500' => '#8b5cf6', 'violet-600' => '#7c3aed', 'violet-700' => '#6d28d9',
        'violet-800' => '#5b21b6', 'violet-900' => '#4c1d95', 'violet-950' => '#2e1065',
        'purple-50' => '#faf5ff', 'purple-100' => '#f3e8ff', 'purple-200' => '#e9d5ff', 'purple-300' => '#d8b4fe',
        'purple-400' => '#c084fc', 'purple-500' => '#a855f7', 'purple-600' => '#9333ea', 'purple-700' => '#7e22ce',
        'purple-800' => '#6b21a8', 'purple-900' => '#581c87', 'purple-950' => '#3b0764',
        'fuchsia-50' => '#fdf4ff', 'fuchsia-100' => '#fae8ff', 'fuchsia-200' => '#f5d0fe', 'fuchsia-300' => '#f0abfc',
        'fuchsia-400' => '#e879f9', 'fuchsia-500' => '#d946ef', 'fuchsia-600' => '#c026d3', 'fuchsia-700' => '#a21caf',
        'fuchsia-800' => '#86198f', 'fuchsia-900' => '#701a75', 'fuchsia-950' => '#4a044e',
        'pink-50' => '#fdf2f8', 'pink-100' => '#fce7f3', 'pink-200' => '#fbcfe8', 'pink-300' => '#f9a8d4',
        'pink-400' => '#f472b6', 'pink-500' => '#ec4899', 'pink-600' => '#db2777', 'pink-700' => '#be185d',
        'pink-800' => '#9d174d', 'pink-900' => '#831843', 'pink-950' => '#500724',
        'rose-50' => '#fff1f2', 'rose-100' => '#ffe4e6', 'rose-200' => '#fecdd3', 'rose-300' => '#fda4af',
        'rose-400' => '#fb7185', 'rose-500' => '#f43f5e', 'rose-600' => '#e11d48', 'rose-700' => '#be123c',
        'rose-800' => '#9f1239', 'rose-900' => '#881337', 'rose-950' => '#4c0519',
    ];

    public function resolveLogoUrl(): ?string
    {
        if (Storage::exists('logo.json')) {
            $data = json_decode(Storage::get('logo.json'), true);
            $filename = $data['filename'] ?? null;
            if ($filename && file_exists(public_path("images/{$filename}"))) {
                return asset("images/{$filename}");
            }
        }

        return null;
    }

    public function renderCss(): string
    {
        $state = $this->load();

        return $this->buildCss($state['colors'], $state['brand_family']);
    }

    private function buildCss(array $colors, string $brandFamily): string
    {
        $lightVars = [];
        $darkVars = [];

        foreach (self::SHADES as $shade) {
            $lightVars[] = "  --color-brand-{$shade}: var(--color-{$brandFamily}-{$shade});";
        }

        foreach ($colors as $key => $meta) {
            $var = '--color-'.str_replace('_', '-', $key);

            // A separator token with enabled=false renders as transparent.
            if (array_key_exists('enabled', $meta) && ! $meta['enabled']) {
                $lightVars[] = "  {$var}: transparent;";

                continue;
            }

            $lightVars[] = "  {$var}: {$this->cssVal($meta['light'])};";
            if ($meta['dark'] !== $meta['light']) {
                $darkVars[] = "  {$var}: {$this->cssVal($meta['dark'])};";
            }
        }

        foreach (self::KEPT_LITERALS as $key) {
            $lightVars[] = '  --color-'.str_replace('_', '-', $key).': transparent;';
        }

        return ":root {\n".implode("\n", $lightVars)."\n}\n\n.dark {\n".implode("\n", $darkVars)."\n}";
    }

    private function cssVal(string $v): string
    {
        if ($v === '' || $v === 'transparent') {
            return 'transparent';
        }

        $parts = explode('/', $v, 2);
        $base = $parts[0];
        $opacity = $parts[1] ?? null;

        $color = in_array($base, ['white', 'black']) ? $base : "var(--color-{$base})";

        if ($opacity !== null && (int) $opacity < 100) {
            return "color-mix(in srgb, {$color} {$opacity}%, transparent)";
        }

        return $color;
    }
}
