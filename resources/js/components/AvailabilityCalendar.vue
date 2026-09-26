<script setup>
import { computed, ref } from 'vue'
import Calendar from '@/components/ui/Calendar.vue'
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
    // The ISO weekday (1–7) whose default is being edited, or null.
    selectedWeekday: { type: Number, default: null },
    // The 'Y-m-d' date whose availability is being edited, or null.
    selectedDate: { type: String, default: null },
})

// Picking a weekday or a date clears the other; a second click deselects.
const emit = defineEmits(['update:selectedWeekday', 'update:selectedDate'])

function onWeekdayClick({ weekday }) {
    emit('update:selectedWeekday', props.selectedWeekday === weekday ? null : weekday)
    emit('update:selectedDate', null)
}

function onDayClick({ year: y, month: m, day }) {
    const date = dateString(y, m, day)
    emit('update:selectedDate', props.selectedDate === date ? null : date)
    emit('update:selectedWeekday', null)
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
    <Calendar
        :year="year"
        :month="month"
        :day-states="states.dayStates"
        :day-borders="states.dayBorders"
        :legenda="legenda"
        :border-legenda="borderLegenda"
        :date-range-start="availableFrom || null"
        :selected-weekday="selectedWeekday"
        :ring-day="ringDay"
        :highlight-selection="false"
        @change="onChange"
        @day-click="onDayClick"
        @weekday-click="onWeekdayClick"
    />
</template>
