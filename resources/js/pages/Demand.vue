<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import TabSaveBar from '@/components/ui/TabSaveBar.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import DemandCalendar from '@/components/DemandCalendar.vue'
import DemandDateCard from '@/components/DemandDateCard.vue'
import { SelectInput, NumberInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'
import { useUnsavedChangesGuard } from '@/composables/useUnsavedChangesGuard'
import { putAsync, postAsync, deleteAsync } from '@/utils/inertiaAsync'
import { groupDemandOverrides, groupAssigned, dayDemand } from '@/utils/demandCalendar'
import { isValidIsoDate, todayIso } from '@/utils/date'

const __ = useI18n()
const page = usePage()

const props = defineProps({
    workcenters: { type: Array, default: () => [] }, // { id, name }, active only
    workcenterId: { type: Number, default: null }, // the shown workcenter, null without active workcenters
    shifts: { type: Array, default: () => [] }, // { id, name, start_time, end_time }, all shift definitions
    defaults: { type: Array, default: () => [] }, // { shift_id, spots: number[7] }, the shown workcenter only
    overrides: { type: Array, default: () => [] }, // { shift_id, date, spots }
    assigned: { type: Array, default: () => [] }, // { shift_id, date, count }
})

const WEEKDAYS = [
    'demand.weekday.mon',
    'demand.weekday.tue',
    'demand.weekday.wed',
    'demand.weekday.thu',
    'demand.weekday.fri',
    'demand.weekday.sat',
    'demand.weekday.sun',
]

const shiftById = (id) => props.shifts.find((s) => s.id === id)
const shiftLabel = (id) => {
    const shift = shiftById(id)
    return shift ? `${shift.name} (${shift.start_time}–${shift.end_time})` : `#${id}`
}

const workcenterOptions = computed(() => props.workcenters.map((w) => ({ value: w.id, label: w.name })))

// ── Selection: kept per user for this browser tab ─────────────────────
const userId = page.props.auth?.user?.id
const workcenterKey = userId ? `demand.workcenter.${userId}` : null
const dateKey = userId ? `demand.date.${userId}` : null

function readStored(key) {
    try {
        return key ? window.sessionStorage.getItem(key) : null
    } catch {
        return null
    }
}

function writeStored(key, value) {
    try {
        if (key) window.sessionStorage.setItem(key, value)
    } catch {
        // Storage can be unavailable; the selection then simply is not kept.
    }
}

// An empty stored day means "nothing selected"; a missing or invalid one falls back to today.
function initialDate() {
    const stored = readStored(dateKey)
    if (stored === '') return null
    return isValidIsoDate(stored) ? stored : todayIso()
}

onMounted(() => {
    const hasExplicitWorkcenter = new URL(page.url, window.location.origin).searchParams.has('workcenter')
    const stored = Number(readStored(workcenterKey))
    const restorable = !hasExplicitWorkcenter && stored !== props.workcenterId
        && props.workcenters.some((w) => w.id === stored)

    if (restorable) {
        router.get('/demand', { workcenter: stored }, { replace: true })
    } else if (props.workcenterId !== null) {
        writeStored(workcenterKey, String(props.workcenterId))
    }
})

// A switch with unsaved edits waits for Stay or Discard.
const blockedWorkcenterId = ref(null)

function selectWorkcenter(id) {
    if (id === props.workcenterId) return
    if (dirty.value) {
        blockedWorkcenterId.value = id
        return
    }
    router.get('/demand', { workcenter: id })
}

function discardAndSwitch() {
    const id = blockedWorkcenterId.value
    blockedWorkcenterId.value = null
    cancel()
    router.get('/demand', { workcenter: id })
}

// ── Default demand: one row per shift, seven weekday slot counts ──────
function seed() {
    return props.defaults.map((d) => ({ shift_id: d.shift_id, spots: [...d.spots] }))
}

const committed = ref(seed())
const rows = ref(seed())
const saving = ref(false)
const justSaved = ref(false)

const draftShiftId = ref(null)

// Only shifts the workcenter does not have yet can be added.
const shiftOptions = computed(() => props.shifts
    .filter((s) => !rows.value.some((r) => r.shift_id === s.id))
    .map((s) => ({ value: s.id, label: shiftLabel(s.id) })))

function add() {
    if (draftShiftId.value === null) return

    rows.value = [...rows.value, { shift_id: draftShiftId.value, spots: [0, 0, 0, 0, 0, 0, 0] }]
    draftShiftId.value = null
}

function remove(row) {
    rows.value = rows.value.filter((r) => r !== row)
}

const sameSpots = (a, b) => JSON.stringify(a.spots) === JSON.stringify(b.spots)

const defaultsDirty = computed(() => {
    const committedIds = committed.value.map((c) => c.shift_id)
    const currentIds = rows.value.map((r) => r.shift_id)

    if (currentIds.some((id) => !committedIds.includes(id))) return true
    if (committedIds.some((id) => !currentIds.includes(id))) return true

    return rows.value.some((row) => !sameSpots(committed.value.find((c) => c.shift_id === row.shift_id), row))
})

// ── Date overrides: pending until Save, { [date]: { [shift_id]: spots } } ──
const seedOverrides = () => groupDemandOverrides(props.overrides)
const committedOverrides = ref(seedOverrides())
const currentOverrides = ref(seedOverrides())
const assigned = computed(() => groupAssigned(props.assigned))
const selectedDate = ref(initialDate())
watch(selectedDate, (date) => writeStored(dateKey, date ?? ''))

// The changed (date, shift) pairs of the shifts the workcenter keeps:
// a removed shift loses its overrides on the server anyway.
const dateChanges = computed(() => {
    const dates = new Set([...Object.keys(committedOverrides.value), ...Object.keys(currentOverrides.value)])
    const changes = []
    for (const date of dates) {
        for (const { shift_id: shiftId } of rows.value) {
            const before = committedOverrides.value[date]?.[shiftId] ?? null
            const after = currentOverrides.value[date]?.[shiftId] ?? null
            if (before !== after) changes.push({ date, shiftId, spots: after })
        }
    }
    return changes
})

const selectedDay = computed(() => selectedDate.value
    ? dayDemand(selectedDate.value, { defaults: rows.value, overrides: currentOverrides.value, assigned: assigned.value })
    : null)

// A value equal to the weekday default is no override.
function setDateSpots({ shiftId, spots }) {
    const date = selectedDate.value
    const day = selectedDay.value.shifts.find((s) => s.shift_id === shiftId)
    const { [shiftId]: _, ...rest } = currentOverrides.value[date] ?? {}
    const next = spots === null || spots === day.defaultSpots ? rest : { ...rest, [shiftId]: spots }
    currentOverrides.value = { ...currentOverrides.value, [date]: next }
}

function resetDate() {
    const { [selectedDate.value]: _, ...rest } = currentOverrides.value
    currentOverrides.value = rest
}

// ── Save / Cancel ─────────────────────────────────────────────────────
const dirty = computed(() => defaultsDirty.value || dateChanges.value.length > 0)

async function save() {
    saving.value = true
    const committedIds = committed.value.map((c) => c.shift_id)
    const currentIds = rows.value.map((r) => r.shift_id)
    const url = (shiftId) => `/demand/${props.workcenterId}/${shiftId}`

    const toDelete = committed.value.filter((c) => !currentIds.includes(c.shift_id))
    const toAdd = rows.value.filter((r) => !committedIds.includes(r.shift_id))
    const toEdit = rows.value.filter((r) => committedIds.includes(r.shift_id)
        && !sameSpots(committed.value.find((c) => c.shift_id === r.shift_id), r))

    const results = await Promise.allSettled([
        ...toDelete.map((r) => deleteAsync(url(r.shift_id))),
        ...toEdit.map((r) => putAsync(url(r.shift_id), { spots: r.spots })),
        ...toAdd.map((r) => postAsync('/demand', {
            workcenter_id: props.workcenterId,
            shift_id: r.shift_id,
            spots: r.spots,
        })),
        ...dateChanges.value.map((c) => (c.spots === null
            ? deleteAsync(`${url(c.shiftId)}/${c.date}`)
            : putAsync(`${url(c.shiftId)}/${c.date}`, { spots: c.spots }))),
    ])

    saving.value = false
    const ok = results.every((r) => r.status === 'fulfilled')
    if (ok) {
        committed.value = seed()
        rows.value = seed()
        committedOverrides.value = seedOverrides()
        currentOverrides.value = seedOverrides()
        justSaved.value = true
        setTimeout(() => { justSaved.value = false }, 2000)
    }
    return ok
}

function cancel() {
    rows.value = committed.value.map((r) => ({ ...r, spots: [...r.spots] }))
    currentOverrides.value = committedOverrides.value
    draftShiftId.value = null
}

useUnsavedChangesGuard(() => dirty.value)
</script>

<template>
    <AppLayout>
        <Head :title="__('demand.title')" />

        <Card class="max-w-5xl" data-testid="demand-card">
            <template #header>
                <div data-testid="demand-card-header" class="flex h-12 items-center px-6 text-md font-semibold">
                    {{ __('demand.title') }}
                </div>
            </template>

            <div class="space-y-6 p-6">
                <section data-testid="demand-workcenter">
                    <LabeledInput v-if="workcenters.length" :label="__('demand.workcenter')">
                        <SelectInput
                            :model-value="workcenterId"
                            :options="workcenterOptions"
                            :placeholder="__('demand.select_workcenter')"
                            class="w-64"
                            @update:model-value="selectWorkcenter"
                        />
                    </LabeledInput>
                    <p v-else class="text-sm text-(--color-text-secondary)">{{ __('demand.no_workcenters') }}</p>
                </section>

                <Card v-if="workcenterId !== null" data-testid="demand-default-card">
                    <template #header>
                        <div class="flex h-12 items-center px-6 text-md font-semibold">
                            {{ __('demand.default.heading') }}
                        </div>
                    </template>

                    <div class="px-6 py-4">
                        <table class="w-full table-fixed text-sm">
                            <thead>
                                <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                                    <th class="py-2 pr-3 font-medium">{{ __('demand.column.shift') }}</th>
                                    <th v-for="label in WEEKDAYS" :key="label" class="w-16 py-2 pr-2 font-medium">
                                        {{ __(label) }}
                                    </th>
                                    <th class="w-14 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in rows"
                                    :key="row.shift_id"
                                    data-testid="demand-default-row"
                                    class="border-b border-(--color-table-row-separator)"
                                >
                                    <td class="py-2 pr-3 align-middle">{{ shiftLabel(row.shift_id) }}</td>
                                    <td v-for="(label, weekday) in WEEKDAYS" :key="label" class="py-2 pr-2 align-middle">
                                        <NumberInput
                                            v-model="row.spots[weekday]"
                                            :min="0"
                                            class="w-full"
                                            :data-testid="`demand-default-spots-${row.shift_id}-${weekday}`"
                                        />
                                    </td>
                                    <td class="px-1 py-2 align-middle">
                                        <ButtonDanger
                                            type="button"
                                            icon="bin"
                                            class="w-full px-0"
                                            :aria-label="__('demand.delete')"
                                            @click="remove(row)"
                                        />
                                    </td>
                                </tr>

                                <tr v-if="!rows.length">
                                    <td :colspan="9" class="py-6 text-center text-(--color-text-secondary)">
                                        {{ __('demand.list_empty') }}
                                    </td>
                                </tr>

                                <tr data-testid="demand-add-row" class="border-t border-(--color-table-row-separator)">
                                    <td class="py-2 pr-3 align-top" :colspan="8">
                                        <SelectInput
                                            v-model="draftShiftId"
                                            :options="shiftOptions"
                                            :placeholder="__('demand.select_shift')"
                                            class="w-64"
                                        />
                                    </td>
                                    <td class="px-1 py-2 text-right align-top">
                                        <ButtonPrimary
                                            type="button"
                                            icon="plus-circle"
                                            class="px-2.5"
                                            :aria-label="__('demand.add')"
                                            @click="add"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- The calendar keeps its content width; the date card takes the rest. -->
                <section v-if="workcenterId !== null" data-testid="demand-calendar-section" class="flex flex-col gap-6 sm:flex-row sm:items-start">
                    <DemandCalendar
                        v-model:selected-date="selectedDate"
                        :defaults="rows"
                        :overrides="currentOverrides"
                        :assigned="assigned"
                    />
                    <DemandDateCard
                        :day="selectedDay"
                        :shift-label="shiftLabel"
                        @set-spots="setDateSpots"
                        @reset="resetDate"
                    />
                </section>

                <TabSaveBar
                    v-if="workcenterId !== null"
                    :dirty="dirty"
                    :saving="saving"
                    :just-saved="justSaved"
                    @save="save"
                    @cancel="cancel"
                />
            </div>
        </Card>

        <ConfirmDialog
            :open="blockedWorkcenterId !== null"
            :title="__('tabs.unsaved.title')"
            :confirm-label="__('tabs.unsaved.discard')"
            :cancel-label="__('tabs.unsaved.stay')"
            variant="danger"
            @confirm="discardAndSwitch"
            @cancel="blockedWorkcenterId = null"
        >
            {{ __('demand.unsaved.body') }}
        </ConfirmDialog>
    </AppLayout>
</template>
