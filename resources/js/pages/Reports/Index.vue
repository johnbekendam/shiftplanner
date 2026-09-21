<script setup>
import { ref, computed, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import Tabs from '@/components/ui/Tabs.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import NoEmailBadge from '@/components/NoEmailBadge.vue'
import UninformedPlanningReport from '@/components/UninformedPlanningReport.vue'
import { SelectInput, CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    employees: { type: Array, default: () => [] }, // { id, name, business_line, weekly_hours, confirmed }
    shifts: { type: Array, default: () => [] }, // { id, name, start_time, end_time }
    businessLines: { type: Array, default: () => [] }, // { id, abbreviation }
    uninformedPlanning: { type: Array, default: () => [] }, // { id, name, business_line, uninformed_count, first_date }
    competences: { type: Array, default: () => [] }, // { id, name, read_only }
    competenceReport: { type: Array, default: () => [] }, // { id, name, competence_names }
    filters: { type: Object, required: true }, // { shift, business_line, unconfirmed, planning_business_line, competence_mode, competence_id }
})

const tabs = [
    { value: 'competences', label: __('reports.tab.competences') },
    { value: 'uninformed-planning', label: __('reports.tab.uninformed_planning') },
    { value: 'missing-availability', label: __('reports.tab.missing_availability') },
]
const tab = ref('competences')

const shift = ref(props.filters.shift ?? '')
const businessLine = ref(props.filters.business_line ?? '')
const includeUnconfirmed = ref(props.filters.unconfirmed)
const planningBusinessLine = ref(props.filters.planning_business_line ?? '')
const competenceMode = ref(props.filters.competence_mode ?? 'missing')
const competenceId = ref(props.filters.competence_id ?? '')

const shiftOptions = computed(() => [
    { value: '', label: __('reports.missing_availability.shift_placeholder') },
    ...props.shifts.map((s) => ({ value: s.id, label: s.name })),
])
const businessLineOptions = computed(() => [
    { value: '', label: __('reports.missing_availability.business_line_placeholder') },
    ...props.businessLines.map((l) => ({ value: l.id, label: l.abbreviation })),
])
const competenceModeOptions = computed(() => [
    { value: 'missing', label: __('reports.competences.mode.missing') },
    { value: 'has', label: __('reports.competences.mode.has') },
])
const competenceOptions = computed(() => [
    { value: '', label: __('reports.competences.competence_placeholder') },
    ...props.competences.map((competence) => ({ value: competence.id, label: competence.name })),
])

function reload() {
    router.get('/reports', {
        shift: shift.value || undefined,
        business_line: businessLine.value || undefined,
        unconfirmed: includeUnconfirmed.value ? 1 : 0,
        planning_business_line: planningBusinessLine.value || undefined,
        competence_mode: competenceMode.value,
        competence: competenceId.value || undefined,
    }, { preserveState: true })
}

watch([shift, businessLine, includeUnconfirmed, planningBusinessLine, competenceMode, competenceId], reload)

function openEmployeeCompetences(employee) {
    router.visit(`/employees/${employee.id}/edit?tab=competences`)
}

const selectedIds = ref([])
watch(() => props.employees, () => { selectedIds.value = [] })

// Employees without an email cannot receive the message, so they stay unselectable.
const selectableEmployees = computed(() => props.employees.filter((e) => e.has_email !== false))

const allSelected = computed(() =>
    selectableEmployees.value.length > 0 && selectedIds.value.length === selectableEmployees.value.length,
)

function toggleSelectAll(checked) {
    selectedIds.value = checked ? selectableEmployees.value.map((e) => e.id) : []
}

function emailSelected() {
    const query = selectedIds.value.map((id) => `employee_ids[]=${id}`).join('&')
    router.visit(`/mailbox?tab=compose&type=custom&${query}`)
}
</script>

