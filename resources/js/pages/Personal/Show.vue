<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import Tabs from '@/components/ui/Tabs.vue'
import EmployeeFields from '@/components/EmployeeFields.vue'
import WeeklyHoursField from '@/components/WeeklyHoursField.vue'
import AvailabilityGrid from '@/components/AvailabilityGrid.vue'
import ShiftNote from '@/components/ShiftNote.vue'
import HolidayList from '@/components/HolidayList.vue'
import QuestionChecklist from '@/components/QuestionChecklist.vue'
import TagChecklist from '@/components/TagChecklist.vue'
import PlannedShiftsList from '@/components/PlannedShiftsList.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useI18n } from '@/composables/useI18n'
import { useSaveRegistry } from '@/composables/useSaveRegistry'
import { useUnsavedChangesGuard } from '@/composables/useUnsavedChangesGuard'
import { putAsync, postAsync, deleteAsync } from '@/utils/inertiaAsync'
import { calculateAvailabilityHours } from '@/utils/availabilityHours'

const __ = useI18n()

const props = defineProps({
    token: { type: String, required: true },
    employee: { type: Object, required: true },
    businessLines: { type: Array, default: () => [] },
    holidays: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    weeklyHoursMinimum: { type: Number, default: 20 },
    shiftNoteHtml: { type: String, default: null },
    scheduleNoteHtml: { type: String, default: null },
    availability: { type: Array, default: () => [] },
    competences: { type: Array, default: () => [] },
    competenceIds: { type: Array, default: () => [] },
    questions: { type: Array, default: () => [] },
    questionAnswers: { type: Array, default: () => [] },
    // [{ weekStart, weekEnd, published, assignments }] — published weeks only.
    plannedShifts: { type: Array, default: () => [] },
    // False when a manager has closed employee changes: the page stays
    // visible but every control is read-only.
    editable: { type: Boolean, default: true },
})

const registry = useSaveRegistry()
useUnsavedChangesGuard(() => registry.anyDirty.value)

const form = useForm({
    first_name: props.employee.first_name,
    last_name: props.employee.last_name,
    email: props.employee.email,
    weekly_hours: props.employee.weekly_hours,
    business_line_id: props.employee.business_line_id,
})

const tab = ref('information')
const tabs = computed(() => [
    { value: 'information', label: __('availability.tab.information') },
    { value: 'details', label: __('availability.tab.details'), hasError: registry.hasError('personal') },
    {
        value: 'availability',
        label: __('availability.tab.availability'),
        hasError: registry.hasError('personal') || registry.hasError('availability')
            || registry.hasError('holidays') || registry.hasError('questions'),
    },
    { value: 'competences', label: __('competences.tab'), hasError: registry.hasError('competences') },
    { value: 'planning', label: __('planning.tab') },
])

// ── Details + weekly hours: one backend resource (PUT /personal/{token}) ──
registry.register('personal', {
    isDirty: () => form.isDirty,
    save: () => new Promise((resolve) => {
        form
            .transform((data) => ({ weekly_hours: data.weekly_hours, business_line_id: data.business_line_id }))
            .put(`/personal/${props.token}`, {
                preserveScroll: true,
                preserveState: true,
                async: true,
                onSuccess: () => {
                    form.defaults()
                    resolve(true)
                },
                onError: () => resolve(false),
            })
    }),
})

function onWeeklyHoursChange(value) {
    form.weekly_hours = value
}

// ── Availability grid: one PUT per changed cell ──────────────────────────
const availabilityVersion = ref(0)
// The last-saved rows — the Cancel reset target. Not just props.availability,
// since a save earlier this visit may have moved the true baseline forward.
const committedAvailability = ref(props.availability)
const availability = ref(props.availability)
const pendingAvailability = reactive({})

function committedAvailabilityLevel(weekday, shiftId) {
    const row = committedAvailability.value.find((r) => r.weekday === weekday && r.shift_id === shiftId)
    return row ? row.level : 'not_set'
}

function onAvailabilityChange({ weekday, shiftId, level }) {
    availability.value = availability.value.filter((row) => row.weekday !== weekday || row.shift_id !== shiftId)
    if (level !== 'not_set') {
        availability.value.push({ weekday, shift_id: shiftId, level })
    }

    const key = `${weekday}-${shiftId}`
    if (level === committedAvailabilityLevel(weekday, shiftId)) {
        delete pendingAvailability[key]
    } else {
        pendingAvailability[key] = level
    }
}

