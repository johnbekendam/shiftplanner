<script setup>
import CardSeparator from './CardSeparator.vue'
import ButtonSecondary from './ButtonSecondary.vue'
import ButtonPrimary from './ButtonPrimary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    dirty: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    justSaved: { type: Boolean, default: false },
})

defineEmits(['save', 'cancel'])
</script>

<template>
    <CardSeparator />

    <div class="flex items-center justify-end gap-3">
        <ButtonSecondary type="button" :disabled="!dirty || saving" @click="$emit('cancel')">
            {{ __('app.cancel') }}
        </ButtonSecondary>
        <ButtonPrimary :disabled="!dirty || saving" :icon="justSaved ? 'check-circle' : null" @click="$emit('save')">
            {{ saving ? __('app.saving') : justSaved ? __('app.saved') : __('app.save') }}
        </ButtonPrimary>
    </div>
</template>
