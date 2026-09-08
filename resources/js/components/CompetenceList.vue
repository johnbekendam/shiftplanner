<script setup>
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    competences: { type: Array, default: () => [] },
    // Base URL for the competence resource, e.g. /settings/competences.
    endpoint: { type: String, required: true },
})
</script>

<template>
    <ul>
        <li v-for="competence in competences" :key="competence.id">{{ competence.name }}</li>
        <li v-if="!competences.length">{{ __('competences.list_empty') }}</li>
    </ul>
</template>
