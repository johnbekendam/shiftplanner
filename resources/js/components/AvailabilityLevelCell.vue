<script setup>
import { ref, nextTick, onBeforeUnmount } from 'vue'
import Icon from '@/components/ui/Icon.vue'

const props = defineProps({
    // The level the cell shows: not_set, available, not_preferred or unavailable.
    level: { type: String, required: true },
    // Menu entries: [{ value, level, label }]. `level` picks the swatch color.
    options: { type: Array, required: true },
    // The option value that is currently chosen; the menu marks it.
    selected: { type: String, default: null },
    // Draws the changed-day border, the same one the calendar uses.
    changed: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    ariaLabel: { type: String, default: null },
})

const emit = defineEmits(['choose'])

// Two root nodes (button + teleported menu): attributes go on the button.
defineOptions({ inheritAttrs: false })

// Theme-builder badge tokens: Success / Warning / Error.
const LEVEL_CLASS = {
    not_set: 'bg-(--color-badge-standard-bg) text-(--color-badge-standard-text) border-(--color-badge-standard-border)',
    available: 'bg-(--color-badge-success-bg) text-(--color-badge-success-text) border-(--color-badge-success-border)',
    not_preferred: 'bg-(--color-badge-warning-bg) text-(--color-badge-warning-text) border-(--color-badge-warning-border)',
    unavailable: 'bg-(--color-badge-error-bg) text-(--color-badge-error-text) border-(--color-badge-error-border)',
}

const LEVEL_ICON = {
    available: 'check-circle',
    not_preferred: 'exclamation-triangle',
    unavailable: 'x-circle',
}

// ── Selection menu ──────────────────────────────────────────────────────
// The cell button opens a small menu anchored to it; picking an entry is
// what emits. Teleported to body so a table's overflow can't clip it.
const open = ref(false)
const buttonRef = ref(null)
const menuRef = ref(null)
const menuStyle = ref({})

function positionMenu() {
    if (!buttonRef.value) return
    const r = buttonRef.value.getBoundingClientRect()
    menuStyle.value = {
        top: `${r.bottom + 4}px`,
        left: `${r.left}px`,
        minWidth: `${r.width}px`,
    }
}

function toggleMenu() {
    if (props.disabled) return
    if (open.value) {
        closeMenu()
        return
    }
    positionMenu()
    open.value = true
    nextTick(() => menuRef.value?.querySelector('[data-menu-item]')?.focus())
}

function closeMenu() {
    open.value = false
    buttonRef.value?.focus?.()
}

function choose(value) {
    if (value !== props.selected) emit('choose', value)
    closeMenu()
}

function moveMenuFocus(delta) {
    const items = [...(menuRef.value?.querySelectorAll('[data-menu-item]') || [])]
    if (!items.length) return
    const i = items.indexOf(document.activeElement)
    items[(i + delta + items.length) % items.length].focus()
}

function onClickOutside(e) {
    if (!open.value) return
    if (menuRef.value?.contains(e.target) || buttonRef.value?.contains(e.target)) return
    open.value = false
}

function onReposition() {
    if (open.value) positionMenu()
}

document.addEventListener('mousedown', onClickOutside)
document.addEventListener('scroll', onReposition, true)
window.addEventListener('resize', onReposition)

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onClickOutside)
    document.removeEventListener('scroll', onReposition, true)
    window.removeEventListener('resize', onReposition)
})
</script>

<template>
    <button
        ref="buttonRef"
        v-bind="$attrs"
        type="button"
        :disabled="disabled"
        aria-haspopup="menu"
        :aria-expanded="open"
        :aria-label="ariaLabel"
        class="flex h-8 w-full items-center justify-center rounded transition-colors disabled:cursor-not-allowed disabled:opacity-60"
        :class="[LEVEL_CLASS[level], changed ? 'border-2 border-(--color-tab-active-border)' : 'border']"
        @click="toggleMenu"
    >
        <Icon :name="LEVEL_ICON[level]" class="size-4" />
    </button>

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
                v-if="open"
                ref="menuRef"
                role="menu"
                data-testid="availability-menu"
                :style="menuStyle"
                class="fixed z-50 min-w-44 rounded-md py-1 shadow-lg outline outline-1 bg-(--color-dropdown-panel-bg) outline-(--color-dropdown-panel-border)"
                @keydown.escape.stop="closeMenu"
                @keydown.arrow-down.prevent="moveMenuFocus(1)"
                @keydown.arrow-up.prevent="moveMenuFocus(-1)"
            >
                <li v-for="option in options" :key="option.value">
                    <button
                        type="button"
                        data-menu-item
                        :data-testid="`availability-menu-${option.value}`"
                        role="menuitemradio"
                        :aria-checked="selected === option.value"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-(--color-dropdown-option-text) hover:bg-(--color-dropdown-option-hover-bg) hover:text-(--color-dropdown-option-hover-text)"
                        @click="choose(option.value)"
                    >
                        <span
                            class="flex size-5 shrink-0 items-center justify-center rounded border"
                            :class="LEVEL_CLASS[option.level]"
                        >
                            <Icon :name="LEVEL_ICON[option.level]" class="size-3.5" />
                        </span>
                        <span :class="selected === option.value ? 'font-semibold' : 'font-normal'">
                            {{ option.label }}
                        </span>
                    </button>
                </li>
            </ul>
        </Transition>
    </Teleport>
</template>
