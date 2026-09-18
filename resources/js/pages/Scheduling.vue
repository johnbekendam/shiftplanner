<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Calendar from '@/components/ui/Calendar.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import WorkcenterScheduleCard from '@/components/scheduling/WorkcenterScheduleCard.vue'
import { CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'
import { postAsync } from '@/utils/inertiaAsync'

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
    publishedWorkcenterWeeks: { type: Array, default: () => [] }, // { workcenter_id, week_start }, within the visible month
    // Y-m-d, the Monday of the 2-week cycle containing weekStart, or null when no
    // planning period start is configured yet (there's no anchor to compute cycles from).
    cycleStart: { type: String, default: null },
    // { status: 'pending'|'running'|'done'|'failed', error: string|null } for the most
    // recent run of the viewed cycle, or null if none has ever run.
    generationRun: { type: Object, default: null },
})

const GENERATION_POLL_MS = 3000

function isGenerationActive(run) {
    return !!run && (run.status === 'pending' || run.status === 'running')
}

async function generate() {
    await postAsync(`/planning/cycles/${props.cycleStart}/generate`).catch(() => {})
}

const cycleEnd = computed(() => (props.cycleStart ? addDays(props.cycleStart, 13) : null))

function formatCycleDate(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const generateLabel = computed(() => {
    if (isGenerationActive(props.generationRun)) return __('planning.generating')
    if (props.generationRun?.status === 'failed') return __('planning.generate_again')
    return __('planning.generate_cycle', { start: formatCycleDate(props.cycleStart), end: formatCycleDate(cycleEnd.value) })
})

let pollTimer = null

function stopGenerationPoll() {
    if (pollTimer) {
        clearTimeout(pollTimer)
        pollTimer = null
    }
}

// Self-perpetuating via onFinish, rather than relying solely on the watch
// below picking up a "new" prop object with the same still-pending status —
// keeps polling correctly even if a reload's response were ever reference-
// equal to what's already there.
function scheduleGenerationPoll() {
    stopGenerationPoll()
    pollTimer = setTimeout(() => {
        router.reload({
            only: ['generationRun', 'weekCells', 'coverage'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                if (isGenerationActive(props.generationRun)) scheduleGenerationPoll()
            },
        })
    }, GENERATION_POLL_MS)
}

watch(
    () => props.generationRun,
    (run) => {
        if (isGenerationActive(run)) scheduleGenerationPoll()
        else stopGenerationPoll()
    },
    { immediate: true },
)

onBeforeUnmount(stopGenerationPoll)

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

function isWorkcenterWeekPublished(workcenterId, weekStart) {
    return props.publishedWorkcenterWeeks.some((p) => p.workcenter_id === workcenterId && p.week_start === weekStart)
}

// Same "relevant" idea dayStates uses (checked, checked shift, attached, spots > 0), just
// asking "does this workcenter have anything relevant in this week" instead of "this day".
function relevantWorkcenterIdsForWeek(weekStart) {
    const weekEnd = addDays(weekStart, 6)
    const ids = new Set()
    for (const c of props.coverage) {
        if (c.date < weekStart || c.date > weekEnd) continue
        if (!checkedWorkcenterIds.value.includes(c.workcenter_id)) continue
        if (!checkedShiftIds.value.includes(c.shift_id)) continue
        ids.add(c.workcenter_id)
    }
    return [...ids]
}

// A week's calendar marker lights up only when every workcenter relevant to it (checked,
// with checked-shift coverage that week) is published — mirrors dayStates' AND-aggregation
// and "zero-relevant is muted, not vacuously true" rules above, just at week granularity.
const weekMarkerDays = computed(() => {
    const states = {}

    for (let day = 1; day <= daysInMonth.value; day++) {
        const dateStr = `${props.year}-${String(props.month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
        const monday = mondayOf(dateStr)
        const relevantIds = relevantWorkcenterIdsForWeek(monday)
        if (relevantIds.length && relevantIds.every((id) => isWorkcenterWeekPublished(id, monday))) {
            states[day] = true
        }
    }
    return states
})

function mondayOf(dateString) {
    const [y, m, d] = dateString.split('-').map(Number)
    const date = new Date(y, m - 1, d)
    const offset = (date.getDay() + 6) % 7 // 0 = Monday
    date.setDate(date.getDate() - offset)
    return dateStr(date.getFullYear(), date.getMonth() + 1, date.getDate())
}

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
        router.get('/planning', { year, month, date }, { preserveState: true, preserveScroll: true })
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
                    :week-marker-days="weekMarkerDays"
                    week-marker-color="warning"
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

            <div v-if="cycleStart" class="mt-4 flex items-center gap-3">
                <ButtonPrimary
                    type="button"
                    data-testid="generate-plan-button"
                    :disabled="isGenerationActive(generationRun)"
                    @click="generate"
                >
                    {{ generateLabel }}
                </ButtonPrimary>
                <span
                    v-if="generationRun?.status === 'failed'"
                    data-testid="generation-error"
                    class="text-sm text-(--color-badge-error-text)"
                >
                    {{ __('planning.generation_failed', { error: generationRun.error }) }}
                </span>
            </div>

            <div v-if="visibleWorkcenters.length" class="mt-4 flex flex-col gap-4">
                <WorkcenterScheduleCard
                    v-for="{ workcenter, schedule } in visibleWorkcenters"
                    :key="workcenter.id"
                    :workcenter="workcenter"
                    :schedule="schedule"
                    :week-start="weekStart"
                    :published="isWorkcenterWeekPublished(workcenter.id, weekStart)"
                />
            </div>
        </div>
    </AppLayout>
</template>
