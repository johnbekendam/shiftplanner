<script setup>
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
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
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { useI18n } from '@/composables/useI18n'
import { calculateAvailabilityHours } from '@/utils/availabilityHours'

const __ = useI18n()

const props = defineProps({
    token: { type: String, required: true },
    employee: { type: Object, required: true },
    businessLines: { type: Array, default: () => [] },
    holidays: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    shiftNoteHtml: { type: String, default: null },
    availability: { type: Array, default: () => [] },
    competences: { type: Array, default: () => [] },
    competenceIds: { type: Array, default: () => [] },
    questions: { type: Array, default: () => [] },
    questionAnswers: { type: Array, default: () => [] },
    // False when a manager has closed employee changes: the page stays
    // visible but every control is read-only.
    editable: { type: Boolean, default: true },
})

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
    if (!props.editable) return
    form
        .transform((data) => ({
            weekly_hours: data.weekly_hours,
            business_line_id: data.business_line_id,
        }))
        .put(`/personal/${props.token}`, { preserveScroll: true, preserveState: true })
}

// Weekly hours lives on the Availability tab and auto-saves on change.
function onWeeklyHoursChange(value) {
    form.weekly_hours = value
    save()
}

function onAvailabilityChange({ weekday, shiftId, level }) {
    availability.value = availability.value.filter((row) => row.weekday !== weekday || row.shift_id !== shiftId)

    if (level !== 'available') {
        availability.value.push({ weekday, shift_id: shiftId, level })
    }
}

// The business line is the only editable Details field. Auto-save it on
// change, and flush a dirty Details form when the user leaves the tab.
watch(() => form.business_line_id, () => save())

watch(tab, (next, prev) => {
    if (prev === 'details' && form.isDirty) save()
})
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
            <form class="space-y-5" @submit.prevent="save">
                <EmployeeFields :form="form" :business-lines="businessLines" readonly-identity :disabled="!editable" />

                <div v-if="editable" class="flex items-center justify-end gap-3">
                    <span v-if="form.recentlySuccessful" class="text-sm text-(--color-badge-success-text)">
                        {{ __('personal.saved') }}
                    </span>
                    <ButtonPrimary type="submit" :disabled="form.processing">
                        {{ __('personal.action.save') }}
                    </ButtonPrimary>
                </div>
            </form>
        </div>

        <div v-show="tab === 'availability'" data-testid="panel-availability">
            <section class="mb-6 max-w-xs">
                <WeeklyHoursField
                    :model-value="form.weekly_hours"
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
                    :shifts="shifts"
                    :availability="availability"
                    :endpoint="`/personal/${token}/availability`"
                    :disabled="!editable"
                    @update:availability="onAvailabilityChange"
                />
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
                        :endpoint="`/personal/${token}/questions`"
                        :disabled="!editable"
                    />
                </section>
            </template>

            <CardSeparator />

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-(--color-text-primary)">
                    {{ __('availability.holidays.heading') }}
                </h3>
                <HolidayList :holidays="holidays" :endpoint="`/personal/${token}/holidays`" :disabled="!editable" />
            </section>
        </div>

        <div v-show="tab === 'competences'" data-testid="panel-competences">
            <TagChecklist
                :items="competences"
                :selected-ids="competenceIds"
                :endpoint="`/personal/${token}/competences`"
                empty-key="competences.checklist_empty"
                :disabled="!editable"
            />
        </div>
    </CenteredLayout>
</template>
