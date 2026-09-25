<script setup>
import { ref, computed, watch, onBeforeUnmount, onMounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Calendar from '@/components/ui/Calendar.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import WorkcenterScheduleCard from '@/components/scheduling/WorkcenterScheduleCard.vue'
import GenerationChangeSummary from '@/components/scheduling/GenerationChangeSummary.vue'
import { CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'
import { postAsync } from '@/utils/inertiaAsync'

const __ = useI18n()
const page = usePage()

const props = defineProps({
    workcenters: { type: Array, default: () => [] }, // { id, name }, active only
    shifts: { type: Array, default: () => [] }, // { id, name, start_time, end_time }, all shift definitions
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    coverage: { type: Array, default: () => [] }, // { workcenter_id, shift_id, date, spots, assigned }
    date: { type: String, required: true }, // Y-m-d, the currently selected day
    weekStart: { type: String, required: true }, // Y-m-d, the Monday of the selected day's week
    weekCells: { type: Array, default: () => [] }, // { workcenter_id, shift_id, date, spots, overridden, assignments }
    publishedWorkcenterWeeks: { type: Array, default: () => [] }, // { workcenter_id, week_start, planner_open }, within the visible month
    // Y-m-d, the Monday of the 2-week cycle containing weekStart, or null when no
    // planning period start is configured yet (there's no anchor to compute cycles from).
    cycleStart: { type: String, default: null },
    // The most recent run of the viewed cycle, or null if none has ever run:
    // { id, status: 'pending'|'running'|'done'|'failed', error: string|null,
    //   changes: [{ type: 'added'|'removed', employee_id, employee_name, workcenter_name, shift_name, date }],
    //   unfulfilled: [{ workcenter_id, shift_id, workcenter_name, shift_name, date, reason }] }.
    // changes/unfulfilled are only ever populated once status is 'done'.
    generationRun: { type: Object, default: null },
    // { start, end } (Y-m-d) from Settings, or null until both are configured.
    planningPeriod: { type: Object, default: null },
    // { active, failedCount, firstError } across every cycle in the planning period,
    // or null when the period isn't configured — drives the Generate button, since one
    // click now generates every cycle in the period at once, not just the viewed one.
    generationStatus: { type: Object, default: null },
    // Employees with published shifts they were not told about (and no queued email yet):
    // Send planning is only enabled when this is above zero.
    uninformedCount: { type: Number, default: 0 },
})

const GENERATION_POLL_MS = 3000

function isGenerationActive(status) {
    return !!status?.active
}

function formatCycleDate(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const periodRangeLabel = computed(() => {
    if (!props.planningPeriod) return ''
    return `${formatCycleDate(props.planningPeriod.start)} – ${formatCycleDate(props.planningPeriod.end)}`
})

// The action opens a ConfirmDialog first; the actual write only happens once
// the manager confirms, then the dialog closes itself.
const generateDialogOpen = ref(false)
const sendDialogOpen = ref(false)

async function confirmGenerate() {
    generateDialogOpen.value = false
    await postAsync('/planning/generate').catch(() => {})
}

async function confirmSend() {
    sendDialogOpen.value = false
    await postAsync('/planning/send').catch(() => {})
}

const generateLabel = computed(() => {
    if (isGenerationActive(props.generationStatus)) return __('planning.generating')
    if (props.generationStatus?.failedCount > 0) return __('planning.generate_again')
    return __('planning.generate')
})

const generationErrorMessage = computed(() => {
    const status = props.generationStatus
    if (!status || status.failedCount === 0) return null
    let message = __('planning.generation_failed', { error: status.firstError })
    if (status.failedCount > 1) message += __('planning.generation_failed_more', { count: status.failedCount - 1 })
    return message
})

// Verify planning: null until the button is first pressed, then
// { [assignment id]: [violation code] } for the visible week.
const violations = ref(null)
// Set by the first press, before any answer arrives, so a week change
// during that first request still verifies the new week.
const verificationOn = ref(false)

function startVerification() {
    verificationOn.value = true
    verifyPlanning().catch(() => {})
}

// Only the latest request may set the result, so a slow answer for a week
// that is no longer shown cannot overwrite the current one.
let verifyRequest = 0

async function verifyPlanning() {
    const request = ++verifyRequest
    const { data } = await axios.get('/planning/verify', { params: { week_start: props.weekStart } })
    if (request !== verifyRequest) return
    // An empty PHP array arrives as [], not {}.
    violations.value = Array.isArray(data.violations) ? {} : data.violations
}

// Once verified, stay verified until a full reload: an edit reloads the
// week cells, and a week change loads new ones.
watch(
    () => [props.weekStart, props.weekCells],
    () => {
        if (verificationOn.value) verifyPlanning().catch(() => {})
    },
)

const verifyResult = computed(() => {
    if (violations.value === null) return null
    const count = Object.keys(violations.value).length
    return count ? __('planning.verify_result', { count }) : __('planning.verify_ok')
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
            only: ['generationStatus', 'weekCells', 'coverage', 'publishedWorkcenterWeeks', 'uninformedCount'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                if (isGenerationActive(props.generationStatus)) scheduleGenerationPoll()
            },
        })
    }, GENERATION_POLL_MS)
}

