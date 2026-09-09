<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import Tabs from '@/components/ui/Tabs.vue'
import EmployeeFields from '@/components/EmployeeFields.vue'
import AvailabilityGrid from '@/components/AvailabilityGrid.vue'
import ShiftNote from '@/components/ShiftNote.vue'
import HolidayList from '@/components/HolidayList.vue'
import TagChecklist from '@/components/TagChecklist.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    employee: { type: Object, default: null },
    businessLines: { type: Array, default: () => [] },
    holidays: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    shiftNoteHtml: { type: String, default: null },
    availability: { type: Array, default: () => [] },
    competences: { type: Array, default: () => [] },
    competenceIds: { type: Array, default: () => [] },
})

const isEdit = computed(() => props.employee !== null)

const form = useForm({
    name: props.employee?.name ?? '',
    email: props.employee?.email ?? '',
    weekly_hours: props.employee?.weekly_hours ?? 20,
    business_line_id: props.employee?.business_line_id ?? null,
})

const tab = ref('details')
const tabs = computed(() => [
    { value: 'details', label: __('availability.tab.details') },
    { value: 'availability', label: __('availability.tab.availability') },
    { value: 'competences', label: __('competences.tab') },
])

function submit() {
    if (isEdit.value) {
        form.put(`/employees/${props.employee.id}`)
    } else {
        form.post('/employees')
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="isEdit ? __('employees.form.edit_title') : __('employees.form.create_title')" />

        <Card class="max-w-2xl">
            <template #header>
                <Tabs v-model="tab" :tabs="tabs" />
            </template>

            <div v-show="tab === 'details'" data-testid="panel-details" class="p-6">
                <form class="space-y-5" @submit.prevent="submit">
                    <EmployeeFields :form="form" :business-lines="businessLines" />

                    <div class="flex justify-end gap-3">
                        <Link href="/employees">
                            <ButtonSecondary type="button">{{ __('employees.action.cancel') }}</ButtonSecondary>
                        </Link>
                        <ButtonPrimary type="submit" :disabled="form.processing">
                            {{ __('employees.action.save') }}
                        </ButtonPrimary>
                    </div>
                </form>
            </div>

            <div v-show="tab === 'availability'" data-testid="panel-availability" class="p-6">
                <ShiftNote v-if="shiftNoteHtml" :html="shiftNoteHtml" class="mb-6" />

                <template v-if="isEdit">
                    <section class="space-y-3">
                        <h3 class="text-sm font-semibold text-(--color-text-primary)">
                            {{ __('availability.grid.heading') }}
                        </h3>
                        <AvailabilityGrid
                            :shifts="shifts"
                            :availability="availability"
                            :endpoint="`/employees/${employee.id}/availability`"
                            show-add-hint
                        />
                    </section>

                    <CardSeparator />

                    <section class="space-y-3">
                        <h3 class="text-sm font-semibold text-(--color-text-primary)">
                            {{ __('availability.holidays.heading') }}
                        </h3>
                        <HolidayList :holidays="holidays" :endpoint="`/employees/${employee.id}/holidays`" />
                    </section>
                </template>
                <p v-else class="text-sm text-(--color-text-secondary)">
                    {{ __('availability.holidays.save_first') }}
                </p>
            </div>

            <div v-show="tab === 'competences'" data-testid="panel-competences" class="p-6">
                <TagChecklist
                    v-if="isEdit"
                    :items="competences"
                    :selected-ids="competenceIds"
                    :endpoint="`/employees/${employee.id}/competences`"
                    empty-key="competences.checklist_empty"
                />
                <p v-else class="text-sm text-(--color-text-secondary)">
                    {{ __('competences.save_first') }}
                </p>
            </div>
        </Card>
    </AppLayout>
</template>
