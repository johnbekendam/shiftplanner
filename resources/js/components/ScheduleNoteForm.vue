<script setup>
import LabeledInput from '@/components/LabeledInput.vue'
import { MultilineInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    // The raw Markdown note, '' when unset.
    note: { type: String, default: '' },
    error: { type: String, default: null },
})

defineEmits(['update:note'])
</script>

<template>
    <div class="space-y-3">
        <LabeledInput :label="__('shifts.schedule_note_label')" :error="error">
            <MultilineInput
                :model-value="note"
                rows="6"
                class="w-full font-mono"
                @update:model-value="$emit('update:note', $event)"
            />
            <p class="mt-1 text-xs text-(--color-text-secondary)">{{ __('shifts.schedule_note_hint') }}</p>
        </LabeledInput>
    </div>
</template>