registry.register('availability', {
    isDirty: () => Object.keys(pendingAvailability).length > 0,
    save: async () => {
        const entries = Object.entries(pendingAvailability)
        const results = await Promise.allSettled(entries.map(([key, level]) => {
            const [weekday, shiftId] = key.split('-')
            return putAsync(`/personal/${props.token}/availability/${weekday}/${shiftId}`, { level })
                .then(() => {
                    delete pendingAvailability[key]
                    committedAvailability.value = committedAvailability.value
                        .filter((row) => row.weekday !== Number(weekday) || row.shift_id !== Number(shiftId))
                    if (level !== 'not_set') {
                        committedAvailability.value = [
                            ...committedAvailability.value,
                            { weekday: Number(weekday), shift_id: Number(shiftId), level },
                        ]
                    }
                })
        }))
        return results.every((r) => r.status === 'fulfilled')
    },
})

const availabilityHours = computed(() => calculateAvailabilityHours(props.shifts, availability.value))

const availabilityWarning = computed(() => {
    if (!form.weekly_hours || availabilityHours.value.preferred >= form.weekly_hours) return null

    return availabilityHours.value.available >= form.weekly_hours ? 'not_preferred' : 'insufficient'
})

// ── Holidays: POST is not idempotent, so a failed save leaves the whole
// resource dirty rather than retrying only the still-pending items — a
// partial retry could double-create an already-saved holiday. ──────────
const holidaysVersion = ref(0)
const committedHolidays = ref(props.holidays)
const currentHolidayRows = ref(props.holidays)

function onHolidaysChange(rows) {
    currentHolidayRows.value = rows
}

registry.register('holidays', {
    isDirty: () => {
        const savedIds = committedHolidays.value.map((h) => h.id)
        return currentHolidayRows.value.some((r) => r.id === null)
            || savedIds.some((id) => !currentHolidayRows.value.some((r) => r.id === id))
    },
    save: async () => {
        const toAdd = currentHolidayRows.value.filter((r) => r.id === null)
        const savedIds = committedHolidays.value.map((h) => h.id)
        const toDeleteIds = savedIds.filter((id) => !currentHolidayRows.value.some((r) => r.id === id))

        const results = await Promise.allSettled([
            ...toAdd.map((r) => postAsync(`/personal/${props.token}/holidays`, {
                start_date: r.start_date,
                end_date: r.end_date,
                note: r.note,
            })),
            ...toDeleteIds.map((id) => deleteAsync(`/personal/${props.token}/holidays/${id}`)),
        ])

        const ok = results.every((r) => r.status === 'fulfilled')
        if (ok) {
            committedHolidays.value = props.holidays
            currentHolidayRows.value = props.holidays
            holidaysVersion.value++
        }
        return ok
    },
})

// ── Questions and competences: id-set toggles, both idempotent to retry ──
const questionsVersion = ref(0)
const pendingAnsweredIds = ref([...props.questionAnswers])
const savedAnsweredIds = ref([...props.questionAnswers])

function onAnsweredIdsChange(ids) {
    pendingAnsweredIds.value = ids
}

registry.register('questions', {
    isDirty: () => {
        const before = new Set(savedAnsweredIds.value)
        const after = new Set(pendingAnsweredIds.value)
        return before.size !== after.size || [...after].some((id) => !before.has(id))
    },
    save: async () => {
        const before = new Set(savedAnsweredIds.value)
        const after = new Set(pendingAnsweredIds.value)
        const toAttach = [...after].filter((id) => !before.has(id))
        const toDetach = [...before].filter((id) => !after.has(id))

        const results = await Promise.allSettled([
            ...toAttach.map((id) => putAsync(`/personal/${props.token}/questions/${id}`, { answer: true })
                .then(() => { savedAnsweredIds.value = [...savedAnsweredIds.value, id] })),
            ...toDetach.map((id) => putAsync(`/personal/${props.token}/questions/${id}`, { answer: false })
                .then(() => { savedAnsweredIds.value = savedAnsweredIds.value.filter((x) => x !== id) })),
        ])
        return results.every((r) => r.status === 'fulfilled')
    },
})

