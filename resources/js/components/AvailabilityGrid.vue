<script setup>
import { computed, reactive } from 'vue'
import AvailabilityLevelCell from '@/components/AvailabilityLevelCell.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Defined shifts, in display order: { id, name, start_time, end_time, weekdays }.
    // `weekdays` lists the ISO days (1–7) the shift runs on for this employee.
    shifts: { type: Array, default: () => [] },
    // Array of { weekday, shift_id, level } for cells with an explicit level.
    availability: { type: Array, default: () => [] },
    // Manager surface: the empty state also points at the Settings page.
    showAddHint: { type: Boolean, default: false },
    // Read-only: cells render but do not cycle (employee change lock).
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:availability'])

// Monday–Sunday. A column shows only when a shift runs that day, so the
// weekend appears once a workcenter staffs a weekend shift.
const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7]
// The menu only offers explicit states — "not set" is the unclicked default,
// not a choice a person picks back into once they've set something.
const MENU_STATES = ['available', 'not_preferred', 'unavailable']

const key = (weekday, shiftId) => `${weekday}-${shiftId}`

const runs = (shift, weekday) => (shift.weekdays ?? []).includes(weekday)

const visibleWeekdays = computed(() => WEEKDAYS.filter((weekday) => props.shifts.some((shift) => runs(shift, weekday))))

// Local, edit-until-Save state, seeded once from props. The parent forces a
// fresh seed by remounting this component (a :key bump) after its own
// successful save — see the parent page for why an ambient prop watcher
// would be wrong here (a sibling resource's save can refresh these same
// props without touching availability at all).
const cells = reactive({})

for (const shift of props.shifts) {
    for (const weekday of WEEKDAYS) cells[key(weekday, shift.id)] = 'not_set'
}
for (const row of props.availability) {
    const k = key(row.weekday, row.shift_id)
    if (k in cells) cells[k] = row.level
}

const menuOptions = MENU_STATES.map((level) => ({ value: level, level, label: __(`availability.state.${level}`) }))

function choose(weekday, shiftId, level) {
    cells[key(weekday, shiftId)] = level
    emit('update:availability', { weekday, shiftId, level })
}
</script>

<template>
    <p v-if="!shifts.length" class="text-sm text-(--color-text-secondary)">
        {{ showAddHint ? __('availability.grid.no_shifts_manager') : __('availability.grid.no_shifts') }}
    </p>

    <p v-else-if="!visibleWeekdays.length" class="text-sm text-(--color-text-secondary)">
        {{ __('availability.grid.no_running_days') }}
    </p>

    <div v-else class="space-y-3">
        <table class="w-full table-fixed text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-(--color-table-header-text)">
                    <th class="w-32 py-2 text-left font-medium">
                        {{ __('availability.grid.shift_column') }}
                    </th>
                    <th v-for="weekday in visibleWeekdays" :key="weekday" class="py-2 text-center font-medium">
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
                    <td v-for="weekday in visibleWeekdays" :key="weekday" class="p-1">
                        <span
                            v-if="!runs(shift, weekday)"
                            :data-testid="`no-cell-${weekday}-${shift.id}`"
                            class="flex h-8 w-full items-center justify-center text-(--color-text-muted)"
                        >
                            –
                        </span>
                        <AvailabilityLevelCell
                            v-else
                            :data-testid="`cell-${weekday}-${shift.id}`"
                            :level="cells[key(weekday, shift.id)]"
                            :selected="cells[key(weekday, shift.id)]"
                            :options="menuOptions"
                            :disabled="disabled"
                            :aria-label="__('availability.grid.cell', {
                                shift: shift.name,
                                day: __(`availability.weekday.${weekday}`),
                                state: __(`availability.state.${cells[key(weekday, shift.id)]}`),
                            })"
                            @choose="choose(weekday, shift.id, $event)"
                        />
                    </td>
                </tr>
            </tbody>
        </table>

    </div>
</template>
