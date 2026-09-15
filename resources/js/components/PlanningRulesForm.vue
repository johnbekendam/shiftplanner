<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import LabeledInput from '@/components/LabeledInput.vue'
import { NumberInput, SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // { max_hours_per_week_mode, max_hours_per_week_severity, max_shifts_per_day,
    //   max_shifts_per_day_mode, max_shifts_per_day_severity, not_preferred_shift_mode,
    //   not_preferred_shift_severity }
    planningRules: { type: Object, default: () => ({}) },
})

const modeOptions = computed(() => [
    { value: 'hard', label: __('planning_rules.mode.hard') },
    { value: 'soft', label: __('planning_rules.mode.soft') },
])

const form = useForm({
    max_hours_per_week_mode: props.planningRules.max_hours_per_week_mode ?? 'hard',
    max_hours_per_week_severity: props.planningRules.max_hours_per_week_severity ?? null,
    max_shifts_per_day: props.planningRules.max_shifts_per_day ?? 1,
    max_shifts_per_day_mode: props.planningRules.max_shifts_per_day_mode ?? 'hard',
    max_shifts_per_day_severity: props.planningRules.max_shifts_per_day_severity ?? null,
    not_preferred_shift_mode: props.planningRules.not_preferred_shift_mode ?? 'soft',
    not_preferred_shift_severity: props.planningRules.not_preferred_shift_severity ?? null,
})

// Driven by the page's shared TabSaveBar via this exposed surface,
// matching PeriodSettingsForm.
function submit() {
    return new Promise((resolve) => {
        form.transform((data) => ({
            ...data,
            max_hours_per_week_severity: data.max_hours_per_week_mode === 'soft' ? data.max_hours_per_week_severity : null,
            max_shifts_per_day_severity: data.max_shifts_per_day_mode === 'soft' ? data.max_shifts_per_day_severity : null,
            not_preferred_shift_severity: data.not_preferred_shift_mode === 'soft' ? data.not_preferred_shift_severity : null,
        })).put('/settings/planning-rules', {
            preserveScroll: true,
            async: true,
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
    <div class="max-w-sm space-y-6">
        <div class="space-y-3">
            <LabeledInput :label="__('planning_rules.max_hours_per_week')" :error="form.errors.max_hours_per_week_mode">
                <SelectInput v-model="form.max_hours_per_week_mode" :options="modeOptions" class="w-full" />
                <p class="mt-1 text-xs text-(--color-text-secondary)">{{ __('planning_rules.max_hours_per_week_hint') }}</p>
            </LabeledInput>
            <LabeledInput
                v-if="form.max_hours_per_week_mode === 'soft'"
                :label="__('planning_rules.severity')"
                :error="form.errors.max_hours_per_week_severity"
            >
                <NumberInput v-model="form.max_hours_per_week_severity" :min="1" :max="10" class="w-full" />
            </LabeledInput>
        </div>

        <div class="space-y-3">
            <LabeledInput :label="__('planning_rules.max_shifts_per_day')" :error="form.errors.max_shifts_per_day">
                <NumberInput v-model="form.max_shifts_per_day" :min="1" class="w-full" />
            </LabeledInput>
            <LabeledInput :label="__('planning_rules.mode')" :error="form.errors.max_shifts_per_day_mode">
                <SelectInput v-model="form.max_shifts_per_day_mode" :options="modeOptions" class="w-full" />
            </LabeledInput>
            <LabeledInput
                v-if="form.max_shifts_per_day_mode === 'soft'"
                :label="__('planning_rules.severity')"
                :error="form.errors.max_shifts_per_day_severity"
            >
                <NumberInput v-model="form.max_shifts_per_day_severity" :min="1" :max="10" class="w-full" />
            </LabeledInput>
        </div>

        <div class="space-y-3">
            <LabeledInput :label="__('planning_rules.not_preferred_shift')" :error="form.errors.not_preferred_shift_mode">
                <SelectInput v-model="form.not_preferred_shift_mode" :options="modeOptions" class="w-full" />
                <p class="mt-1 text-xs text-(--color-text-secondary)">{{ __('planning_rules.not_preferred_shift_hint') }}</p>
            </LabeledInput>
            <LabeledInput
                v-if="form.not_preferred_shift_mode === 'soft'"
                :label="__('planning_rules.severity')"
                :error="form.errors.not_preferred_shift_severity"
            >
                <NumberInput v-model="form.not_preferred_shift_severity" :min="1" :max="10" class="w-full" />
            </LabeledInput>
        </div>
    </div>
</template>
