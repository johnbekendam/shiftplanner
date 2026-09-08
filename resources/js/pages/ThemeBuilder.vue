<template>
    <AppLayout>
        <Head title="Theme Builder" />

        <Card class="max-w-5xl mx-auto" data-testid="theme-builder-card">
            <!-- Header: the category tabs. One tab bar drives the example and the
                 picker set below it. -->
            <template #header>
                <div class="flex items-center gap-4 pr-4">
                    <div
                        data-testid="preview-category-tabs"
                        class="flex gap-0 overflow-x-auto"
                        style="border-color: var(--color-tab-separator)"
                    >
                        <button
                            v-for="cat in previewCategories"
                            :key="cat.key"
                            type="button"
                            role="tab"
                            :aria-selected="activeCategory === cat.key"
                            class="shrink-0 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors"
                            :class="
                                activeCategory === cat.key
                                    ? 'border-[var(--color-tab-active-border)] text-[var(--color-tab-active-text)]'
                                    : 'border-transparent text-[var(--color-tab-text)] hover:border-[var(--color-tab-hover-border)] hover:text-[var(--color-tab-hover-text)]'
                            "
                            @click="activeCategory = cat.key"
                        >
                            {{ __(cat.labelKey) }}
                        </button>
                    </div>

                    <div
                        data-testid="branding-row"
                        class="ml-auto flex shrink-0 items-center gap-2 text-sm text-[var(--color-card-header-text)]"
                    >
                        <span class="font-medium">Branding</span>
                        <BrandFamilyPicker v-model="brandFamilyState" />
                    </div>
                </div>
            </template>

            <!-- Body: the example page, then the settings card below it. -->
            <div class="space-y-4 p-4">
                <PreviewFrame
                    :style-vars="previewVarsStyle"
                    :logo-url="page.props.logoUrl"
                    :crumbs="frameCrumbs"
                >
                    <template #header-actions>
                        <button
                            type="button"
                            data-testid="dark-toggle"
                            class="rounded p-1 transition-opacity hover:opacity-80"
                            :aria-label="isDark ? __('nav.switch_to_light') : __('nav.switch_to_dark')"
                            @click="toggleDarkMode"
                        >
                            <Icon v-if="isDark" name="sun" class="size-4" />
                            <Icon v-else name="moon" class="size-4" />
                        </button>
                    </template>

                    <template v-if="activeCategory === 'menu'" #sidebar>
                        <MenuSidebarPreview />
                    </template>

                    <component :is="compositionFor(activeCategory)" />
                    <template v-if="overlayFor(activeCategory)" #overlay>
                        <component :is="overlayFor(activeCategory)" />
                    </template>
                </PreviewFrame>

                <!-- Colour settings for the active category. -->
                <Card data-testid="settings-panel">
                    <template #header>
                        <h3 class="px-4 py-2.5 text-xs font-semibold tracking-wider uppercase">
                            {{ __(activeCategoryMeta.labelKey) }}
                        </h3>
                    </template>

                    <!-- Chrome: colours table (left) and separators (right); the
                         rest: a table (menu) or a plain list. -->
                    <div v-if="activeCategory === 'chrome'" class="grid gap-x-8 gap-y-4 md:grid-cols-2">
                        <ColorTokenTable
                            :columns="['Background', 'Text']"
                            :rows="chromeRows"
                            :colors="colorsState"
                            :mode="activeMode"
                        />

                        <div class="grid grid-cols-[1fr_auto] content-start items-center gap-x-3 px-4">
                            <span></span>
                            <span class="py-2 text-left text-sm font-semibold text-[var(--color-card-body-text)]">
                                Separator
                            </span>
                            <template v-for="s in chromeSeparators" :key="s.key">
                                <label class="flex items-center gap-2 border-t border-[var(--color-card-body-border)] py-2.5 text-sm text-[var(--color-card-body-text)]">
                                    <CheckboxInput v-model="colorsState[s.key].enabled" />
                                    {{ s.label }}
                                </label>
                                <div class="flex items-center border-t border-[var(--color-card-body-border)] py-2.5">
                                    <ColorTokenPicker v-model="colorsState[s.key][activeMode]" />
                                </div>
                            </template>
                        </div>
                    </div>

                    <div v-else-if="activeCategory === 'menu'" class="grid gap-x-8 gap-y-4 md:grid-cols-2">
                        <ColorTokenTable
                            :columns="['Background', 'Text']"
                            :rows="menuRowsLeft"
                            :colors="colorsState"
                            :mode="activeMode"
                        />
                        <ColorTokenTable
                            :columns="['Background', 'Text']"
                            :rows="menuRowsRight"
                            :colors="colorsState"
                            :mode="activeMode"
                        />
                    </div>

                    <div v-else-if="activeCategory === 'text'" class="grid gap-x-8 gap-y-4 md:grid-cols-2">
                        <ColorTokenTable
                            :columns="['Colour']"
                            :rows="textRowsLeft"
                            :colors="colorsState"
                            :mode="activeMode"
                        />
                        <ColorTokenTable
                            :columns="['Colour']"
                            :rows="textRowsRight"
                            :colors="colorsState"
                            :mode="activeMode"
                        />
                    </div>

                    <ColorTokenTable
                        v-else-if="activeCategory === 'buttons'"
                        :columns="['Background', 'Text', 'Border']"
                        :rows="buttonRows"
                        :colors="colorsState"
                        :mode="activeMode"
                    />

                    <ColorTokenTable
                        v-else-if="activeCategory === 'tabs'"
                        label="Tabs"
                        :columns="['Background', 'Text', 'Border']"
                        :rows="tabsPageRows"
                        :colors="colorsState"
                        :mode="activeMode"
                    />

                    <div v-else-if="activeCategory === 'table'" class="space-y-6">
                        <ColorTokenTable
                            label="Table"
                            :columns="['Background', 'Text', 'Separator']"
                            :rows="tablePageRows"
                            :colors="colorsState"
                            :mode="activeMode"
                        />

                        <div class="grid grid-cols-1 gap-x-3 px-4 sm:grid-cols-2">
                            <template v-for="c in paginationAccentColors" :key="c.key">
                                <p
                                    class="flex items-center border-b border-[var(--color-card-body-border)] py-2.5 text-sm text-[var(--color-card-body-text)]"
                                    :title="`--color-${c.key.replaceAll('_', '-')}: ${colorsState[c.key]?.[activeMode]}`"
                                >
                                    {{ c.label }}
                                </p>
                                <div class="flex items-center border-b border-[var(--color-card-body-border)] py-2.5">
                                    <ColorTokenPicker v-model="colorsState[c.key][activeMode]" />
                                </div>
                            </template>
                        </div>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-x-3 px-4 sm:grid-cols-2">
                        <template v-for="c in activeCategoryData.colors" :key="c.key">
                            <p
                                class="flex items-center border-b border-[var(--color-card-body-border)] py-2.5 text-sm text-[var(--color-card-body-text)]"
                                :title="`--color-${c.key.replaceAll('_', '-')}: ${colorsState[c.key]?.[activeMode]}`"
                            >
                                {{ c.label }}
                            </p>
                            <div class="flex items-center border-b border-[var(--color-card-body-border)] py-2.5">
                                <ColorTokenPicker v-model="colorsState[c.key][activeMode]" />
                            </div>
                        </template>
                    </div>
                </Card>
            </div>

            <!-- Footer: the theme actions. -->
            <template #footer>
                <div data-testid="card-footer" class="flex items-center gap-3 px-4 py-3">
                    <ButtonDanger @click="resetDefaults">
                        {{ __('theme.reset') }}
                    </ButtonDanger>

                    <Transition
                        enter-active-class="transition-opacity"
                        enter-from-class="opacity-0"
                        leave-active-class="transition-opacity"
                        leave-to-class="opacity-0"
                    >
                        <span v-if="themeSaved" class="text-xs font-medium text-green-600 dark:text-green-400">
                            {{ __('theme.saved') }}
                        </span>
                    </Transition>

                    <ButtonSecondary class="ml-auto" @click="cancelChanges">
                        {{ __('theme.cancel') }}
                    </ButtonSecondary>
                    <ButtonPrimary @click="saveTheme">
                        {{ __('theme.save') }}
                    </ButtonPrimary>
                </div>
            </template>
        </Card>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import axios from 'axios'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { useI18n } from '@/composables/useI18n'
