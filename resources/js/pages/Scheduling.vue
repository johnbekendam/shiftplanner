<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import SchedulingCell from '@/components/scheduling/SchedulingCell.vue'
import { SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    workcenters: { type: Array, default: () => [] }, // { id, name }, active only
    workcenterId: { type: Number, default: null },
    weekStart: { type: String, required: true }, // Y-m-d, a Monday
    days: { type: Array, default: () => [] }, // 7 Y-m-d strings
    shifts: { type: Array, default: () => [] }, // { id, name, start_time, end_time }
    cells: { type: Array, default: () => [] }, // { shift_id, date, spots, assignments }
})

const workcenterOptions = computed(() => props.workcenters.map((w) => ({ value: w.id, label: w.name })))

function goTo(params) {
    router.get('/scheduling', params, { preserveState: true, preserveScroll: true })
}

function onWorkcenterChange(workcenterId) {
    goTo({ workcenter_id: workcenterId, week_start: props.weekStart })
}

function addDays(dateStr, offset) {
    const [y, m, d] = dateStr.split('-').map(Number)
    const date = new Date(y, m - 1, d + offset)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function goToPreviousWeek() {
    goTo({ workcenter_id: props.workcenterId, week_start: addDays(props.weekStart, -7) })
}

function goToNextWeek() {
    goTo({ workcenter_id: props.workcenterId, week_start: addDays(props.weekStart, 7) })
}

function goToThisWeek() {
    goTo({ workcenter_id: props.workcenterId })
}

function formatDay(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' })
}

const dateRangeLabel = computed(() => {
    if (!props.days.length) return ''
    const [y1, m1, d1] = props.days[0].split('-').map(Number)
    const [y2, m2, d2] = props.days[6].split('-').map(Number)
    const start = new Date(y1, m1 - 1, d1).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    const end = new Date(y2, m2 - 1, d2).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    return `${start} – ${end}`
})

function cellFor(shiftId, date) {
    return props.cells.find((c) => c.shift_id === shiftId && c.date === date)
        ?? { spots: 0, overridden: false, assignments: [] }
}
</script>

<template>
    <AppLayout>
        <Head :title="__('scheduling.title')" />

        <Card class="max-w-5xl">
            <div class="space-y-4 p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <SelectInput
                        :model-value="workcenterId"
                        :options="workcenterOptions"
                        :placeholder="__('scheduling.select_workcenter')"
                        class="w-56"
                        @update:model-value="onWorkcenterChange"
                    />

                    <div v-if="workcenterId" class="flex items-center gap-2">
                        <ButtonSecondary type="button" icon="chevron-left" :aria-label="__('scheduling.prev_week')" @click="goToPreviousWeek" />
                        <span data-testid="date-range" class="text-sm text-(--color-text-secondary)">{{ dateRangeLabel }}</span>
                        <ButtonSecondary type="button" icon="chevron-right" :aria-label="__('scheduling.next_week')" @click="goToNextWeek" />
                        <ButtonSecondary type="button" @click="goToThisWeek">{{ __('scheduling.this_week') }}</ButtonSecondary>
                    </div>
                </div>

                <p v-if="!workcenters.length" class="py-6 text-center text-(--color-text-secondary)">
                    {{ __('scheduling.no_workcenters') }}
                </p>
                <p v-else-if="!shifts.length" class="py-6 text-center text-(--color-text-secondary)">
                    {{ __('scheduling.no_shifts') }}
                </p>
                <table v-else class="w-full table-fixed text-sm">
                    <thead>
                        <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                            <th class="w-32 py-2 pr-3 font-medium" />
                            <th v-for="day in days" :key="day" class="py-2 pr-2 font-medium">
                                {{ formatDay(day) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="shift in shifts"
                            :key="shift.id"
                            data-testid="scheduling-shift-row"
                            class="border-b border-(--color-table-row-separator) align-top"
                        >
                            <td class="py-2 pr-3">
                                <div class="font-medium">{{ shift.name }}</div>
                                <div class="text-xs text-(--color-text-secondary)">{{ shift.start_time }}–{{ shift.end_time }}</div>
                            </td>
                            <td
                                v-for="day in days"
                                :key="day"
                                class="py-2 pr-2"
                                :data-testid="`scheduling-cell-${shift.id}-${day}`"
                            >
                                <SchedulingCell
                                    :workcenter-id="workcenterId"
                                    :shift-id="shift.id"
                                    :date="day"
                                    :cell="cellFor(shift.id, day)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AppLayout>
</template>
