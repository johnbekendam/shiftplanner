<template>
    <div ref="triggerRef" v-bind="$attrs" class="relative inline-block">
        <!-- Trigger button -->
        <button
            ref="buttonRef"
            type="button"
            :disabled="disabled"
            @click="toggle"
            @keydown.escape.stop="close"
            @keydown.arrow-down.prevent="openAndFocus(0)"
            @keydown.arrow-up.prevent="openAndFocus(normalizedOptions.length - 1)"
            :class="[
                'grid w-full min-w-16 cursor-default grid-cols-1 rounded-lg py-2 pr-2 pl-3 text-left text-sm transition duration-100',
                'bg-[var(--color-input-bg)]',
                'text-[var(--color-input-text)]',
                'outline outline-1 -outline-offset-1 outline-[var(--color-input-border)]',
                'focus:outline-2 focus:-outline-offset-2 focus:outline-[var(--color-input-focus-border)]',
                disabled
                    ? 'cursor-not-allowed bg-[var(--color-input-disabled-bg)] outline-[var(--color-input-disabled-border)]'
                    : '',
            ]"
        >
            <span
                class="col-start-1 row-start-1 truncate pr-6"
                :class="!selectedLabel ? 'text-[var(--color-text-muted)]' : ''"
            >
                {{ selectedLabel || placeholder }}
            </span>
            <!-- Chevrons up-down icon -->
            <Icon
                name="chevron-up-down"
                aria-hidden="true"
                class="col-start-1 row-start-1 size-4 self-center justify-self-end text-zinc-400 dark:text-zinc-500"
            />
        </button>

        <!-- Dropdown list — rendered in body to escape overflow/table clipping -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-100"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-75"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <ul
                    v-if="isOpen"
                    ref="listRef"
                    role="listbox"
                    :style="listStyle"
                    class="fixed z-50 max-h-60 overflow-auto rounded-md py-1 text-sm shadow-lg outline outline-1"
                    :class="['bg-[var(--color-dropdown-panel-bg)]', 'outline-[var(--color-dropdown-panel-border)]']"
                    @keydown.escape.stop="close"
                    @keydown.arrow-down.prevent="moveFocus(1)"
                    @keydown.arrow-up.prevent="moveFocus(-1)"
                    @keydown.enter.prevent="selectFocused"
                    @keydown.tab="close"
                >
                    <li
                        v-for="(opt, i) in normalizedOptions"
                        :key="opt.value"
                        ref="optionRefs"
                        role="option"
                        :aria-selected="String(model) === String(opt.value)"
                        tabindex="-1"
                        @click="select(opt)"
                        @mouseenter="focusedIndex = i"
                        :class="[
                            'relative cursor-default py-2 pr-9 pl-3 select-none',
                            focusedIndex === i
                                ? 'bg-[var(--color-dropdown-option-hover-bg)] text-[var(--color-dropdown-option-hover-text)]'
                                : 'text-[var(--color-dropdown-option-text)]',
                        ]"
                    >
                        <span
                            :class="[
                                'block whitespace-nowrap',
                                String(model) === String(opt.value) ? 'font-semibold' : 'font-normal',
                            ]"
                        >
                            {{ opt.label }}
                        </span>
                        <!-- Checkmark for selected option -->
                        <span
                            v-if="String(model) === String(opt.value)"
                            class="absolute inset-y-0 right-0 flex items-center pr-3"
                            :class="
                                focusedIndex === i
                                    ? 'text-[var(--color-dropdown-option-hover-text)]'
                                    : 'text-[var(--color-dropdown-option-selected-text)]'
                            "
                        >
                            <Icon name="check" aria-hidden="true" class="size-4" />
                        </span>
                    </li>
                </ul>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'
import Icon from '@/components/ui/Icon.vue'

/**
 * SelectMenu — a styled, accessible dropdown replacement for <select>.
 *
 * Props:
 *   options  — Object {value: label} | Array of strings | Array of {value, label}
 *   placeholder — text shown when no value is selected (default '—')
 *
 * Usage:
 *   <SelectMenu v-model="val" :options="{ a: 'Label A', b: 'Label B' }" />
 *   <SelectMenu v-model="val" :options="['opt1', 'opt2']" />
 *   <SelectMenu v-model="val" :options="[{value: 'a', label: 'Label A'}]" />
 */

const props = defineProps({
    options: { type: [Object, Array], required: true },
    placeholder: { type: String, default: '—' },
    disabled: { type: Boolean, default: false },
})

defineOptions({ inheritAttrs: false })

const model = defineModel()

// ── Normalize options ────────────────────────────────────────────────────────

const normalizedOptions = computed(() => {
    if (Array.isArray(props.options)) {
        return props.options.map((o) =>
            typeof o === 'string' || typeof o === 'number' ? { value: o, label: String(o) } : o,
        )
    }
    return Object.entries(props.options).map(([value, label]) => ({ value, label }))
})

const selectedLabel = computed(() => {
    const found = normalizedOptions.value.find((o) => String(o.value) === String(model.value))
    return found ? found.label : ''
})

// ── Open / close state ───────────────────────────────────────────────────────

const isOpen = ref(false)
const triggerRef = ref(null)
const buttonRef = ref(null)
const listRef = ref(null)
const optionRefs = ref([])
const focusedIndex = ref(-1)
const listStyle = ref({})

function computePosition() {
    if (!triggerRef.value) return
    const rect = triggerRef.value.getBoundingClientRect()
    listStyle.value = {
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        minWidth: `${rect.width}px`,
        width: 'max-content',
    }
}

async function open() {
    computePosition()
    isOpen.value = true
    // Focus the currently selected option
    const selectedIdx = normalizedOptions.value.findIndex((o) => String(o.value) === String(model.value))
    focusedIndex.value = selectedIdx >= 0 ? selectedIdx : 0
    await nextTick()
    optionRefs.value[focusedIndex.value]?.focus()
}

function close() {
    isOpen.value = false
    focusedIndex.value = -1
    buttonRef.value?.focus()
}

function toggle() {
    if (props.disabled) return
    isOpen.value ? close() : open()
}

function select(opt) {
    model.value = opt.value
    close()
}

function selectFocused() {
    if (focusedIndex.value >= 0 && focusedIndex.value < normalizedOptions.value.length) {
        select(normalizedOptions.value[focusedIndex.value])
    }
}

async function openAndFocus(index) {
    if (!isOpen.value) await open()
    await nextTick()
    focusedIndex.value = index
    optionRefs.value[index]?.focus()
}

function moveFocus(delta) {
    const len = normalizedOptions.value.length
    if (!len) return
    focusedIndex.value = (focusedIndex.value + delta + len) % len
    optionRefs.value[focusedIndex.value]?.focus()
}

// ── Click outside ────────────────────────────────────────────────────────────

function onClickOutside(e) {
    if (!isOpen.value) return
    if (triggerRef.value?.contains(e.target)) return
    if (listRef.value?.contains(e.target)) return
    close()
}

function onScroll() {
    if (isOpen.value) computePosition()
}

document.addEventListener('mousedown', onClickOutside)
document.addEventListener('scroll', onScroll, true)

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onClickOutside)
    document.removeEventListener('scroll', onScroll, true)
})
</script>
