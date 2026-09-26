<script setup>
import { computed, ref } from 'vue'
import Calendar from '@/components/ui/Calendar.vue'
import DayAvailabilityDialog from '@/components/DayAvailabilityDialog.vue'
import { dateString, dayAvailability, monthStates } from '@/utils/availabilityCalendar'
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
    // Read-only (employee change lock): the dialog opens but cannot apply.
    disabled: { type: Boolean, default: false },
    // The ISO weekday (1–7) whose default is being edited, or null.
    selectedWeekday: { type: Number, default: null },
})

const emit = defineEmits(['apply-day', 'update:selectedWeekday'])

// A weekday header click selects that weekday; a second click deselects it.
function onWeekdayClick({ weekday }) {
    emit('update:selectedWeekday', props.selectedWeekday === weekday ? null : weekday)
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

// The open dialog's date. The day state is derived, so it follows the
// current props while the dialog is open.
const openDate = ref(null)
const openDay = computed(() => openDate.value ? dayAvailability(openDate.value, context.value) : null)

function onDayClick({ year: y, month: m, day }) {
    openDate.value = dateString(y, m, day)
}

function onApply(payload) {
    emit('apply-day', payload)
    openDate.value = null
}
</script>

<template>
    <div>
        <Calendar
            :year="year"
            :month="month"
            :day-states="states.dayStates"
            :day-borders="states.dayBorders"
            :legenda="legenda"
            :border-legenda="borderLegenda"
            :date-range-start="availableFrom || null"
            :selected-weekday="selectedWeekday"
            :highlight-selection="false"
            @change="onChange"
            @day-click="onDayClick"
            @weekday-click="onWeekdayClick"
        />

        <DayAvailabilityDialog
            :open="openDate !== null"
            :day="openDay"
            :disabled="disabled"
            @close="openDate = null"
            @apply="onApply"
        />
    </div>
</template>
