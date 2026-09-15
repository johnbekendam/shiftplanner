<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import Tabs from '@/components/ui/Tabs.vue'
import EmployeeFields from '@/components/EmployeeFields.vue'
import WeeklyHoursField from '@/components/WeeklyHoursField.vue'
import EmployeePlanningSettings from '@/components/EmployeePlanningSettings.vue'
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
    employee: { type: Object, default: null },
    businessLines: { type: Array, default: () => [] },
    holidays: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    weeklyHoursMinimum: { type: Number, default: 20 },
    globalWeeklyHoursMinimum: { type: Number, default: 20 },
    shiftNoteHtml: { type: String, default: null },
    scheduleNoteHtml: { type: String, default: null },
    availability: { type: Array, default: () => [] },
    competences: { type: Array, default: () => [] },
    competenceIds: { type: Array, default: () => [] },
    questions: { type: Array, default: () => [] },
    questionAnswers: { type: Array, default: () => [] },
    // [{ weekStart, weekEnd, published, assignments }] — every assignment, draft included.
    plannedShifts: { type: Array, default: () => [] },
})

const isEdit = computed(() => props.employee !== null)

const registry = useSaveRegistry()
useUnsavedChangesGuard(() => isEdit.value && registry.anyDirty.value)

const form = useForm({
    first_name: props.employee?.first_name ?? '',
    last_name: props.employee?.last_name ?? '',
    email: props.employee?.email ?? '',
    weekly_hours: props.employee?.weekly_hours ?? 0,
    weekly_hours_minimum: props.employee?.weekly_hours_minimum ?? null,
    business_line_id: props.employee?.business_line_id ?? null,
})

const effectiveWeeklyHoursMinimum = computed(() =>
    form.weekly_hours_minimum ?? props.globalWeeklyHoursMinimum,
)

const tab = ref('settings')
const tabs = computed(() => [
    { value: 'settings', label: __('availability.tab.settings'), hasError: registry.hasError('personal') },
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

function submit() {
    if (!isEdit.value) form.post('/employees')
}

if (isEdit.value) {
    // ── Details + weekly hours: one backend resource (PUT /employees/{id}) ──
    registry.register('personal', {
        isDirty: () => form.isDirty,
        save: () => new Promise((resolve) => {
            form.put(`/employees/${props.employee.id}`, {
                preserveScroll: true,
                preserveState: true,
                async: true,
                onSuccess: () => {
                    form.defaults()
                    availabilityVersion.value++
                    resolve(true)
                },
                onError: () => resolve(false),
            })
        }),
    })
}

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

if (isEdit.value) {
    registry.register('availability', {
        isDirty: () => Object.keys(pendingAvailability).length > 0,
        save: async () => {
            const entries = Object.entries(pendingAvailability)
            const results = await Promise.allSettled(entries.map(([key, level]) => {
                const [weekday, shiftId] = key.split('-')
                return putAsync(`/employees/${props.employee.id}/availability/${weekday}/${shiftId}`, { level })
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
}

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

if (isEdit.value) {
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
                ...toAdd.map((r) => postAsync(`/employees/${props.employee.id}/holidays`, {
                    start_date: r.start_date,
                    end_date: r.end_date,
                    note: r.note,
                })),
                ...toDeleteIds.map((id) => deleteAsync(`/employees/${props.employee.id}/holidays/${id}`)),
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
}

// ── Questions and competences: id-set toggles, both idempotent to retry ──
const questionsVersion = ref(0)
const pendingAnsweredIds = ref([...props.questionAnswers])
const savedAnsweredIds = ref([...props.questionAnswers])

function onAnsweredIdsChange(ids) {
    pendingAnsweredIds.value = ids
}

if (isEdit.value) {
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
                ...toAttach.map((id) => putAsync(`/employees/${props.employee.id}/questions/${id}`, { answer: true })
                    .then(() => { savedAnsweredIds.value = [...savedAnsweredIds.value, id] })),
                ...toDetach.map((id) => putAsync(`/employees/${props.employee.id}/questions/${id}`, { answer: false })
                    .then(() => { savedAnsweredIds.value = savedAnsweredIds.value.filter((x) => x !== id) })),
            ])
            return results.every((r) => r.status === 'fulfilled')
        },
    })
}

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

if (isEdit.value) {
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
                ...toAttach.map((id) => putAsync(`/employees/${props.employee.id}/competences/${id}`, {})
                    .then(() => { savedCompetenceIds.value = [...savedCompetenceIds.value, id] })),
                ...toDetach.map((id) => deleteAsync(`/employees/${props.employee.id}/competences/${id}`)
                    .then(() => { savedCompetenceIds.value = savedCompetenceIds.value.filter((x) => x !== id) })),
            ])
            return results.every((r) => r.status === 'fulfilled')
        },
    })
}

