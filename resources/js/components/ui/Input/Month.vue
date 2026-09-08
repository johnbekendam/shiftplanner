<template>
    <div class="relative flex items-center" ref="containerRef">
        <input
            ref="inputRef"
            v-bind="$attrs"
            type="text"
            readonly
            :value="displayValue"
            :placeholder="placeholder"
            :class="[
                'block w-full rounded-lg px-3 py-2 pr-9 text-sm transition duration-100',
                isDisabled ? 'cursor-not-allowed' : 'cursor-pointer',
                'bg-[var(--color-input-bg)]',
                'text-[var(--color-input-text)]',
                'outline outline-1 -outline-offset-1 outline-[var(--color-input-border)]',
                'focus:outline-2 focus:-outline-offset-2 focus:outline-[var(--color-input-focus-border)]',
                'disabled:bg-transparent',
                'disabled:text-[var(--color-input-disabled-text)]',
                'disabled:outline-[var(--color-input-disabled-border)]',
            ]"
            @click="openPicker"
        />
        <button
            type="button"
            tabindex="-1"
            :disabled="isDisabled"
            @click="openPicker"
            class="absolute right-2 text-[var(--color-input-text)] opacity-50 transition-opacity hover:opacity-100 disabled:cursor-not-allowed"
        >
            <Icon name="calendar" class="h-4 w-4" />
        </button>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="dropdownRef"
                :style="dropdownStyle"
                class="fixed z-50 min-w-[200px] rounded-lg bg-[var(--color-dropdown-panel-bg)] p-3 shadow-lg outline outline-1 outline-[var(--color-dropdown-panel-border)]"
            >
                <div class="mb-2 flex items-center justify-between">
                    <button
                        type="button"
                        @click="pickerYear--"
                        class="rounded p-1 text-[var(--color-dropdown-option-text)] transition-colors hover:bg-[var(--color-dropdown-option-hover-bg)] hover:text-[var(--color-dropdown-option-hover-text)]"
                    >
                        <Icon name="chevron-left" class="h-4 w-4" />
                    </button>
                    <span class="text-sm font-semibold text-[var(--color-dropdown-option-text)]">{{ pickerYear }}</span>
                    <button
                        type="button"
                        @click="pickerYear++"
                        class="rounded p-1 text-[var(--color-dropdown-option-text)] transition-colors hover:bg-[var(--color-dropdown-option-hover-bg)] hover:text-[var(--color-dropdown-option-hover-text)]"
                    >
                        <Icon name="chevron-right" class="h-4 w-4" />
                    </button>
                </div>

                <div class="grid grid-cols-3 gap-1">
                    <button
                        v-for="(name, idx) in monthNames"
                        :key="idx"
                        type="button"
                        @click="selectMonth(idx)"
                        :class="[
                            'rounded-lg px-1 py-1.5 text-center text-sm transition-colors',
                            isSelected(idx)
                                ? 'bg-[var(--color-dropdown-option-hover-bg)] font-medium text-[var(--color-dropdown-option-hover-text)]'
                                : 'text-[var(--color-dropdown-option-text)] hover:bg-[var(--color-dropdown-option-hover-bg)] hover:text-[var(--color-dropdown-option-hover-text)]',
                        ]"
                    >
                        {{ name }}
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch, useAttrs, onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Icon from '@/components/ui/Icon.vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
    format: { type: String, default: null }, // 'my' | 'ym' — overrides user setting
})

const model = defineModel({ type: String, default: '' })
const attrs = useAttrs()
const page = usePage()

const containerRef = ref(null)
const inputRef = ref(null)
const dropdownRef = ref(null)
const isOpen = ref(false)
const pickerYear = ref(new Date().getFullYear())
const dropdownStyle = ref({})

const locale = computed(() => page.props.locale ?? 'en')
const monthFormat = computed(() => props.format ?? page.props.auth?.settings?.month_format ?? 'my')
const isDisabled = computed(() => attrs.disabled === true || attrs.disabled === '')

const monthNames = computed(() => {
    const fmt = new Intl.DateTimeFormat(locale.value, { month: 'short' })
    return Array.from({ length: 12 }, (_, i) => fmt.format(new Date(2000, i, 1)))
})

const placeholder = computed(() =>
    monthFormat.value === 'ym' ? `YYYY ${monthNames.value[0]}` : `${monthNames.value[0]} YYYY`,
)

const selectedYear = computed(() => (model.value ? Number(model.value.split('-')[0]) : null))
const selectedMonth = computed(() => (model.value ? Number(model.value.split('-')[1]) - 1 : null))

const displayValue = computed(() => {
    if (!model.value) return ''
    const [y, m] = model.value.split('-').map(Number)
    if (!y || !m) return ''
    const name = monthNames.value[m - 1]
    return monthFormat.value === 'ym' ? `${y} ${name}` : `${name} ${y}`
})

function isSelected(idx) {
    return pickerYear.value === selectedYear.value && idx === selectedMonth.value
}

function selectMonth(idx) {
    const m = String(idx + 1).padStart(2, '0')
    model.value = `${pickerYear.value}-${m}`
    isOpen.value = false
}

function openPicker() {
    if (isDisabled.value) return
    if (selectedYear.value) pickerYear.value = selectedYear.value
    if (!isOpen.value && containerRef.value) {
        const rect = containerRef.value.getBoundingClientRect()
        dropdownStyle.value = {
            top: `${rect.bottom + window.scrollY + 4}px`,
            left: `${rect.left + window.scrollX}px`,
        }
    }
    isOpen.value = !isOpen.value
}

function onClickOutside(e) {
    if (
        containerRef.value &&
        !containerRef.value.contains(e.target) &&
        dropdownRef.value &&
        !dropdownRef.value.contains(e.target)
    ) {
        isOpen.value = false
    }
}

watch(
    () => model.value,
    (val) => {
        if (val) {
            const y = Number(val.split('-')[0])
            if (y) pickerYear.value = y
        }
    },
)

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>
