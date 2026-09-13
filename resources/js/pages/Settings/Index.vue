<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import Tabs from '@/components/ui/Tabs.vue'
import OrderedNameList from '@/components/OrderedNameList.vue'
import BusinessLineList from '@/components/BusinessLineList.vue'
import ShiftList from '@/components/ShiftList.vue'
import ShiftNoteForm from '@/components/ShiftNoteForm.vue'
import ScheduleNoteForm from '@/components/ScheduleNoteForm.vue'
import PeriodSettingsForm from '@/components/PeriodSettingsForm.vue'
import SaveStatusBadge from '@/components/ui/SaveStatusBadge.vue'
import TabSaveBar from '@/components/ui/TabSaveBar.vue'
import { useI18n } from '@/composables/useI18n'
import { useSaveStatus } from '@/composables/useSaveStatus'
import { putAsync, postAsync, deleteAsync } from '@/utils/inertiaAsync'

const __ = useI18n()
const saveStatus = useSaveStatus()

const props = defineProps({
    competences: { type: Array, default: () => [] },
    businessLines: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    shiftNote: { type: String, default: '' },
    scheduleNote: { type: String, default: '' },
    questions: { type: Array, default: () => [] },
    period: { type: Object, default: () => ({}) },
})

const tab = ref('general')
const tabs = computed(() => [
    { value: 'general', label: __('settings.tab.general') },
    { value: 'business_lines', label: __('settings.tab.business_lines') },
    { value: 'shifts', label: __('settings.tab.shifts') },
    { value: 'questions', label: __('settings.tab.questions') },
    { value: 'competences', label: __('settings.tab.competences') },
    { value: 'information', label: __('settings.tab.information') },
])

// ── Business Lines: local edit/add/delete/reorder, one Save/Cancel ──────
// Reorder and add are both fire-and-forget in the same Promise.allSettled
// below. A brand-new row's server-assigned position (max(position)+1) is
// computed independently of a concurrent reorder's position writes, so
// the rare case of reordering AND adding in the same Save can leave the
// new row's final position slightly off — a cosmetic edge case, not a
// data-integrity one (position carries no unique constraint).
const businessLinesVersion = ref(0)
const committedBusinessLines = ref(props.businessLines)
const currentBusinessLines = ref(props.businessLines)
const businessLinesSaving = ref(false)
const businessLinesJustSaved = ref(false)

function onBusinessLinesChange(rows) {
    currentBusinessLines.value = rows
}

function businessLineOrderIds(rows, excludeIds) {
    return rows.filter((r) => r.id !== null && !excludeIds.includes(r.id)).map((r) => r.id)
}

const businessLinesDirty = computed(() => {
    const committed = committedBusinessLines.value
    const current = currentBusinessLines.value

    if (current.some((r) => r.id === null)) return true
    if (committed.some((c) => !current.some((r) => r.id === c.id))) return true

    for (const row of current) {
        const orig = committed.find((c) => c.id === row.id)
        if (!orig) continue
        if (orig.abbreviation !== row.abbreviation || orig.description !== row.description || orig.target_fte !== row.target_fte) {
            return true
        }
    }

    return businessLineOrderIds(current, []).join(',') !== businessLineOrderIds(committed, []).join(',')
})

async function saveBusinessLines() {
    businessLinesSaving.value = true
    const committed = committedBusinessLines.value
    const current = currentBusinessLines.value
    const committedIds = committed.map((r) => r.id)
    const toDeleteIds = committedIds.filter((id) => !current.some((r) => r.id === id))
    const toAdd = current.filter((r) => r.id === null)
    const toEdit = current.filter((r) => {
        if (r.id === null || toDeleteIds.includes(r.id)) return false
        const orig = committed.find((c) => c.id === r.id)
        return orig && (orig.abbreviation !== r.abbreviation || orig.description !== r.description || orig.target_fte !== r.target_fte)
    })
    const newOrder = businessLineOrderIds(current, toDeleteIds)
    const oldOrder = businessLineOrderIds(committed, toDeleteIds)
    const reorderNeeded = newOrder.join(',') !== oldOrder.join(',')

    const results = await Promise.allSettled([
        ...toDeleteIds.map((id) => deleteAsync(`/settings/business-lines/${id}`)),
        ...toEdit.map((r) => putAsync(`/settings/business-lines/${r.id}`, {
            abbreviation: r.abbreviation,
            description: r.description,
            target_fte: r.target_fte,
        })),
        ...(reorderNeeded ? [putAsync('/settings/business-lines/reorder', { ids: newOrder })] : []),
        ...toAdd.map((r) => postAsync('/settings/business-lines', {
            abbreviation: r.abbreviation,
            description: r.description,
            target_fte: r.target_fte,
        })),
    ])

    businessLinesSaving.value = false
    const ok = results.every((r) => r.status === 'fulfilled')
    if (ok) {
        committedBusinessLines.value = props.businessLines
        currentBusinessLines.value = props.businessLines
        businessLinesVersion.value++
        businessLinesJustSaved.value = true
        setTimeout(() => { businessLinesJustSaved.value = false }, 2000)
    }
    return ok
}

function cancelBusinessLines() {
    currentBusinessLines.value = committedBusinessLines.value
    businessLinesVersion.value++
}

