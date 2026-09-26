<script setup>
import { computed } from 'vue'
import AvailabilityLevelCell from '@/components/AvailabilityLevelCell.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import { CheckboxInput } from '@/components/ui/Input'
import { isoWeekday } from '@/utils/availabilityCalendar'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // A dayAvailability() result: { date, holiday, blocked, changed, shifts: [{ shift, defaultLevel, override }] }.
    day: { type: Object, required: true },
    // Read-only (employee change lock).
    disabled: { type: Boolean, default: false },
})

// Every change emits the whole day, so the parent replaces that date's overrides.
const emit = defineEmits(['apply-day'])

const LEVELS = ['available', 'not_preferred', 'unavailable']

const weekday = computed(() => isoWeekday(props.day.date))

function overrides() {
    return Object.fromEntries(props.day.shifts.filter((s) => s.override).map((s) => [s.shift.id, s.override]))
}

function optionsFor(entry) {
    return [
        {
            value: 'default',
            level: entry.defaultLevel,
            label: __('availability.day.default', { level: __(`availability.state.${entry.defaultLevel}`) }),
        },
        ...LEVELS.map((level) => ({ value: level, level, label: __(`availability.state.${level}`) })),
    ]
}

// The level the cell shows: a blocked day is unavailable for every shift.
function shownLevel(entry) {
    if (props.day.blocked) return 'unavailable'
    return entry.override ?? entry.defaultLevel
}

function choose(shiftId, value) {
    const shifts = overrides()
    if (value === 'default') delete shifts[shiftId]
    else shifts[shiftId] = value
    emit('apply-day', { date: props.day.date, blocked: props.day.blocked, shifts })
}

function setBlocked(blocked) {
    emit('apply-day', { date: props.day.date, blocked, shifts: overrides() })
}

function reset() {
    emit('apply-day', { date: props.day.date, blocked: false, shifts: {} })
}
</script>

<template>
    <div class="space-y-3">
        <p
            v-if="day.holiday"
            class="rounded-md border border-(--color-badge-warning-border) bg-(--color-badge-warning-bg) px-3 py-2 text-sm text-(--color-badge-warning-text)"
        >
            {{ __('availability.day.holiday_notice') }}
        </p>

        <CheckboxInput :model-value="day.blocked" :disabled="disabled" @update:model-value="setBlocked">
            {{ __('availability.day.block') }}
        </CheckboxInput>

        <CardSeparator flush-top />

        <p v-if="!day.shifts.length" class="text-sm text-(--color-text-secondary)">
            {{ __('availability.grid.no_shifts_on_day') }}
        </p>

        <table v-else class="w-full table-fixed text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-(--color-table-header-text)">
                    <th class="w-32 py-2 text-left font-medium">
                        {{ __('availability.grid.shift_column') }}
                    </th>
                    <th class="py-2 text-center font-medium">
                        {{ __(`availability.weekday.${weekday}`) }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="entry in day.shifts" :key="entry.shift.id">
                    <th class="py-1 pr-3 text-left font-medium text-(--color-table-row-text)">
                        <span class="block">{{ entry.shift.name }}</span>
                        <span class="block text-xs font-normal text-(--color-text-secondary)">
                            {{ entry.shift.start_time }} – {{ entry.shift.end_time }}
                        </span>
                    </th>
                    <td class="p-1">
                        <AvailabilityLevelCell
                            :data-testid="`date-cell-${entry.shift.id}`"
                            :level="shownLevel(entry)"
                            :selected="entry.override ?? 'default'"
                            :changed="!day.blocked && entry.override !== null"
                            :options="optionsFor(entry)"
                            :disabled="disabled || day.blocked"
                            :aria-label="__('availability.grid.cell', {
                                shift: entry.shift.name,
                                day: __(`availability.weekday.${weekday}`),
                                state: __(`availability.state.${shownLevel(entry)}`),
                            })"
                            @choose="choose(entry.shift.id, $event)"
                        />
                    </td>
                </tr>
            </tbody>
        </table>

        <ButtonSecondary v-if="day.changed && !disabled" type="button" @click="reset">
            {{ __('availability.day.reset') }}
        </ButtonSecondary>
    </div>
</template>
