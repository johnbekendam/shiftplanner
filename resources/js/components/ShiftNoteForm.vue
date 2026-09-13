<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import LabeledInput from '@/components/LabeledInput.vue'
import { MultilineInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // The raw Markdown note, '' when unset.
    note: { type: String, default: '' },
})

const form = useForm({ note: props.note ?? '' })

// Driven by the page's shared TabSaveBar via this exposed surface,
// instead of an inline submit button — the useForm/validation/PUT stay
// exactly as they were.
function submit() {
    return new Promise((resolve) => {
        form.put('/settings/shifts/note', {
            preserveScroll: true,
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
    <div class="space-y-3">
        <LabeledInput :label="__('shifts.note_label')" :error="form.errors.note">
            <MultilineInput v-model="form.note" rows="8" class="w-full font-mono" />
            <p class="mt-1 text-xs text-(--color-text-secondary)">{{ __('shifts.note_hint') }}</p>
        </LabeledInput>
    </div>
</template>
