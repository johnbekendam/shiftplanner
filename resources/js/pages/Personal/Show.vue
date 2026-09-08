<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import EmployeeFields from '@/components/EmployeeFields.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    token: { type: String, required: true },
    employee: { type: Object, required: true },
})

const form = useForm({
    name: props.employee.name,
    email: props.employee.email,
    weekly_hours: props.employee.weekly_hours,
})

function save() {
    form
        .transform((data) => ({ weekly_hours: data.weekly_hours }))
        .put(`/personal/${props.token}`, { preserveScroll: true })
}
</script>

<template>
    <CenteredLayout>
        <Head :title="__('personal.title')" />

        <template #title>{{ __('personal.greeting', { name: employee.name }) }}</template>

        <form class="space-y-5" @submit.prevent="save">
            <p class="text-sm text-(--color-text-secondary)">{{ __('personal.intro') }}</p>

            <EmployeeFields :form="form" readonly-identity />

            <div class="flex items-center justify-end gap-3">
                <span v-if="form.recentlySuccessful" class="text-sm text-(--color-badge-success-text)">
                    {{ __('personal.saved') }}
                </span>
                <ButtonPrimary type="submit" :disabled="form.processing">
                    {{ __('personal.action.save') }}
                </ButtonPrimary>
            </div>
        </form>
    </CenteredLayout>
</template>
