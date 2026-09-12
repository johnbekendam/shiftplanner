<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import Tabs from '@/components/ui/Tabs.vue'
import EmployeeFields from '@/components/EmployeeFields.vue'
import WeeklyHoursField from '@/components/WeeklyHoursField.vue'
import AvailabilityGrid from '@/components/AvailabilityGrid.vue'
import ShiftNote from '@/components/ShiftNote.vue'
import HolidayList from '@/components/HolidayList.vue'
import QuestionChecklist from '@/components/QuestionChecklist.vue'
import TagChecklist from '@/components/TagChecklist.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
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
    shiftNoteHtml: { type: String, default: null },
    scheduleNoteHtml: { type: String, default: null },
    availability: { type: Array, default: () => [] },
    competences: { type: Array, default: () => [] },
    competenceIds: { type: Array, default: () => [] },
    questions: { type: Array, default: () => [] },
    questionAnswers: { type: Array, default: () => [] },
})

const isEdit = computed(() => props.employee !== null)

const registry = useSaveRegistry()
useUnsavedChangesGuard(() => isEdit.value && registry.anyDirty.value)

const form = useForm({
    first_name: props.employee?.first_name ?? '',
    last_name: props.employee?.last_name ?? '',
    email: props.employee?.email ?? '',
    weekly_hours: props.employee?.weekly_hours ?? 32,
    business_line_id: props.employee?.business_line_id ?? null,
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
const availability = ref(props.availability)
const pendingAvailability = reactive({})

function originalAvailabilityLevel(weekday, shiftId) {
    const row = props.availability.find((r) => r.weekday === weekday && r.shift_id === shiftId)
    return row ? row.level : 'available'
}

function onAvailabilityChange({ weekday, shiftId, level }) {
    availability.value = availability.value.filter((row) => row.weekday !== weekday || row.shift_id !== shiftId)
    if (level !== 'available') {
        availability.value.push({ weekday, shift_id: shiftId, level })
    }

    const key = `${weekday}-${shiftId}`
    if (level === originalAvailabilityLevel(weekday, shiftId)) {
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
                    .then(() => { delete pendingAvailability[key] })
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

const pendingCompetenceIds = ref([...props.competenceIds])
const savedCompetenceIds = ref([...props.competenceIds])

function onSelectedCompetenceIdsChange(ids) {
    pendingCompetenceIds.value = ids
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

// ── Footer Save button (edit mode only) ──────────────────────────────────
const justSaved = ref(false)

async function onSaveClick() {
    const ok = await registry.saveAll()
    if (ok) {
        justSaved.value = true
        setTimeout(() => { justSaved.value = false }, 2000)
    }
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

            <div v-if="isEdit" v-show="tab === 'information'" data-testid="panel-information" class="p-6">
                <ShiftNote v-if="shiftNoteHtml" :html="shiftNoteHtml" class="mb-6" />
                <p v-else class="mb-6 text-sm text-(--color-text-secondary)">
                    {{ __('availability.info.empty') }}
                </p>

                <p class="text-sm text-(--color-text-secondary)">{{ __('availability.info.cta') }}</p>
            </div>

            <div v-if="isEdit" v-show="tab === 'availability'" data-testid="panel-availability" class="p-6">
                <section class="mb-6 max-w-xs">
                    <WeeklyHoursField
                        :model-value="form.weekly_hours"
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
                        :shifts="shifts"
                        :availability="availability"
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
                            :items="questions"
                            :answered-ids="questionAnswers"
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
                    :items="competences"
                    :selected-ids="competenceIds"
                    empty-key="competences.checklist_empty"
                    @update:selected-ids="onSelectedCompetenceIdsChange"
                />
            </div>

            <template v-if="isEdit" #footer>
                <div class="flex justify-end px-6 py-4">
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
            </template>
        </Card>
    </AppLayout>
</template>
