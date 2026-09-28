<script setup>
import { computed, ref } from 'vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import AvailabilityCalendar from '@/components/AvailabilityCalendar.vue'
import AvailabilityGrid from '@/components/AvailabilityGrid.vue'
import DateAvailabilityGrid from '@/components/DateAvailabilityGrid.vue'
import DayBlockToggle from '@/components/DayBlockToggle.vue'
import DayResetButton from '@/components/DayResetButton.vue'
import ShiftNote from '@/components/ShiftNote.vue'
import { dayAvailability, isoWeekday } from '@/utils/availabilityCalendar'
import { formatDate } from '@/utils/date'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Shifts with their `weekdays`.
    shifts: { type: Array, default: () => [] },
    // The current default rows, pending edits included: [{ weekday, shift_id, level }].
    defaults: { type: Array, default: () => [] },
    // The current overrides, pending edits included: groupOverrides() shape.
    overrides: { type: Object, default: () => ({}) },
    // [{ start_date, end_date }], pending rows included.
    holidays: { type: Array, default: () => [] },
    availableFrom: { type: String, default: null },
    // Read-only (employee change lock, archived employee).
    disabled: { type: Boolean, default: false },
    scheduleNoteHtml: { type: String, default: null },
    // Manager surface: the grid's empty state also points at Settings.
    showAddHint: { type: Boolean, default: false },
    // Changing it re-seeds the default grid (after a save or cancel).
    gridKey: { type: [Number, String], default: 0 },
})

// update:availability — a default grid change; apply-day — a whole date.
const emit = defineEmits(['update:availability', 'apply-day'])

// The date whose availability the date card edits, or null.
const selectedDate = ref(null)

const selectedDay = computed(() => selectedDate.value
    ? dayAvailability(selectedDate.value, {
        shifts: props.shifts,
        availableFrom: props.availableFrom || null,
        holidays: props.holidays,
        defaults: props.defaults,
        overrides: props.overrides,
    })
    : null)

const dayTitle = computed(() => selectedDay.value
    ? __('availability.day.title', {
        weekday: __(`availability.weekday_long.${isoWeekday(selectedDay.value.date)}`),
        date: formatDate(selectedDay.value.date),
    })
    : '')

const canReset = computed(() => !props.disabled && !!selectedDay.value?.changed && !selectedDay.value.holiday)
</script>

<template>
    <div class="space-y-6">
        <Card data-testid="default-week-card">
            <template #header>
                <div data-testid="default-week-card-header" class="flex h-12 items-center px-6 text-md font-semibold">
                    {{ __('availability.default_week.heading') }}
                </div>
            </template>
            <div class="px-6 py-4">
                <AvailabilityGrid
                    :key="gridKey"
                    :shifts="shifts"
                    :availability="defaults"
                    :disabled="disabled"
                    :show-add-hint="showAddHint"
                    @update:availability="emit('update:availability', $event)"
                />
                <template v-if="scheduleNoteHtml">
                    <CardSeparator />
                    <ShiftNote :html="scheduleNoteHtml" data-testid="availability-card-note" />
                </template>
            </div>
        </Card>

        <!-- The calendar keeps its content width; the date card takes the rest. -->
        <section data-testid="availability-calendar-section" class="flex flex-col gap-6 sm:flex-row sm:items-start">
            <AvailabilityCalendar
                v-model:selected-date="selectedDate"
                :shifts="shifts"
                :defaults="defaults"
                :overrides="overrides"
                :holidays="holidays"
                :available-from="availableFrom || null"
            />

            <Card class="min-w-0 flex-1" data-testid="date-card">
                <template #header>
                    <div data-testid="date-card-header" class="flex h-12 items-center px-6 text-md font-semibold">
                        {{ selectedDay ? dayTitle : __('availability.specific.heading') }}
                    </div>
                </template>

                <div class="px-6 py-4">
                    <DateAvailabilityGrid
                        v-if="selectedDay"
                        :day="selectedDay"
                        :disabled="disabled"
                        @apply-day="emit('apply-day', $event)"
                    />
                    <template v-if="selectedDay && !selectedDay.holiday">
                        <CardSeparator />
                        <div data-testid="day-block-row">
                            <DayBlockToggle
                                :day="selectedDay"
                                :disabled="disabled"
                                @apply-day="emit('apply-day', $event)"
                            />
                        </div>
                    </template>
                    <p v-else-if="!selectedDay" data-testid="date-hint" class="text-sm text-(--color-text-secondary)">
                        {{ __('availability.specific.hint') }}
                    </p>
                </div>

                <!-- Only a date with its own changes can go back to the default. -->
                <template v-if="canReset" #footer>
                    <div data-testid="date-card-footer" class="flex justify-end px-6 py-3">
                        <DayResetButton :day="selectedDay" @apply-day="emit('apply-day', $event)" />
                    </div>
                </template>
            </Card>
        </section>
    </div>
</template>