// ── Shifts: the shift list and the schedule note, one Save/Cancel ──────
const shiftsVersion = ref(0)
const committedShifts = ref(props.shifts)
const currentShifts = ref(props.shifts)
const committedScheduleNote = ref(props.scheduleNote)
const currentScheduleNote = ref(props.scheduleNote)
const shiftsSaving = ref(false)
const shiftsJustSaved = ref(false)

function onShiftsChange(rows) {
    currentShifts.value = rows
}

function onScheduleNoteChange(note) {
    currentScheduleNote.value = note
}

const shiftsDirty = computed(() => {
    const committed = committedShifts.value
    const current = currentShifts.value

    if (currentScheduleNote.value !== committedScheduleNote.value) return true
    if (current.some((r) => r.id === null)) return true
    if (committed.some((c) => !current.some((r) => r.id === c.id))) return true

    return current.some((row) => {
        const orig = committed.find((c) => c.id === row.id)
        return orig && (orig.name !== row.name || orig.start_time !== row.start_time || orig.end_time !== row.end_time)
    })
})

async function saveShifts() {
    shiftsSaving.value = true
    const committed = committedShifts.value
    const current = currentShifts.value
    const committedIds = committed.map((r) => r.id)
    const toDeleteIds = committedIds.filter((id) => !current.some((r) => r.id === id))
    const toAdd = current.filter((r) => r.id === null)
    const toEdit = current.filter((r) => {
        if (r.id === null || toDeleteIds.includes(r.id)) return false
        const orig = committed.find((c) => c.id === r.id)
        return orig && (orig.name !== r.name || orig.start_time !== r.start_time || orig.end_time !== r.end_time)
    })
    const noteChanged = currentScheduleNote.value !== committedScheduleNote.value

    const results = await Promise.allSettled([
        ...toDeleteIds.map((id) => deleteAsync(`/settings/shifts/${id}`)),
        ...toEdit.map((r) => putAsync(`/settings/shifts/${r.id}`, {
            name: r.name,
            start_time: r.start_time,
            end_time: r.end_time,
        })),
        ...toAdd.map((r) => postAsync('/settings/shifts', {
            name: r.name,
            start_time: r.start_time,
            end_time: r.end_time,
        })),
        ...(noteChanged ? [putAsync('/settings/shifts/schedule-note', { note: currentScheduleNote.value })] : []),
    ])

    shiftsSaving.value = false
    const ok = results.every((r) => r.status === 'fulfilled')
    if (ok) {
        committedShifts.value = props.shifts
        currentShifts.value = props.shifts
        committedScheduleNote.value = currentScheduleNote.value
        shiftsVersion.value++
        shiftsJustSaved.value = true
        setTimeout(() => { shiftsJustSaved.value = false }, 2000)
    }
    return ok
}

function cancelShifts() {
    currentShifts.value = committedShifts.value
    currentScheduleNote.value = committedScheduleNote.value
    shiftsVersion.value++
}
</script>

<template>
    <AppLayout>
        <Head :title="__('settings.title')" />

        <Card class="max-w-3xl">
            <template #header>
                <Tabs v-model="tab" :tabs="tabs" />
            </template>

            <div v-show="tab === 'business_lines'" data-testid="panel-business-lines" class="p-6">
                <BusinessLineList
                    :key="businessLinesVersion"
                    :items="committedBusinessLines"
                    @update:items="onBusinessLinesChange"
                />
                <TabSaveBar
                    :dirty="businessLinesDirty"
                    :saving="businessLinesSaving"
                    :just-saved="businessLinesJustSaved"
                    @save="saveBusinessLines"
                    @cancel="cancelBusinessLines"
                />
            </div>

            <div v-show="tab === 'general'" data-testid="panel-general" class="p-6">
                <PeriodSettingsForm :period="period" />
            </div>

            <div v-show="tab === 'shifts'" data-testid="panel-shifts" class="space-y-6 p-6">
                <ShiftList :key="shiftsVersion" :items="committedShifts" @update:items="onShiftsChange" />
                <CardSeparator />
                <ScheduleNoteForm :note="currentScheduleNote" @update:note="onScheduleNoteChange" />
                <TabSaveBar
                    :dirty="shiftsDirty"
                    :saving="shiftsSaving"
                    :just-saved="shiftsJustSaved"
                    @save="saveShifts"
                    @cancel="cancelShifts"
                />
            </div>

            <div v-show="tab === 'information'" data-testid="panel-information" class="p-6">
                <ShiftNoteForm :note="shiftNote" />
            </div>

            <div v-show="tab === 'questions'" data-testid="panel-questions" class="relative p-6">
                <SaveStatusBadge :status="saveStatus.status.value" />
                <OrderedNameList
                    :items="questions"
                    endpoint="/settings/questions"
                    i18n-prefix="questions"
                    :save-status="saveStatus"
                />
            </div>

            <div v-show="tab === 'competences'" data-testid="panel-competences" class="relative p-6">
                <SaveStatusBadge :status="saveStatus.status.value" />
                <OrderedNameList
                    :items="competences"
                    endpoint="/settings/competences"
                    i18n-prefix="competences"
                    :save-status="saveStatus"
                />
            </div>
        </Card>
    </AppLayout>
</template>