const competencesVersion = ref(0)
const pendingCompetenceIds = ref([...props.competenceIds])
const savedCompetenceIds = ref([...props.competenceIds])
const editableCompetences = computed(() => props.competences.filter((competence) => !competence.read_only))
const readOnlyCompetences = computed(() => props.competences.filter((competence) => competence.read_only))

function onSelectedCompetenceIdsChange(ids, items) {
    const itemIds = new Set(items.map((item) => item.id))
    pendingCompetenceIds.value = [
        ...pendingCompetenceIds.value.filter((id) => !itemIds.has(id)),
        ...ids,
    ]
}

registry.register('competences', {
    isDirty: () => {
        const before = new Set(savedCompetenceIds.value)
        const after = new Set(pendingCompetenceIds.value)
        return before.size !== after.size || [...after].some((id) => !before.has(id))
    },
    save: async () => {
        const before = new Set(savedCompetenceIds.value)
        const after = new Set(pendingCompetenceIds.value)
        const toAttach = [...after].filter((id) => !before.has(id))
        const toDetach = [...before].filter((id) => !after.has(id))

        const results = await Promise.allSettled([
            ...toAttach.map((id) => putAsync(`/personal/${props.token}/competences/${id}`, {})
                .then(() => { savedCompetenceIds.value = [...savedCompetenceIds.value, id] })),
            ...toDetach.map((id) => deleteAsync(`/personal/${props.token}/competences/${id}`)
                .then(() => { savedCompetenceIds.value = savedCompetenceIds.value.filter((x) => x !== id) })),
        ])
        return results.every((r) => r.status === 'fulfilled')
    },
})

// ── Save / Cancel ─────────────────────────────────────────────────────
const justSaved = ref(false)

async function onSaveClick() {
    const ok = await registry.saveAll()
    if (ok) {
        justSaved.value = true
        setTimeout(() => { justSaved.value = false }, 2000)
    }
}

// Discards every pending edit across every tab, back to the last-saved
// state (not necessarily the state the page loaded with, if something
// already saved successfully earlier this visit). Bumping each :key
// forces that child to re-seed from the restored data.
function onCancelClick() {
    form.reset()
    form.clearErrors()

    for (const key of Object.keys(pendingAvailability)) delete pendingAvailability[key]
    availability.value = committedAvailability.value
    availabilityVersion.value++

    currentHolidayRows.value = committedHolidays.value
    holidaysVersion.value++

    pendingAnsweredIds.value = [...savedAnsweredIds.value]
    questionsVersion.value++

    pendingCompetenceIds.value = [...savedCompetenceIds.value]
    competencesVersion.value++
}

// ── Withdraw: self-service, permanent account deletion ─────────────────
const withdrawDialogOpen = ref(false)

function onWithdrawConfirm() {
    router.delete(`/personal/${props.token}`)
}
</script>

