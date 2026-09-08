<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import EmployeeFields from '@/components/EmployeeFields.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    employee: { type: Object, default: null },
})

const isEdit = computed(() => props.employee !== null)

const form = useForm({
    name: props.employee?.name ?? '',
    email: props.employee?.email ?? '',
    weekly_hours: props.employee?.weekly_hours ?? 20,
})

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

        <Card class="max-w-lg">
            <template #header>
                <div class="px-6 py-3 text-base font-semibold">
                    {{ isEdit ? __('employees.form.edit_title') : __('employees.form.create_title') }}
                </div>
            </template>

            <form class="space-y-5 p-6" @submit.prevent="submit">
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
        </Card>
    </AppLayout>
</template>
