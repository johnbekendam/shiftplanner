<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { TextInput, EmailInput, SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    employee: { type: Object, default: null },
    departments: { type: Array, required: true },
})

const isEdit = computed(() => props.employee !== null)

const form = useForm({
    name: props.employee?.name ?? '',
    email: props.employee?.email ?? '',
    department_id: props.employee?.department_id ?? null,
    shift_preference: props.employee?.shift_preference ?? 'either',
})

const preferenceOptions = computed(() => ({
    morning: __('employees.preference.morning'),
    evening: __('employees.preference.evening'),
    either: __('employees.preference.either'),
}))

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
                <LabeledInput :label="__('employees.field.name')" :error="form.errors.name">
                    <TextInput v-model="form.name" class="w-full" />
                </LabeledInput>

                <LabeledInput :label="__('employees.field.email')" :error="form.errors.email">
                    <EmailInput v-model="form.email" class="w-full" />
                </LabeledInput>

                <LabeledInput :label="__('employees.field.department')" :error="form.errors.department_id">
                    <SelectInput
                        v-model="form.department_id"
                        :options="departments"
                        :placeholder="__('employees.field.department_placeholder')"
                        class="w-full"
                    />
                </LabeledInput>

                <LabeledInput :label="__('employees.field.preference')" :error="form.errors.shift_preference">
                    <SelectInput v-model="form.shift_preference" :options="preferenceOptions" class="w-full" />
                </LabeledInput>

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
