<script setup>
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // A dayAvailability() result: { date, hiddenOverrides }.
    day: { type: Object, required: true },
})

// Emits the whole day, like DateAvailabilityGrid. Overrides of shifts that
// do not run on the date are not shown, so a reset keeps them.
const emit = defineEmits(['apply-day'])

function reset() {
    emit('apply-day', { date: props.day.date, blocked: false, shifts: { ...(props.day.hiddenOverrides ?? {}) } })
}
</script>

<template>
    <ButtonSecondary type="button" @click="reset">
        {{ __('availability.day.reset') }}
    </ButtonSecondary>
</template>
