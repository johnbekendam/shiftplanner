<script setup>
import { reactive, ref, watch, nextTick, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import Icon from '@/components/ui/Icon.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Defined shifts, in display order: { id, name, start_time, end_time }.
    shifts: { type: Array, default: () => [] },
    // Array of { weekday, shift_id, level } for the non-available cells.
    availability: { type: Array, default: () => [] },
    // Base URL, e.g. /employees/7/availability.
    endpoint: { type: String, required: true },
    // Manager surface: the empty state also points at the Settings page.
    showAddHint: { type: Boolean, default: false },
    // Read-only: cells render but do not cycle (employee change lock).
    disabled: { type: Boolean, default: false },
})

// Monday–Friday. The team runs no weekend shifts; a weekend need is a
// configurable question instead.
const WEEKDAYS = [1, 2, 3, 4, 5]
const STATES = ['available', 'not_preferred', 'unavailable']

const key = (weekday, shiftId) => `${weekday}-${shiftId}`

// Local state for instant feedback; the server write follows.
const cells = reactive({})

function sync() {
    for (const k of Object.keys(cells)) delete cells[k]
    for (const shift of props.shifts) {
        for (const weekday of WEEKDAYS) cells[key(weekday, shift.id)] = 'available'
    }
    for (const row of props.availability) {
        const k = key(row.weekday, row.shift_id)
        if (k in cells) cells[k] = row.level
    }
}
sync()
watch(() => [props.shifts, props.availability], sync, { deep: true })

// Theme-builder badge tokens: Success / Warning / Error.
const LEVEL_CLASS = {
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
// The cell button opens a small menu anchored to it; picking a state is
// what writes. Teleported to body so the table's overflow can't clip it.
const menu = ref(null) // { weekday, shiftId } while open
const menuRef = ref(null)
const menuStyle = ref({})
let anchor = null

function positionMenu() {
    if (!anchor) return
    const r = anchor.getBoundingClientRect()
    menuStyle.value = {
        top: `${r.bottom + 4}px`,
        left: `${r.left}px`,
        minWidth: `${r.width}px`,
    }
}

function openMenu(weekday, shiftId, event) {
    if (props.disabled) return
    if (menu.value && menu.value.weekday === weekday && menu.value.shiftId === shiftId) {
        closeMenu()
        return
    }
    anchor = event.currentTarget
    positionMenu()
    menu.value = { weekday, shiftId }
    nextTick(() => menuRef.value?.querySelector('[data-menu-item]')?.focus())
}

function closeMenu() {
    menu.value = null
    anchor?.focus?.()
    anchor = null
}

function choose(level) {
    if (!menu.value) return
    const { weekday, shiftId } = menu.value
    const k = key(weekday, shiftId)
    if (cells[k] !== level) {
        cells[k] = level
        router.put(`${props.endpoint}/${weekday}/${shiftId}`, { level }, {
            preserveScroll: true,
            preserveState: true,
        })
    }
    closeMenu()
}

function isOpenFor(weekday, shiftId) {
    return !!menu.value && menu.value.weekday === weekday && menu.value.shiftId === shiftId
}

function moveMenuFocus(delta) {
    const items = [...(menuRef.value?.querySelectorAll('[data-menu-item]') || [])]
    if (!items.length) return
    const i = items.indexOf(document.activeElement)
    items[(i + delta + items.length) % items.length].focus()
}

function onClickOutside(e) {
    if (!menu.value) return
    if (menuRef.value?.contains(e.target) || anchor?.contains(e.target)) return
    menu.value = null
    anchor = null
}

function onReposition() {
    if (menu.value) positionMenu()
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
    <p v-if="!shifts.length" class="text-sm text-(--color-text-secondary)">
        {{ showAddHint ? __('availability.grid.no_shifts_manager') : __('availability.grid.no_shifts') }}
    </p>

    <div v-else class="space-y-3">
        <table class="w-full table-fixed text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-(--color-table-header-text)">
                    <th class="w-32 py-2 text-left font-medium">
                        {{ __('availability.grid.shift_column') }}
                    </th>
                    <th v-for="weekday in WEEKDAYS" :key="weekday" class="py-2 text-center font-medium">
                        {{ __(`availability.weekday.${weekday}`) }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="shift in shifts" :key="shift.id">
                    <th class="py-1 pr-3 text-left font-medium text-(--color-table-row-text)">
                        <span class="block">{{ shift.name }}</span>
                        <span class="block text-xs font-normal text-(--color-text-secondary)">
                            {{ shift.start_time }} – {{ shift.end_time }}
                        </span>
                    </th>
                    <td v-for="weekday in WEEKDAYS" :key="weekday" class="p-1">
                        <button
                            type="button"
                            :disabled="disabled"
                            :data-testid="`cell-${weekday}-${shift.id}`"
                            aria-haspopup="menu"
                            :aria-expanded="isOpenFor(weekday, shift.id)"
                            :aria-label="__('availability.grid.cell', {
                                shift: shift.name,
                                day: __(`availability.weekday.${weekday}`),
                                state: __(`availability.state.${cells[`${weekday}-${shift.id}`]}`),
                            })"
                            class="flex h-8 w-full items-center justify-center rounded border transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                            :class="LEVEL_CLASS[cells[`${weekday}-${shift.id}`]]"
                            @click="openMenu(weekday, shift.id, $event)"
                        >
                            <Icon :name="LEVEL_ICON[cells[`${weekday}-${shift.id}`]]" class="size-4" />
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

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
                    v-if="menu"
                    ref="menuRef"
                    role="menu"
                    data-testid="availability-menu"
                    :style="menuStyle"
                    class="fixed z-50 min-w-44 rounded-md py-1 shadow-lg outline outline-1 bg-(--color-dropdown-panel-bg) outline-(--color-dropdown-panel-border)"
                    @keydown.escape.stop="closeMenu"
                    @keydown.arrow-down.prevent="moveMenuFocus(1)"
                    @keydown.arrow-up.prevent="moveMenuFocus(-1)"
                >
                    <li v-for="level in STATES" :key="level">
                        <button
                            type="button"
                            data-menu-item
                            :data-testid="`availability-menu-${level}`"
                            role="menuitemradio"
                            :aria-checked="cells[key(menu.weekday, menu.shiftId)] === level"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-(--color-dropdown-option-text) hover:bg-(--color-dropdown-option-hover-bg) hover:text-(--color-dropdown-option-hover-text)"
                            @click="choose(level)"
                        >
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded border"
                                :class="LEVEL_CLASS[level]"
                            >
                                <Icon :name="LEVEL_ICON[level]" class="size-3.5" />
                            </span>
                            <span :class="cells[key(menu.weekday, menu.shiftId)] === level ? 'font-semibold' : 'font-normal'">
                                {{ __(`availability.state.${level}`) }}
                            </span>
                        </button>
                    </li>
                </ul>
            </Transition>
        </Teleport>
    </div>
</template>
