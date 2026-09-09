<script setup>
import { computed } from 'vue'
import LabeledInput from '@/components/LabeledInput.vue'
import { TextInput, EmailInput, SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // An Inertia useForm() instance with first_name, last_name, email,
    // business_line_id fields.
    form: { type: Object, required: true },
    // When true, name and email render read-only (used by the personal page).
    readonlyIdentity: { type: Boolean, default: false },
    // When true, the business-line select is disabled too (personal page
    // under the employee change lock).
    disabled: { type: Boolean, default: false },
    // Every business line as { id, abbreviation }. Empty hides the field.
    businessLines: { type: Array, default: () => [] },
})

const businessLineOptions = computed(() => [
    { value: null, label: __('employees.field.business_line_none') },
    ...props.businessLines.map((line) => ({ value: line.id, label: line.abbreviation })),
])
</script>

<template>
    <div class="space-y-5">
        <div data-testid="name-row" class="grid grid-cols-2 gap-4">
            <LabeledInput :label="__('employees.field.first_name')" :error="form.errors.first_name">
                <TextInput v-model="form.first_name" :disabled="readonlyIdentity" class="w-full" />
            </LabeledInput>

            <LabeledInput :label="__('employees.field.last_name')" :error="form.errors.last_name">
                <TextInput v-model="form.last_name" :disabled="readonlyIdentity" class="w-full" />
            </LabeledInput>
        </div>

        <LabeledInput :label="__('employees.field.email')" :error="form.errors.email">
            <EmailInput v-model="form.email" :disabled="readonlyIdentity" class="w-full" />
        </LabeledInput>

        <LabeledInput
            v-if="businessLines.length"
            :label="__('employees.field.business_line')"
            :error="form.errors.business_line_id"
        >
            <SelectInput v-model="form.business_line_id" :options="businessLineOptions" :disabled="disabled" class="w-full" />
        </LabeledInput>
    </div>
</template>
