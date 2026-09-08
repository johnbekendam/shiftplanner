<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Tabs from '@/components/ui/Tabs.vue'
import EmployeeFields from '@/components/EmployeeFields.vue'
import HolidayList from '@/components/HolidayList.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    employee: { type: Object, default: null },
    holidays: { type: Array, default: () => [] },
})

const isEdit = computed(() => props.employee !== null)

const form = useForm({
    name: props.employee?.name ?? '',
    email: props.employee?.email ?? '',
    weekly_hours: props.employee?.weekly_hours ?? 20,
})

const tab = ref('details')
const tabs = computed(() => [
    { value: 'details', label: __('availability.tab.details') },
    { value: 'availability', label: __('availability.tab.availability') },
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
                    <EmployeeFields :form="form" />

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
                <HolidayList
                    v-if="isEdit"
                    :holidays="holidays"
                    :endpoint="`/employees/${employee.id}/holidays`"
                />
                <p v-else class="text-sm text-(--color-text-secondary)">
                    {{ __('availability.holidays.save_first') }}
                </p>
            </div>
        </Card>
    </AppLayout>
</template>