import { useDarkMode } from '@/composables/useDarkMode'
import Icon from '@/components/ui/Icon.vue'
import ColorTokenPicker from '@/components/ui/ColorTokenPicker.vue'
import { CheckboxInput } from '@/components/ui/Input'
import Card from '@/components/ui/Card.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import { cssVal } from '@/utils/colorToken.js'
import { PREVIEW_CATEGORIES, DEFAULT_PREVIEW_CATEGORY } from '@/pages/themeBuilder/previewCategories'
import PreviewFrame from '@/pages/themeBuilder/PreviewFrame.vue'
import MenuSidebarPreview from '@/pages/themeBuilder/MenuSidebarPreview.vue'
import ColorTokenTable from '@/pages/themeBuilder/ColorTokenTable.vue'
import BrandFamilyPicker from '@/components/ui/BrandFamilyPicker.vue'
import { compositionFor, overlayFor } from '@/pages/themeBuilder/compositions/registry'

const props = defineProps({
    categories:         { type: Array, required: true },
    colors:             { type: Object, required: true },
    defaultColors:      { type: Object, required: true },
    brandFamily:        { type: String, required: true },
    defaultBrandFamily: { type: String, required: true },
})

const page = usePage()

const __ = useI18n()
const { isDark, toggle: toggleDarkMode } = useDarkMode()