<template>
    <CenteredLayout align="top" width="lg">
        <Head :title="__('personal.title')" />

        <template #header>
            <Tabs v-model="tab" :tabs="tabs" />
        </template>

        <p
            v-if="!editable"
            data-testid="locked-notice"
            class="mb-5 rounded-md border border-(--color-badge-warning-border) bg-(--color-badge-warning-bg) px-3 py-2 text-sm text-(--color-badge-warning-text)"
        >
            {{ __('personal.locked_notice') }}
        </p>

        <div v-show="tab === 'information'" data-testid="panel-information">
            <ShiftNote v-if="shiftNoteHtml" :html="shiftNoteHtml" class="mb-6" />
            <p v-else class="mb-6 text-sm text-(--color-text-secondary)">
                {{ __('availability.info.empty') }}
            </p>

            <p class="text-sm text-(--color-text-secondary)">{{ __('availability.info.cta') }}</p>
        </div>

        <div v-show="tab === 'details'" data-testid="panel-details">
            <EmployeeFields :form="form" :business-lines="businessLines" readonly-identity :disabled="!editable" />
        </div>

        <div v-show="tab === 'availability'" data-testid="panel-availability">
            <section class="mb-6">
                <WeeklyHoursField
                    :model-value="form.weekly_hours"
                    :minimum="weeklyHoursMinimum"
                    :error="form.errors.weekly_hours"
                    :disabled="!editable"
                    @update:model-value="onWeeklyHoursChange"
                />
            </section>

            <p
                v-if="availabilityWarning"
                data-testid="availability-hours-warning"
                class="mb-6 rounded-md border border-(--color-badge-warning-border) bg-(--color-badge-warning-bg) px-3 py-2 text-sm text-(--color-badge-warning-text)"
            >
                <template v-if="availabilityWarning === 'not_preferred'">
                    {{ __('availability.hours_warning.not_preferred') }}
                </template>
                <template v-else>
                    {{ __('availability.hours_warning.insufficient', {
                        available: availabilityHours.available,
                        target: form.weekly_hours,
                    }) }}
                </template>
            </p>

            <CardSeparator />

            <section class="space-y-3">
                <AvailabilityGrid
                    :key="availabilityVersion"
                    :shifts="shifts"
                    :availability="committedAvailability"
                    :disabled="!editable"
                    @update:availability="onAvailabilityChange"
                />
                <ShiftNote v-if="scheduleNoteHtml" :html="scheduleNoteHtml" />
            </section>

            <template v-if="questions.length">
                <CardSeparator />

                <section class="space-y-3">
                    <h3 class="text-sm font-semibold text-(--color-text-primary)">
                        {{ __('availability.questions.heading') }}
                    </h3>
                    <QuestionChecklist
                        :key="questionsVersion"
                        :items="questions"
                        :answered-ids="savedAnsweredIds"
                        :disabled="!editable"
                        @update:answered-ids="onAnsweredIdsChange"
                    />
                </section>
            </template>

            <CardSeparator />

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-(--color-text-primary)">
                    {{ __('availability.holidays.heading') }}
                </h3>
                <HolidayList
                    :key="holidaysVersion"
                    :holidays="committedHolidays"
                    :disabled="!editable"
                    @update:holidays="onHolidaysChange"
                />
            </section>
        </div>

        <div v-show="tab === 'competences'" data-testid="panel-competences">
            <TagChecklist
                :key="competencesVersion"
                :items="editableCompetences"
                :selected-ids="savedCompetenceIds.filter((id) => editableCompetences.some((item) => item.id === id))"
                empty-key="competences.checklist_empty"
                :disabled="!editable"
                @update:selected-ids="onSelectedCompetenceIdsChange($event, editableCompetences)"
            />
            <template v-if="readOnlyCompetences.length">
                <CardSeparator />
                <TagChecklist
                    :items="readOnlyCompetences"
                    :selected-ids="savedCompetenceIds.filter((id) => readOnlyCompetences.some((item) => item.id === id))"
                    empty-key="competences.checklist_empty"
                    disabled
                />
            </template>
        </div>

        <div v-show="tab === 'planning'" data-testid="panel-planning">
            <PlannedShiftsList :weeks="plannedShifts" />
        </div>

        <template v-if="editable">
            <CardSeparator />

            <div class="flex items-center justify-between gap-3">
                <ButtonDanger type="button" @click="withdrawDialogOpen = true">
                    {{ __('personal.action.withdraw') }}
                </ButtonDanger>

                <div class="flex items-center gap-3">
                    <ButtonSecondary
                        type="button"
                        :disabled="!registry.anyDirty.value || registry.saving.value"
                        @click="onCancelClick"
                    >
                        {{ __('personal.action.cancel') }}
                    </ButtonSecondary>
                    <ButtonPrimary
                        :disabled="!registry.anyDirty.value || registry.saving.value"
                        :icon="justSaved ? 'check-circle' : null"
                        @click="onSaveClick"
                    >
                        {{
                            registry.saving.value
                                ? __('personal.action.saving')
                                : justSaved
                                  ? __('personal.saved')
                                  : __('personal.action.save')
                        }}
                    </ButtonPrimary>
                </div>
            </div>
        </template>

        <ConfirmDialog
            :open="withdrawDialogOpen"
            :title="__('personal.withdraw.title')"
            :confirm-label="__('personal.withdraw.confirm')"
            variant="danger"
            @confirm="onWithdrawConfirm"
            @cancel="withdrawDialogOpen = false"
        >
            {{ __('personal.withdraw.body') }}
        </ConfirmDialog>
    </CenteredLayout>
</template>
