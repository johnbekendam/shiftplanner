<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import LabeledInput from '@/components/LabeledInput.vue'
import { NumberInput, DateInput, CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // { fte_hours, period_start, period_end, allow_employee_changes } — dates are ISO strings or null.
    period: { type: Object, default: () => ({}) },
})

const form = useForm({
    fte_hours: props.period.fte_hours ?? 40,
    period_start: props.period.period_start ?? '',
    period_end: props.period.period_end ?? '',
    allow_employee_changes: props.period.allow_employee_changes ?? true,
})

// Driven by the page's shared TabSaveBar via this exposed surface,
// instead of an inline submit button — the useForm/validation/PUT stay
// exactly as they were.
function submit() {
    return new Promise((resolve) => {
        form.transform((data) => ({
            ...data,
            period_start: data.period_start || null,
            period_end: data.period_end || null,
        })).put('/settings/period', {
            preserveScroll: true,
            onSuccess: () => {
                form.defaults()
                resolve(true)
            },
            onError: () => resolve(false),
        })
    })
}

function cancel() {
    form.reset()
    form.clearErrors()
}

defineExpose({
    isDirty: computed(() => form.isDirty),
    processing: computed(() => form.processing),
    submit,
    cancel,
})
</script>

<template>
    <div class="max-w-sm space-y-5">
        <LabeledInput :label="__('period.fte_hours')" :error="form.errors.fte_hours">
            <NumberInput v-model="form.fte_hours" :min="1" class="w-full" />
            <p class="mt-1 text-xs text-(--color-text-secondary)">{{ __('period.fte_hours_hint') }}</p>
        </LabeledInput>

        <LabeledInput :label="__('period.period_start')" :error="form.errors.period_start">
            <DateInput v-model="form.period_start" class="w-full" />
        </LabeledInput>

        <LabeledInput :label="__('period.period_end')" :error="form.errors.period_end">
            <DateInput v-model="form.period_end" class="w-full" />
        </LabeledInput>

        <div>
            <CheckboxInput v-model="form.allow_employee_changes">
                {{ __('general.allow_employee_changes') }}
            </CheckboxInput>
            <p class="mt-1 text-xs text-(--color-text-secondary)">{{ __('general.allow_employee_changes_hint') }}</p>
        </div>
    </div>
</template>