// ── Preview category tabs (drive the preview frame) ────────────────────────
const previewCategories = PREVIEW_CATEGORIES
const activeCategory = ref(DEFAULT_PREVIEW_CATEGORY)
const activeCategoryMeta = computed(
    () => PREVIEW_CATEGORIES.find((c) => c.key === activeCategory.value) ?? PREVIEW_CATEGORIES[0],
)
// Frame breadcrumb: always "Home / <active category>".
const frameCrumbs = computed(() => [
    __('theme.breadcrumb.home'),
    __(activeCategoryMeta.value.labelKey),
])

// The active category's picker set.
const activeCategoryData = computed(
    () => props.categories.find((c) => c.key === activeCategory.value) ?? props.categories[0],
)

// Chrome tab: a Header / Sidebar / Page table, then two toggleable separators.
// Logo merges into Header — there is no separate logo row; --color-logo-*
// is derived from --color-header-* (see app.css and previewVarsStyle below).
// "Page" is the content body (content_bg/content_text). There is no outer
// background token — the layout covers the whole screen, so --color-page-bg
// is fixed transparent.
const chromeRows = [
    { label: 'Header', keys: ['header_bg', 'header_text'] },
    { label: 'Sidebar', keys: ['sidebar_bg', 'sidebar_text'] },
    { label: 'Page', keys: ['content_bg', 'content_text'] },
]
const chromeSeparators = [
    { key: 'separator_horizontal', label: 'Horizontal (header)' },
    { key: 'separator_vertical', label: 'Vertical (sidebar)' },
]

// Text tab: two single-column tables — the text types left, the link states right.
const textRowsLeft = [
    { label: 'Heading', keys: ['text_heading'] },
    { label: 'Normal', keys: ['text_primary'] },
    { label: 'Muted', keys: ['text_muted'] },
]
const textRowsRight = [
    { label: 'Link normal', keys: ['text_link'] },
    { label: 'Link hover', keys: ['text_link_hover'] },
]

// Buttons tab: variant x state rows, Background / Text / Border columns.
const buttonRows = ['primary', 'secondary', 'danger'].flatMap((v) => {
    const cap = v[0].toUpperCase() + v.slice(1)
    return [
        { label: cap, keys: [`btn_${v}_bg`, `btn_${v}_text`, `btn_${v}_border`] },
        { label: `${cap} (hover)`, keys: [`btn_${v}_hover_bg`, `btn_${v}_hover_text`, `btn_${v}_hover_border`] },
        { label: `${cap} (disabled)`, keys: [`btn_${v}_disabled_bg`, `btn_${v}_disabled_text`, `btn_${v}_disabled_border`] },
    ]
})