// ── Save / Cancel (edit mode only) ────────────────────────────────────
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

// ── Delete: admin/manager counterpart of the employee's own Withdraw ────
const deleteDialogOpen = ref(false)
const employeeName = computed(() => [props.employee?.first_name, props.employee?.last_name].filter(Boolean).join(' '))

function onDeleteConfirm() {
    router.delete(`/employees/${props.employee.id}`)
}
</script>

<template>
    <AppLayout>
        <Head :title="isEdit ? __('employees.form.edit_title') : __('employees.form.create_title')" />

        <Card class="max-w-2xl">
            <template v-if="isEdit" #header>
                <Tabs v-model="tab" :tabs="tabs" />
            </template>

            <div
                v-show="!isEdit || tab === 'details'"
                data-testid="panel-details"
                class="p-6"
            >
                <EmployeeFields v-if="isEdit" :form="form" :business-lines="businessLines" />

                <form v-else class="space-y-5" @submit.prevent="submit">
                    <EmployeeFields :form="form" :business-lines="businessLines" />

                    <div class="flex items-center justify-end gap-3">
                        <Link href="/employees">
                            <ButtonSecondary type="button">{{ __('employees.action.cancel') }}</ButtonSecondary>
                        </Link>
                        <ButtonPrimary type="submit" :disabled="form.processing">
                            {{ form.processing ? __('employees.action.saving') : __('employees.action.create') }}
                        </ButtonPrimary>
                    </div>
                </form>
            </div>

            <div v-if="isEdit" v-show="tab === 'availability'" data-testid="panel-availability" class="p-6">
                <section class="mb-6">
                    <WeeklyHoursField
                        :model-value="form.weekly_hours"
                        :minimum="effectiveWeeklyHoursMinimum"
                        :error="form.errors.weekly_hours"
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
                        show-add-hint
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
                        @update:holidays="onHolidaysChange"
                    />
                </section>
            </div>

            <div v-if="isEdit" v-show="tab === 'competences'" data-testid="panel-competences" class="p-6">
                <TagChecklist
                    :key="competencesVersion"
                    :items="editableCompetences"
                    :selected-ids="savedCompetenceIds.filter((id) => editableCompetences.some((item) => item.id === id))"
                    empty-key="competences.checklist_empty"
                    @update:selected-ids="onSelectedCompetenceIdsChange($event, editableCompetences)"
                />
                <template v-if="readOnlyCompetences.length">
                    <CardSeparator />
                    <TagChecklist
                        :items="readOnlyCompetences"
                        :selected-ids="savedCompetenceIds.filter((id) => readOnlyCompetences.some((item) => item.id === id))"
                        empty-key="competences.checklist_empty"
                        @update:selected-ids="onSelectedCompetenceIdsChange($event, readOnlyCompetences)"
                    />
                </template>
            </div>

            <div v-if="isEdit" v-show="tab === 'settings'" data-testid="panel-settings" class="p-6">
                <EmployeePlanningSettings
                    :form="form"
                    :inherited-minimum="globalWeeklyHoursMinimum"
                />
            </div>

            <div v-if="isEdit" v-show="tab === 'planning'" data-testid="panel-planning" class="p-6">
                <PlannedShiftsList :weeks="plannedShifts" show-published-marker />
            </div>

            <div v-if="isEdit" class="p-6 pt-0">
                <CardSeparator />

                <div class="flex items-center justify-between gap-3">
                    <ButtonDanger type="button" @click="deleteDialogOpen = true">
                        {{ __('employees.action.delete') }}
                    </ButtonDanger>

                    <div class="flex items-center gap-3">
                        <ButtonSecondary
                            type="button"
                            :disabled="!registry.anyDirty.value || registry.saving.value"
                            @click="onCancelClick"
                        >
                            {{ __('employees.action.cancel') }}
                        </ButtonSecondary>
                        <ButtonPrimary
                            :disabled="!registry.anyDirty.value || registry.saving.value"
                            :icon="justSaved ? 'check-circle' : null"
                            @click="onSaveClick"
                        >
                            {{
                                registry.saving.value
                                    ? __('employees.action.saving')
                                    : justSaved
                                      ? __('employees.action.saved')
                                      : __('employees.action.save')
                            }}
                        </ButtonPrimary>
                    </div>
                </div>
            </div>
        </Card>

        <ConfirmDialog
            v-if="isEdit"
            :open="deleteDialogOpen"
            :title="__('employees.delete.title')"
            :confirm-label="__('employees.delete.confirm')"
            variant="danger"
            @confirm="onDeleteConfirm"
            @cancel="deleteDialogOpen = false"
        >
            {{ __('employees.delete.body', { name: employeeName }) }}
        </ConfirmDialog>
    </AppLayout>
</template>
