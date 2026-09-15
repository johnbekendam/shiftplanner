<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Calendar from '@/components/ui/Calendar.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import WorkcenterScheduleCard from '@/components/scheduling/WorkcenterScheduleCard.vue'
import { CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'
import { postAsync, deleteAsync } from '@/utils/inertiaAsync'

const __ = useI18n()

const props = defineProps({
    workcenters: { type: Array, default: () => [] }, // { id, name }, active only
    shifts: { type: Array, default: () => [] }, // { id, name, start_time, end_time }, all shift definitions
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    coverage: { type: Array, default: () => [] }, // { workcenter_id, shift_id, date, spots, assigned }
    date: { type: String, required: true }, // Y-m-d, the currently selected day
    weekStart: { type: String, required: true }, // Y-m-d, the Monday of the selected day's week
    weekCells: { type: Array, default: () => [] }, // { workcenter_id, shift_id, date, spots, overridden, assignments }
    weekPublished: { type: Boolean, default: false },
    publishedDays: { type: Object, default: () => ({}) }, // { [day]: true }, days in the visible month with a published week
})

const checkedWorkcenterIds = ref(props.workcenters.map((w) => w.id))
const checkedShiftIds = ref(props.shifts.map((s) => s.id))

function toggleWorkcenter(id, checked) {
    checkedWorkcenterIds.value = checked
        ? [...checkedWorkcenterIds.value, id]
        : checkedWorkcenterIds.value.filter((x) => x !== id)
}

function toggleShift(id, checked) {
    checkedShiftIds.value = checked
        ? [...checkedShiftIds.value, id]
        : checkedShiftIds.value.filter((x) => x !== id)
}

const daysInMonth = computed(() => new Date(props.year, props.month, 0).getDate())

const dayStates = computed(() => {
    const states = {}
    for (let day = 1; day <= daysInMonth.value; day++) {
        const dateStr = `${props.year}-${String(props.month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
        const relevant = props.coverage.filter(
            (c) =>
                c.date === dateStr &&
                checkedWorkcenterIds.value.includes(c.workcenter_id) &&
                checkedShiftIds.value.includes(c.shift_id),
        )
        if (!relevant.length) {
            states[day] = 'muted'
        } else {
            states[day] = relevant.every((c) => c.assigned >= c.spots) ? 'success' : 'warning'
        }
    }
    return states
})

const legenda = computed(() => ({
    success: __('scheduling.legend_staffed'),
    warning: __('scheduling.legend_open_spots'),
}))

const selectedDay = computed(() => Number(props.date.split('-')[2]))

function pad(n) {
    return String(n).padStart(2, '0')
}

function dateStr(year, month, day) {
    return `${year}-${pad(month)}-${pad(day)}`
}

function addDays(dateString, offset) {
    const [y, m, d] = dateString.split('-').map(Number)
    const date = new Date(y, m - 1, d + offset)
    return dateStr(date.getFullYear(), date.getMonth() + 1, date.getDate())
}

function onCalendarChange({ year, month, day }) {
    const date = dateStr(year, month, day)
    if (year !== props.year || month !== props.month || date !== props.date) {
        router.get('/scheduling', { year, month, date }, { preserveState: true, preserveScroll: true })
    }
}

const weekDays = computed(() => Array.from({ length: 7 }, (_, i) => addDays(props.weekStart, i)))

function cellsFor(workcenterId, shiftId) {
    return weekDays.value.map(
        (date) =>
            props.weekCells.find((c) => c.workcenter_id === workcenterId && c.shift_id === shiftId && c.date === date)
                ?? { date, spots: 0, overridden: false, assignments: [] },
    )
}

function scheduleFor(workcenterId) {
    return props.shifts
        .filter((s) => checkedShiftIds.value.includes(s.id))
        .map((s) => ({ shift: s, cells: cellsFor(workcenterId, s.id) }))
        .filter((entry) => entry.cells.some((c) => c.spots > 0))
}

const visibleWorkcenters = computed(() =>
    props.workcenters
        .filter((w) => checkedWorkcenterIds.value.includes(w.id))
        .map((w) => ({ workcenter: w, schedule: scheduleFor(w.id) }))
        .filter(({ schedule }) => schedule.length > 0),
)

async function togglePublish() {
    const url = `/scheduling/weeks/${props.weekStart}/publish`
    if (props.weekPublished) {
        await deleteAsync(url).catch(() => {})
    } else {
        await postAsync(url).catch(() => {})
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="__('scheduling.title')" />

        <div class="mx-auto max-w-6xl">
            <p v-if="!workcenters.length" class="py-6 text-center text-(--color-text-secondary)">
                {{ __('scheduling.no_schedule') }}
            </p>
            <div v-else class="flex flex-col items-start gap-4 lg:flex-row">
                <Calendar
                    :year="year"
                    :month="month"
                    :initial-day="selectedDay"
                    :day-states="dayStates"
                    :legenda="legenda"
                    :enable-day-selection="true"
                    :enable-week-day-selection="false"
                    :week-marker-days="publishedDays"
                    @change="onCalendarChange"
                />

                <div class="flex w-full flex-col gap-4 sm:flex-row lg:w-auto">
                    <Card class="w-full sm:w-auto">
                        <template #header>
                            <div class="px-6 py-3 text-base font-semibold">{{ __('scheduling.filter_workcenters') }}</div>
                        </template>

                        <div class="flex flex-col gap-1.5 p-6">
                            <CheckboxInput
                                v-for="workcenter in workcenters"
                                :key="workcenter.id"
                                :model-value="checkedWorkcenterIds.includes(workcenter.id)"
                                @update:model-value="(checked) => toggleWorkcenter(workcenter.id, checked)"
                            >
                                <span class="whitespace-nowrap">{{ workcenter.name }}</span>
                            </CheckboxInput>
                        </div>
                    </Card>

                    <Card class="w-full sm:w-48">
                        <template #header>
                            <div class="px-6 py-3 text-base font-semibold">{{ __('scheduling.filter_shifts') }}</div>
                        </template>

                        <div class="flex flex-col gap-1.5 p-6">
                            <CheckboxInput
                                v-for="shift in shifts"
                                :key="shift.id"
                                :model-value="checkedShiftIds.includes(shift.id)"
                                @update:model-value="(checked) => toggleShift(shift.id, checked)"
                            >
                                {{ shift.name }}
                            </CheckboxInput>
                        </div>
                    </Card>
                </div>
            </div>

            <div v-if="workcenters.length" class="mt-4 flex items-center gap-3">
                <span
                    v-if="weekPublished"
                    class="rounded-full border border-(--color-badge-custom-border) bg-(--color-badge-custom-bg) px-2 py-0.5 text-xs font-medium text-(--color-badge-custom-text)"
                >
                    {{ __('scheduling.published_label') }}
                </span>
                <ButtonSecondary type="button" data-testid="publish-week-button" @click="togglePublish">
                    {{ weekPublished ? __('scheduling.unpublish') : __('scheduling.publish') }}
                </ButtonSecondary>
            </div>

            <div v-if="visibleWorkcenters.length" class="mt-4 flex flex-col gap-4">
                <WorkcenterScheduleCard
                    v-for="{ workcenter, schedule } in visibleWorkcenters"
                    :key="workcenter.id"
                    :workcenter="workcenter"
                    :schedule="schedule"
                />
            </div>
        </div>
    </AppLayout>
</template>