// Tabs tab: the tab bar as one pickers table (see doc/features/data-tab/spec.md).
// `tab_bg` is the whole strip; `tab_inactive_bg` is one resting tab. The blank
// cells are the transparent `tab_border` literal and the absent bar text.
const tabsPageRows = [
    { label: 'Bar', keys: ['tab_bg', null, 'tab_separator'] },
    { label: 'Normal', keys: ['tab_inactive_bg', 'tab_text', null] },
    { label: 'Hover', keys: ['tab_hover_bg', 'tab_hover_text', 'tab_hover_border'] },
    { label: 'Active', keys: ['tab_active_bg', 'tab_active_text', 'tab_active_border'] },
]

// Table tab: the data grid as one pickers table, then the pagination-accent
// tokens as plain rows.
const tablePageRows = [
    { label: 'Header', keys: ['table_header_bg', 'table_header_text', 'table_header_separator'] },
    { label: 'Row', keys: ['table_row_bg', 'table_row_text', 'table_row_separator'] },
    { label: 'Row hover', keys: ['table_row_hover_bg', 'table_row_hover_text', null] },
    { label: 'Row selected', keys: ['table_row_selected_bg', 'table_row_selected_text', null] },
]
const paginationAccentKeys = ['pagination_active_bg', 'pagination_active_text']
const paginationAccentColors = computed(() =>
    activeCategoryData.value.colors.filter((c) => paginationAccentKeys.includes(c.key)),
)

// Menu tab: one state per row, Background / Text columns, split over two tables.
const menuRowsLeft = [
    { label: 'Disabled', keys: ['menu_item_disabled_bg', 'menu_item_disabled_text'] },
    { label: 'Normal', keys: ['menu_item_bg', 'menu_item_text'] },
]
const menuRowsRight = [
    { label: 'Hover', keys: ['menu_item_hover_bg', 'menu_item_hover_text'] },
    { label: 'Selected', keys: ['menu_item_selected_bg', 'menu_item_selected_text'] },
]

// ── Helpers ────────────────────────────────────────────────────────────────
function cloneColors(source) {
    const result = {}
    for (const [key, v] of Object.entries(source)) result[key] = { ...v }
    return result
}

function dashed(key) {
    return key.replaceAll('_', '-')
}

// ── Reactive state ─────────────────────────────────────────────────────────
function isDarkMode() {
    return document.documentElement.classList.contains('dark')
}
const activeMode = ref(isDarkMode() ? 'dark' : 'light')
let darkObserver = null

const colorsState = ref(cloneColors(props.colors))
const brandFamilyState = ref(props.brandFamily)
const themeSaved = ref(false)

let savedColorsSnapshot = JSON.parse(JSON.stringify(props.colors))
let savedBrandFamily = props.brandFamily
const defaultColorsRef = JSON.parse(JSON.stringify(props.defaultColors))

const SHADES = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950']

// Keep the global --color-brand-* ramp in sync with the picker. Brand swatches
// live outside the preview frame too — the colour-picker matrix teleports to
// <body>, and every trigger swatch sits in the settings panel — so the live
// family has to be published on :root, not just on the preview element.
watch(
    brandFamilyState,
    (family) => {
        for (const shade of SHADES) {
            document.documentElement.style.setProperty(
                `--color-brand-${shade}`,
                `var(--color-${family}-${shade})`,
            )
        }
    },
    { immediate: true },
)

// ── Computed ───────────────────────────────────────────────────────────────