<template>
    <Head :title="__('reports.title')" />
    <AppLayout>
        <Card class="max-w-4xl">
            <template #header>
                <Tabs v-model="tab" :tabs="tabs" />
            </template>

            <UninformedPlanningReport
                v-if="tab === 'uninformed-planning'"
                v-model:business-line="planningBusinessLine"
                :rows="uninformedPlanning"
                :business-lines="businessLines"
            />

            <div v-else-if="tab === 'competences'" class="p-6 space-y-5">
                <div class="flex flex-wrap items-center gap-3">
                    <SelectInput
                        v-model="competenceMode"
                        :options="competenceModeOptions"
                        class="w-64"
                    />

                    <span class="text-sm text-(--color-text-secondary)">:</span>

                    <SelectInput
                        v-model="competenceId"
                        :options="competenceOptions"
                        class="w-72"
                    />
                </div>

                <CardSeparator />

                <p v-if="!competenceId" class="text-sm text-(--color-text-secondary)">
                    {{ __('reports.competences.empty_selection') }}
                </p>

                <table v-else-if="competenceReport.length > 0" class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                            <th class="px-2 py-2 font-medium">{{ __('reports.competences.column.name') }}</th>
                            <th class="px-2 py-2 font-medium">{{ __('reports.competences.column.competences') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="employee in competenceReport"
                            :key="employee.id"
                            class="cursor-pointer border-b border-(--color-table-row-separator) hover:bg-(--color-table-row-hover-bg)"
                            tabindex="0"
                            @click="openEmployeeCompetences(employee)"
                            @keydown.enter="openEmployeeCompetences(employee)"
                        >
                            <td class="px-2 py-2 text-(--color-table-row-text)">{{ employee.name }}</td>
                            <td class="px-2 py-2 text-(--color-table-row-text)">
                                {{ employee.competence_names.join(', ') }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-else class="text-sm text-(--color-text-secondary)">
                    {{ __('reports.competences.empty_results') }}
                </p>
            </div>

            <div v-else class="p-6 space-y-5">
                <div class="flex flex-wrap items-center gap-4">
                    <SelectInput
                        v-model="shift"
                        :options="shiftOptions"
                        :placeholder="__('reports.missing_availability.shift_placeholder')"
                        class="w-56"
                    />

                    <SelectInput
                        v-model="businessLine"
                        :options="businessLineOptions"
                        :placeholder="__('reports.missing_availability.business_line_placeholder')"
                        class="w-56"
                    />

                    <CheckboxInput v-model="includeUnconfirmed">
                        {{ __('reports.missing_availability.include_unconfirmed') }}
                    </CheckboxInput>
                </div>

                <CardSeparator />

                <div v-if="employees.length > 0" class="flex justify-end">
                    <ButtonPrimary
                        type="button"
                        icon="envelope"
                        :disabled="selectedIds.length === 0"
                        :aria-label="__('reports.missing_availability.email_selected') + ' ' + selectedIds.length"
                        @click="emailSelected"
                    >
                        {{ __('reports.missing_availability.email_selected') }}
                        <span v-if="selectedIds.length > 0" class="text-sm font-semibold">
                            ({{ selectedIds.length }})
                        </span>
                    </ButtonPrimary>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                            <th class="w-8 px-2 py-2 pr-3">
                                <CheckboxInput
                                    :model-value="allSelected"
                                    :aria-label="__('reports.missing_availability.select_all')"
                                    @update:model-value="toggleSelectAll"
                                />
                            </th>
                            <th class="px-2 py-2 font-medium">{{ __('reports.missing_availability.column.name') }}</th>
                            <th class="px-2 py-2 font-medium">{{ __('reports.missing_availability.column.business_line') }}</th>
                            <th class="px-2 py-2 font-medium">{{ __('reports.missing_availability.column.weekly_hours') }}</th>
                            <th class="px-2 py-2 font-medium">{{ __('reports.missing_availability.column.confirmed') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="employee in employees"
                            :key="employee.id"
                            class="border-b border-(--color-table-row-separator) hover:bg-(--color-table-row-hover-bg)"
                        >
                            <td class="w-8 px-2 py-2 pr-3">
                                <CheckboxInput
                                    v-model="selectedIds"
                                    :value="employee.id"
                                    :disabled="employee.has_email === false"
                                    :aria-label="__('reports.missing_availability.select_employee', { name: employee.name })"
                                />
                            </td>
                            <td class="px-2 py-2 text-(--color-table-row-text)">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span>{{ employee.name }}</span>
                                    <NoEmailBadge v-if="employee.has_email === false" />
                                </div>
                            </td>
                            <td class="px-2 py-2 text-(--color-table-row-text)">
                                {{ employee.business_line ?? __('reports.missing_availability.no_business_line') }}
                            </td>
                            <td class="px-2 py-2 text-(--color-table-row-text)">{{ employee.weekly_hours }}</td>
                            <td class="px-2 py-2 text-(--color-table-row-text)">
                                {{ employee.confirmed
                                    ? __('reports.missing_availability.confirmed.yes')
                                    : __('reports.missing_availability.confirmed.no') }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="employees.length === 0" class="text-sm text-(--color-text-secondary)">
                    {{ __('reports.missing_availability.empty') }}
                </p>
            </div>
        </Card>
    </AppLayout>
</template>
