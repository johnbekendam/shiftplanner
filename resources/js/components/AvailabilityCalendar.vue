<script setup>
import { computed, ref } from 'vue'
import Calendar from '@/components/ui/Calendar.vue'
import CalendarLegend from '@/components/ui/CalendarLegend.vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import { dateString, monthStates } from '@/utils/availabilityCalendar'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Shifts with their `weekdays`, as on the default grid.
    shifts: { type: Array, default: () => [] },
    // The current default rows, pending grid edits included: [{ weekday, shift_id, level }].
    defaults: { type: Array, default: () => [] },
    // The current overrides, pending edits included: groupOverrides() shape.
    overrides: { type: Object, default: () => ({}) },
    // [{ start_date, end_date }]
    holidays: { type: Array, default: () => [] },
    availableFrom: { type: String, default: null },
    // True while the default week is being edited (the weekday header is selected).
    defaultWeekSelected: { type: Boolean, default: false },
    // The 'Y-m-d' date whose availability is being edited, or null.
    selectedDate: { type: String, default: null },
})

// Picking the default week or a date clears the other; a second click deselects.
const emit = defineEmits(['update:defaultWeekSelected', 'update:selectedDate'])

// Any weekday letter selects the whole header: the default week.
function onWeekdayClick() {
    emit('update:defaultWeekSelected', !props.defaultWeekSelected)
    emit('update:selectedDate', null)
}

function onDayClick({ year: y, month: m, day }) {
    const date = dateString(y, m, day)
    emit('update:selectedDate', props.selectedDate === date ? null : date)
    emit('update:defaultWeekSelected', false)
}

const today = new Date()
const year = ref(today.getFullYear())
const month = ref(today.getMonth() + 1)

const context = computed(() => ({
    shifts: props.shifts,
    availableFrom: props.availableFrom || null,
    holidays: props.holidays,
    defaults: props.defaults,
    overrides: props.overrides,
}))

const states = computed(() => monthStates(year.value, month.value, context.value))

const legenda = computed(() => ({
    success: __('availability.calendar.legend.available'),
    warning: __('availability.calendar.legend.not_preferred'),
    error: __('availability.calendar.legend.unavailable'),
}))

const borderLegenda = computed(() => ({
    solid: __('availability.calendar.legend.changed'),
    dashed: __('availability.calendar.legend.holiday'),
}))

function onChange(event) {
    year.value = event.year
    month.value = event.month
}

// The selected date's day number, when it falls in the shown month.
const ringDay = computed(() => {
    if (!props.selectedDate) return null
    const [y, m, d] = props.selectedDate.split('-').map(Number)
    return y === year.value && m === month.value ? d : null
})
</script>

<template>
    <!-- The calendar keeps its content width; the info card takes the rest. -->
    <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
        <Calendar
            class="w-fit shrink-0"
            :year="year"
            :month="month"
            :day-states="states.dayStates"
            :day-borders="states.dayBorders"
            :date-range-start="availableFrom || null"
            :weekday-header-selected="defaultWeekSelected"
            :ring-day="ringDay"
            :highlight-selection="false"
            @change="onChange"
            @day-click="onDayClick"
            @weekday-click="onWeekdayClick"
        />

        <Card class="min-w-0 flex-1" data-testid="availability-info-card">
            <div class="px-6 py-4">
                <p data-testid="default-week-hint" class="text-sm text-(--color-text-secondary)">
                    {{ __('availability.default_week.hint') }}
                </p>
                <CardSeparator />
                <CalendarLegend :legenda="legenda" :border-legenda="borderLegenda" />
                <template v-if="$slots.default">
                    <CardSeparator />
                    <slot />
                </template>
            </div>
        </Card>
    </div>
</template>
