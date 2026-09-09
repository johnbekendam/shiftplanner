<script setup>
import { useForm } from '@inertiajs/vue3'
import LabeledInput from '@/components/LabeledInput.vue'
import { NumberInput, DateInput } from '@/components/ui/Input'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // { fte_hours, period_start, period_end } — dates are ISO strings or null.
    period: { type: Object, default: () => ({}) },
})

const form = useForm({
    fte_hours: props.period.fte_hours ?? 40,
    period_start: props.period.period_start ?? '',
    period_end: props.period.period_end ?? '',
})

function submit() {
    form.transform((data) => ({
        ...data,
        period_start: data.period_start || null,
        period_end: data.period_end || null,
    })).put('/settings/period', { preserveScroll: true })
}
</script>

<template>
    <form class="max-w-sm space-y-5" @submit.prevent="submit">
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

        <ButtonPrimary type="submit" :disabled="form.processing">
            {{ __('period.save') }}
        </ButtonPrimary>
    </form>
</template>
