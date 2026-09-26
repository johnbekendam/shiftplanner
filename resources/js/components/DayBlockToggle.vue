<script setup>
import { CheckboxInput } from '@/components/ui/Input'
import { dayOverrides } from '@/utils/availabilityCalendar'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // A dayAvailability() result: { date, blocked, shifts: [{ shift, override }] }.
    day: { type: Object, required: true },
    // Read-only (employee change lock).
    disabled: { type: Boolean, default: false },
})

// Emits the whole day, like DateAvailabilityGrid, so the shift overrides stay.
const emit = defineEmits(['apply-day'])

function setBlocked(blocked) {
    emit('apply-day', { date: props.day.date, blocked, shifts: dayOverrides(props.day) })
}
</script>

<template>
    <CheckboxInput :model-value="day.blocked" :disabled="disabled" @update:model-value="setBlocked">
        {{ __('availability.day.block') }}
    </CheckboxInput>
</template>