watch(
    () => props.generationStatus,
    (status) => {
        if (isGenerationActive(status)) scheduleGenerationPoll()
        else stopGenerationPoll()
    },
    { immediate: true },
)

onBeforeUnmount(stopGenerationPoll)

function selectedItemsSessionKey(name) {
    const userId = page.props.auth?.user?.id
    return userId ? `planning.${name}.${userId}` : null
}

function storeSelectedItems(name, availableIds, selectedIds) {
    const key = selectedItemsSessionKey(name)
    if (!key || typeof window === 'undefined') return

    window.sessionStorage.setItem(key, JSON.stringify({
        available: availableIds,
        selected: selectedIds,
    }))
}

function initialSelectedItemIds(name, availableIds) {
    const key = selectedItemsSessionKey(name)
    if (!key || typeof window === 'undefined') return availableIds

    let stored
    try {
        stored = JSON.parse(window.sessionStorage.getItem(key))
    } catch {
        stored = null
    }

    const valid = stored !== null
        && Array.isArray(stored.available)
        && Array.isArray(stored.selected)
        && stored.available.every(Number.isInteger)
        && stored.selected.every((id) => Number.isInteger(id) && stored.available.includes(id))

    if (!valid) {
        storeSelectedItems(name, availableIds, availableIds)
        return availableIds
    }

    const selectedIds = availableIds.filter((id) =>
        stored.selected.includes(id) || !stored.available.includes(id),
    )
    storeSelectedItems(name, availableIds, selectedIds)
    return selectedIds
}

const availableWorkcenterIds = () => props.workcenters.map((workcenter) => workcenter.id)
const availableShiftIds = () => props.shifts.map((shift) => shift.id)

const checkedWorkcenterIds = ref(initialSelectedItemIds('selectedWorkcenters', availableWorkcenterIds()))
const checkedShiftIds = ref(initialSelectedItemIds('selectedShifts', availableShiftIds()))

function toggleWorkcenter(id, checked) {
    const selectedIds = checked
        ? [...checkedWorkcenterIds.value, id]
        : checkedWorkcenterIds.value.filter((x) => x !== id)
    checkedWorkcenterIds.value = selectedIds
    storeSelectedItems('selectedWorkcenters', availableWorkcenterIds(), selectedIds)
}

function toggleShift(id, checked) {
    const selectedIds = checked
        ? [...checkedShiftIds.value, id]
        : checkedShiftIds.value.filter((x) => x !== id)
    checkedShiftIds.value = selectedIds
    storeSelectedItems('selectedShifts', availableShiftIds(), selectedIds)
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

// Whether the next Generate run may fill this published week's open spots.
function isPlannerOpen(workcenterId, weekStart) {
    return props.publishedWorkcenterWeeks.some(
        (p) => p.workcenter_id === workcenterId && p.week_start === weekStart && p.planner_open,
    )
}

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

function selectedWeekSessionKey() {
    const userId = page.props.auth?.user?.id
    return userId ? `planning.selectedWeek.${userId}` : null
}

function isValidIsoDate(value) {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) return false
    const [year, month, day] = value.split('-').map(Number)
    const date = new Date(year, month - 1, day)
    return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day
}

function storeSelectedWeek(date) {
    const key = selectedWeekSessionKey()
    if (key) window.sessionStorage.setItem(key, mondayOf(date))
}

