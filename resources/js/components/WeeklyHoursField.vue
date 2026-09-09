<script setup>
import { computed } from 'vue'
import LabeledInput from '@/components/LabeledInput.vue'
import { SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

// The real weekly-hours choices. The below-minimum "0" option is appended
// for an employee who cannot work the minimum.
const WEEKLY_HOURS_OPTIONS = [20, 24, 28, 32, 36, 40, 44, 48]

const model = defineModel({ type: Number, default: 20 })

defineProps({
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const options = computed(() => [
    ...WEEKLY_HOURS_OPTIONS.map((hours) => ({
        value: hours,
        label: __('employees.hours_option', { count: hours }),
    })),
    {
        value: 0,
        label: __('employees.hours_below_minimum', { min: WEEKLY_HOURS_OPTIONS[0] }),
    },
])
</script>

<template>
    <LabeledInput :label="__('employees.field.weekly_hours')" :error="error">
        <SelectInput v-model="model" :options="options" :disabled="disabled" class="w-full" />
    </LabeledInput>
</template>
