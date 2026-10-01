<script setup>
import { computed } from 'vue'
import Card from '@/components/ui/Card.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { NumberInput } from '@/components/ui/Input'
import { isoWeekday } from '@/utils/availabilityCalendar'
import { formatDate } from '@/utils/date'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // dayDemand() of the selected date, or null without a selection.
    day: { type: Object, default: null },
    // Shift label by id.
    shiftLabel: { type: Function, required: true },
})

// set-spots — { shiftId, spots } for the selected date; reset — all overrides of that date.
const emit = defineEmits(['set-spots', 'reset'])

const title = computed(() => props.day
    ? __('demand.day.title', {
        weekday: __(`availability.weekday_long.${isoWeekday(props.day.date)}`),
        date: formatDate(props.day.date),
    })
    : __('demand.specific.heading'))

const hasOverrides = computed(() => !!props.day?.shifts.some((s) => s.override !== null))
</script>

<template>
    <Card class="min-w-0 flex-1" data-testid="demand-date-card">
        <template #header>
            <div class="flex h-12 items-center px-6 text-md font-semibold">{{ title }}</div>
        </template>

        <div class="px-6 py-4">
            <table v-if="day" class="w-full table-fixed text-sm">
                <thead>
                    <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                        <th class="py-2 pr-3 font-medium">{{ __('demand.column.shift') }}</th>
                        <th class="w-24 py-2 pr-3 font-medium">{{ __('demand.day.assigned') }}</th>
                        <th class="w-24 py-2 font-medium">{{ __('demand.day.slots') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="shift in day.shifts"
                        :key="shift.shift_id"
                        :data-testid="`demand-date-row-${shift.shift_id}`"
                        class="border-b border-(--color-table-row-separator)"
                    >
                        <td class="py-2 pr-3 align-middle">{{ shiftLabel(shift.shift_id) }}</td>
                        <td class="py-2 pr-3 align-middle" :data-testid="`demand-date-assigned-${shift.shift_id}`">{{ shift.assigned }}</td>
                        <td class="py-2 align-middle">
                            <!-- An overridden value gets a ring, as the calendar marks a changed day. -->
                            <div
                                :data-overridden="shift.override !== null"
                                class="rounded-lg"
                                :class="shift.override !== null ? 'ring-2 ring-(--color-badge-standard-text)' : ''"
                            >
                                <NumberInput
                                    :model-value="shift.spots"
                                    :min="shift.assigned"
                                    class="w-full"
                                    @update:model-value="emit('set-spots', { shiftId: shift.shift_id, spots: $event })"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-(--color-text-secondary)">{{ __('demand.specific.hint') }}</p>
        </div>

        <!-- Only a date with its own changes can go back to the default. -->
        <template v-if="hasOverrides" #footer>
            <div class="flex justify-end px-6 py-3">
                <ButtonSecondary type="button" data-testid="demand-date-reset" @click="emit('reset')">
                    {{ __('demand.day.reset') }}
                </ButtonSecondary>
            </div>
        </template>
    </Card>
</template>
