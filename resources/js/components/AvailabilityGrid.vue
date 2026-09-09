<script setup>
import { reactive, watch } from 'vue'
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
// available -> not_preferred -> unavailable -> available
const CYCLE = { available: 'not_preferred', not_preferred: 'unavailable', unavailable: 'available' }

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

function cycle(weekday, shiftId) {
    if (props.disabled) return
    const next = CYCLE[cells[key(weekday, shiftId)]]
    cells[key(weekday, shiftId)] = next
    router.put(`${props.endpoint}/${weekday}/${shiftId}`, { level: next }, {
        preserveScroll: true,
        preserveState: true,
    })
}
</script>

<template>
    <p v-if="!shifts.length" class="text-sm text-(--color-text-secondary)">
        {{ showAddHint ? __('availability.grid.no_shifts_manager') : __('availability.grid.no_shifts') }}
    </p>

    <div v-else class="space-y-3">
        <table class="w-full table-fixed text-sm">
            <thead>
                <tr class="text-(--color-table-header-text)">
                    <th class="w-32 py-2" />
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
                            :aria-label="__('availability.grid.cell', {
                                shift: shift.name,
                                day: __(`availability.weekday.${weekday}`),
                                state: __(`availability.state.${cells[`${weekday}-${shift.id}`]}`),
                            })"
                            class="flex h-8 w-full items-center justify-center rounded border transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                            :class="LEVEL_CLASS[cells[`${weekday}-${shift.id}`]]"
                            @click="cycle(weekday, shift.id)"
                        >
                            <Icon :name="LEVEL_ICON[cells[`${weekday}-${shift.id}`]]" class="size-4" />
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <ul class="flex flex-wrap justify-end gap-x-4 gap-y-1 text-xs text-(--color-text-secondary)">
            <li v-for="level in STATES" :key="level" class="flex items-center gap-1.5">
                <span
                    class="flex size-5 items-center justify-center rounded border"
                    :class="LEVEL_CLASS[level]"
                >
                    <Icon :name="LEVEL_ICON[level]" class="size-3.5" />
                </span>
                {{ __(`availability.state.${level}`) }}
            </li>
        </ul>
    </div>
</template>
