<script setup>
import { computed, ref } from 'vue'
import Calendar from '@/components/ui/Calendar.vue'
import { dateString } from '@/utils/availabilityCalendar'
import { monthDemandStates } from '@/utils/demandCalendar'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // The current weekday defaults, pending edits included: [{ shift_id, spots[7] }].
    defaults: { type: Array, default: () => [] },
    // The current overrides, pending edits included: groupDemandOverrides() shape.
    overrides: { type: Object, default: () => ({}) },
    // groupAssigned() shape.
    assigned: { type: Object, default: () => ({}) },
    // The 'Y-m-d' date whose demand is being edited, or null.
    selectedDate: { type: String, default: null },
})

// A second click on the selected date deselects it.
const emit = defineEmits(['update:selectedDate'])

function onDayClick({ year: y, month: m, day }) {
    const date = dateString(y, m, day)
    emit('update:selectedDate', props.selectedDate === date ? null : date)
}

// Opens on the month of the selected date, else the current month.
const start = props.selectedDate
    ? props.selectedDate.split('-').map(Number)
    : [new Date().getFullYear(), new Date().getMonth() + 1]
const year = ref(start[0])
const month = ref(start[1])

const states = computed(() => monthDemandStates(year.value, month.value, {
    defaults: props.defaults,
    overrides: props.overrides,
    assigned: props.assigned,
}))

const legenda = computed(() => ({
    success: __('demand.calendar.legend.staffed'),
    warning: __('demand.calendar.legend.open'),
}))

const borderLegenda = computed(() => ({
    solid: __('demand.calendar.legend.changed'),
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
    <!-- As wide as its content; the legend sits in the calendar footer. -->
    <Calendar
        class="w-fit shrink-0"
        :year="year"
        :month="month"
        :day-states="states.dayStates"
        :day-borders="states.dayBorders"
        :legenda="legenda"
        :border-legenda="borderLegenda"
        :enable-week-day-selection="false"
        bold-current
        :ring-day="ringDay"
        :highlight-selection="false"
        @change="onChange"
        @day-click="onDayClick"
    />
</template>
