<script setup>
import LabeledInput from '@/components/LabeledInput.vue'
import { NumberInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const model = defineModel({ type: Number, default: 0 })

defineProps({
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
    minimum: { type: Number, default: 20 },
    // Emit update:modelValue as soon as the in-progress value is valid,
    // instead of only on commit (Enter/Tab/blur) — see NumberInput.
    live: { type: Boolean, default: false },
})
</script>

<template>
    <LabeledInput :label="__('employees.field.weekly_hours')" :error="error">
        <div class="flex items-center gap-3">
            <NumberInput
                v-model="model"
                :min="0"
                :max="48"
                :step="1"
                :disabled="disabled"
                :live="live"
                class="w-24 shrink-0"
            />
            <p
                v-if="model > 0 && model < minimum"
                data-testid="weekly-hours-minimum-warning"
                class="min-w-0 flex-1 rounded-md border border-(--color-badge-warning-border) bg-(--color-badge-warning-bg) px-3 py-2 text-sm text-(--color-badge-warning-text)"
            >
                {{ __('employees.weekly_hours_below_minimum_warning', { min: minimum }) }}
            </p>
        </div>
    </LabeledInput>
</template>