onMounted(() => {
    const key = selectedWeekSessionKey()
    if (!key) return

    const hasExplicitDate = new URL(page.url, window.location.origin).searchParams.has('date')
    if (hasExplicitDate) {
        window.sessionStorage.setItem(key, props.weekStart)
        return
    }

    const storedWeek = window.sessionStorage.getItem(key)
    if (!isValidIsoDate(storedWeek)) {
        window.sessionStorage.setItem(key, props.weekStart)
        return
    }

    if (storedWeek === props.weekStart) return

    const [year, month] = storedWeek.split('-').map(Number)
    router.get('/planning', { year, month, date: storedWeek }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
})

function onCalendarChange({ year, month, day }) {
    const date = dateStr(year, month, day)
    if (year !== props.year || month !== props.month || date !== props.date) {
        storeSelectedWeek(date)
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

                    <Card class="w-full sm:w-auto">
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
                                <span class="whitespace-nowrap">{{ shift.name }}</span>
                            </CheckboxInput>
                        </div>
                    </Card>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <template v-if="planningPeriod">
                    <ButtonSecondary
                        type="button"
                        data-testid="generate-plan-button"
                        :disabled="isGenerationActive(generationStatus)"
                        @click="generateDialogOpen = true"
                    >
                        {{ generateLabel }}
                    </ButtonSecondary>
                </template>
                <ButtonPrimary
                    type="button"
                    icon="envelope"
                    data-testid="send-plan-button"
                    :disabled="uninformedCount === 0"
                    @click="sendDialogOpen = true"
                >
                    {{ __('planning.send') }}
                </ButtonPrimary>
                <ButtonSecondary
                    type="button"
                    icon="check-circle"
                    data-testid="verify-plan-button"
                    @click="startVerification"
                >
                    {{ __('planning.verify') }}
                </ButtonSecondary>
                <span
                    v-if="verifyResult"
                    data-testid="verify-result"
                    class="text-sm"
                    :class="Object.keys(violations).length ? 'text-(--color-badge-error-text)' : 'text-(--color-text-secondary)'"
                >
                    {{ verifyResult }}
                </span>
                <span
                    v-if="generationErrorMessage"
                    data-testid="generation-error"
                    class="text-sm text-(--color-badge-error-text)"
                >
                    {{ generationErrorMessage }}
                </span>
            </div>

            <ConfirmDialog
                :open="generateDialogOpen"
                :title="__('planning.generate_dialog.title')"
                :confirm-label="__('planning.generate')"
                variant="primary"
                @confirm="confirmGenerate"
                @cancel="generateDialogOpen = false"
            >
                <p>{{ __('planning.generate_dialog.body') }}</p>
                <p class="mt-2 font-medium text-(--color-text-primary)">
                    {{ __('planning.generate_dialog.period', { range: periodRangeLabel }) }}
                </p>
            </ConfirmDialog>

            <ConfirmDialog
                :open="sendDialogOpen"
                :title="__('planning.send_dialog.title')"
                :confirm-label="__('planning.send')"
                variant="primary"
                @confirm="confirmSend"
                @cancel="sendDialogOpen = false"
            >
                <p>{{ __('planning.send_dialog.body') }}</p>
                <p class="mt-2 font-medium text-(--color-text-primary)">
                    {{ __('planning.send_dialog.count', { count: uninformedCount }) }}
                </p>
            </ConfirmDialog>

            <GenerationChangeSummary
                v-if="generationRun?.status === 'done' && generationRun.changes.length"
                :key="generationRun.id"
                :changes="generationRun.changes"
                class="mt-4"
            />

            <div v-if="visibleWorkcenters.length" class="mt-4 flex flex-col gap-4">
                <WorkcenterScheduleCard
                    v-for="{ workcenter, schedule } in visibleWorkcenters"
                    :key="workcenter.id"
                    :workcenter="workcenter"
                    :schedule="schedule"
                    :week-start="weekStart"
                    :published="isWorkcenterWeekPublished(workcenter.id, weekStart)"
                    :planner-open="isPlannerOpen(workcenter.id, weekStart)"
                    :unfulfilled="generationRun?.status === 'done' ? generationRun.unfulfilled : []"
                    :violations="violations ?? {}"
                />
            </div>
        </div>
    </AppLayout>
</template>
