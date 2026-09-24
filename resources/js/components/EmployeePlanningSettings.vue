<script setup>
import LabeledInput from '@/components/LabeledInput.vue'
import { NumberInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    form: { type: Object, required: true },
    inheritedMinimum: { type: Number, required: true },
    disabled: { type: Boolean, default: false },
})
</script>

<template>
    <div class="space-y-6">
        <div class="max-w-xs">
            <LabeledInput
                :label="__('employees.settings.minimum_hours')"
                :error="props.form.errors?.weekly_hours_minimum"
            >
                <NumberInput v-model="props.form.weekly_hours_minimum" :min="1" :max="48" :disabled="disabled" class="w-full" />
                <p class="mt-1 text-xs text-(--color-text-secondary)">
                    {{ __('employees.settings.minimum_hours_hint', { min: props.inheritedMinimum }) }}
                </p>
            </LabeledInput>
        </div>
    </div>
</template>