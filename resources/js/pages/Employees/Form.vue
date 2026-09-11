<script setup>
import { computed, ref, watch } from 'vue'
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
import SaveStatusBadge from '@/components/ui/SaveStatusBadge.vue'
import { useI18n } from '@/composables/useI18n'
import { useSaveStatus } from '@/composables/useSaveStatus'
import { calculateAvailabilityHours } from '@/utils/availabilityHours'

const __ = useI18n()
const saveStatus = useSaveStatus()

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
    { value: 'details', label: __('availability.tab.details') },
    { value: 'availability', label: __('availability.tab.availability') },
    { value: 'competences', label: __('competences.tab') },
])

const availability = ref(props.availability)

watch(() => props.availability, (value) => {
    availability.value = value
}, { deep: true })

const availabilityHours = computed(() => calculateAvailabilityHours(props.shifts, availability.value))

const availabilityWarning = computed(() => {
    if (!form.weekly_hours || availabilityHours.value.preferred >= form.weekly_hours) return null

    return availabilityHours.value.available >= form.weekly_hours ? 'not_preferred' : 'insufficient'
})

function save() {
    saveStatus.start()
    form.put(`/employees/${props.employee.id}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => saveStatus.succeed(),
        onError: () => saveStatus.fail(),
    })
}

function submit() {
    if (isEdit.value) {
        save()
    } else {
        form.post('/employees')
    }
}

// Weekly hours lives on the Availability tab and auto-saves on change
// (edit only — on create it rides along with the create submit).
function onWeeklyHoursChange(value) {
    form.weekly_hours = value
    if (isEdit.value) save()
}

function onAvailabilityChange({ weekday, shiftId, level }) {
    availability.value = availability.value.filter((row) => row.weekday !== weekday || row.shift_id !== shiftId)

    if (level !== 'available') {
        availability.value.push({ weekday, shift_id: shiftId, level })
    }
}

// Auto-save the Details fields on edit. Text inputs emit on commit
// (blur / Enter / Tab) and selects on change, so this fires at the
// right moment without a debounce.
watch(
    () => [form.first_name, form.last_name, form.email, form.business_line_id],
    () => {
        if (isEdit.value) save()
    },
)

// Flush a dirty Details form when the user moves to another tab.
watch(tab, (next, prev) => {
    if (isEdit.value && prev === 'details' && form.isDirty) save()
})
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
                <form class="space-y-5" @submit.prevent="submit">
                    <EmployeeFields :form="form" :business-lines="businessLines" />

                    <div class="flex items-center justify-end gap-3">
                        <Link href="/employees">
                            <ButtonSecondary type="button">{{ __('employees.action.cancel') }}</ButtonSecondary>
                        </Link>
                        <ButtonPrimary
                            type="submit"
                            :disabled="form.processing"
                            :icon="form.recentlySuccessful ? 'check-circle' : null"
                        >
                            {{
                                form.processing
                                    ? __('employees.action.saving')
                                    : form.recentlySuccessful
                                      ? __('employees.action.saved')
                                      : isEdit
                                        ? __('employees.action.save')
                                        : __('employees.action.create')
                            }}
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

            <div v-if="isEdit" v-show="tab === 'availability'" data-testid="panel-availability" class="relative p-6">
                <SaveStatusBadge :status="saveStatus.status.value" />

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
                        :endpoint="`/employees/${employee.id}/availability`"
                        :save-status="saveStatus"
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
                            :endpoint="`/employees/${employee.id}/questions`"
                            :save-status="saveStatus"
                        />
                    </section>
                </template>

                <CardSeparator />

                <section class="space-y-3">
                    <h3 class="text-sm font-semibold text-(--color-text-primary)">
                        {{ __('availability.holidays.heading') }}
                    </h3>
                    <HolidayList
                        :holidays="holidays"
                        :endpoint="`/employees/${employee.id}/holidays`"
                        :save-status="saveStatus"
                    />
                </section>
            </div>

            <div v-if="isEdit" v-show="tab === 'competences'" data-testid="panel-competences" class="relative p-6">
                <SaveStatusBadge :status="saveStatus.status.value" />

                <TagChecklist
                    :items="competences"
                    :selected-ids="competenceIds"
                    :endpoint="`/employees/${employee.id}/competences`"
                    :save-status="saveStatus"
                    empty-key="competences.checklist_empty"
                />
            </div>
        </Card>
    </AppLayout>
</template>
