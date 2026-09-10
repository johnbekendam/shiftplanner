<script setup>
import { useForm } from '@inertiajs/vue3'
import LabeledInput from '@/components/LabeledInput.vue'
import { MultilineInput } from '@/components/ui/Input'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // The raw Markdown note, '' when unset.
    note: { type: String, default: '' },
})

const form = useForm({ note: props.note ?? '' })

function submit() {
    form.put('/settings/shifts/schedule-note', { preserveScroll: true })
}
</script>

<template>
    <form class="space-y-3" @submit.prevent="submit">
        <LabeledInput :label="__('shifts.schedule_note_label')" :error="form.errors.note">
            <MultilineInput v-model="form.note" rows="6" class="w-full font-mono" />
            <p class="mt-1 text-xs text-(--color-text-secondary)">{{ __('shifts.schedule_note_hint') }}</p>
        </LabeledInput>

        <ButtonPrimary type="submit" :disabled="form.processing">
            {{ __('shifts.schedule_note_save') }}
        </ButtonPrimary>
    </form>
</template>