// Every flat token is re-declared inline so a preview edit takes effect without
// a save. The still-derived vars (cards, pagination, primary button, the two
// "on surface" text vars) point at flat tokens declared on the same element, the
// same way resources/css/app.css defines them.
const previewVarsStyle = computed(() => {
    const m = activeMode.value

    // The branding ramp — a `brand-600` token value resolves through these.
    const brandRamp = SHADES.map(
        (s) => `--color-brand-${s}: var(--color-${brandFamilyState.value}-${s})`,
    ).join('; ')

    const tokenVars = Object.entries(colorsState.value)
        .map(([key, v]) => {
            // A separator with enabled=false renders transparent.
            const value = 'enabled' in v && !v.enabled ? 'transparent' : cssVal(v[m])
            return `--color-${dashed(key)}: ${value}`
        })
        .join('; ')

    const keptLiterals = [
        'menu-item-border', 'menu-item-hover-border', 'menu-item-selected-border',
        'menu-item-disabled-border', 'tab-border', 'page-bg',
    ].map((k) => `--color-${k}: transparent`).join('; ')

    const derivedVars = [
        '--color-logo-bg: var(--color-header-bg)',
        '--color-logo-text: var(--color-header-text)',
        '--color-text-secondary: var(--color-text-muted)',
        '--color-text-body: var(--color-text-primary)',
        '--color-surface-text: var(--color-text-primary)',
        '--color-surface-secondary-text: var(--color-text-secondary)',
        '--color-card-body-bg: var(--color-surface-bg)',
        '--color-card-body-text: var(--color-text-primary)',
        '--color-card-body-border: var(--color-surface-border)',
        '--color-card-header-bg: var(--color-surface-secondary-bg)',
        '--color-card-header-text: var(--color-text-secondary)',
        '--color-card-header-border: var(--color-surface-border)',
        '--color-card-footer-bg: var(--color-surface-secondary-bg)',
        '--color-card-footer-text: var(--color-text-secondary)',
        '--color-card-footer-border: var(--color-surface-border)',
        '--color-card-border: var(--color-surface-border)',
        '--color-pagination-bg: var(--color-surface-bg)',
        '--color-pagination-text: var(--color-text-primary)',
        '--color-pagination-border: var(--color-surface-border)',
        '--color-pagination-muted-text: var(--color-text-secondary)',
        '--color-pagination-hover-bg: var(--color-surface-secondary-bg)',
        '--color-pagination-hover-text: var(--color-text-primary)',
        '--color-pagination-hover-border: var(--color-surface-border)',
        // pagination_active_bg/text are real tokens (in tokenVars above); only
        // the border still derives, from the bg token.
        '--color-pagination-active-border: var(--color-pagination-active-bg)',
    ].join('; ')

    return `${brandRamp}; ${tokenVars}; ${keptLiterals}; ${derivedVars}`
})

// ── Methods ────────────────────────────────────────────────────────────────
function cancelChanges() {
    colorsState.value = cloneColors(savedColorsSnapshot)
    brandFamilyState.value = savedBrandFamily
}

async function saveTheme() {
    await axios.post('/theme-builder/save', {
        colors: colorsState.value,
        brand_family: brandFamilyState.value,
    })
    savedColorsSnapshot = JSON.parse(JSON.stringify(colorsState.value))
    savedBrandFamily = brandFamilyState.value
    themeSaved.value = true
    setTimeout(() => {
        themeSaved.value = false
    }, 2000)
}

async function resetDefaults() {
    await axios.delete('/theme-builder/save')
    colorsState.value = cloneColors(defaultColorsRef)
    savedColorsSnapshot = JSON.parse(JSON.stringify(defaultColorsRef))
    brandFamilyState.value = props.defaultBrandFamily
    savedBrandFamily = props.defaultBrandFamily
    themeSaved.value = true
    setTimeout(() => {
        themeSaved.value = false
    }, 2000)
}

onMounted(() => {
    darkObserver = new MutationObserver(() => {
        activeMode.value = isDarkMode() ? 'dark' : 'light'
    })
    darkObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
})

onUnmounted(() => {
    darkObserver?.disconnect()
    // Hand the ramp back to the saved <style id="theme-tokens"> block.
    for (const shade of SHADES) {
        document.documentElement.style.removeProperty(`--color-brand-${shade}`)
    }
})
</script>

<style>
[data-theme-preview] input::placeholder {
    color: var(--color-input-placeholder);
}
</style>
