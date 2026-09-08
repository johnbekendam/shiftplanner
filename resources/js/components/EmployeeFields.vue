<script setup>
import { computed } from 'vue'
import LabeledInput from '@/components/LabeledInput.vue'
import { TextInput, EmailInput, SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // An Inertia useForm() instance with name, email, weekly_hours fields.
    form: { type: Object, required: true },
    // When true, name and email render read-only (used by the personal page).
    readonlyIdentity: { type: Boolean, default: false },
})

// Mirrors Employee::WEEKLY_HOURS_OPTIONS — 20 to 48 in steps of 4.
const WEEKLY_HOURS_OPTIONS = [20, 24, 28, 32, 36, 40, 44, 48]

const hoursOptions = computed(() =>
    WEEKLY_HOURS_OPTIONS.map((hours) => ({
        value: hours,
        label: __('employees.hours_option', { count: hours }),
    })),
)
</script>

<template>
    <div class="space-y-5">
        <LabeledInput :label="__('employees.field.name')" :error="form.errors.name">
            <TextInput v-model="form.name" :disabled="readonlyIdentity" class="w-full" />
        </LabeledInput>

        <LabeledInput :label="__('employees.field.email')" :error="form.errors.email">
            <EmailInput v-model="form.email" :disabled="readonlyIdentity" class="w-full" />
        </LabeledInput>

        <LabeledInput :label="__('employees.field.weekly_hours')" :error="form.errors.weekly_hours">
            <SelectInput v-model="form.weekly_hours" :options="hoursOptions" class="w-full" />
        </LabeledInput>
    </div>
</template>
