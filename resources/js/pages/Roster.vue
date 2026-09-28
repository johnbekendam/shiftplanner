<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    weekStart: { type: String, required: true },
    weekNumber: { type: Number, required: true },
    today: { type: String, required: true },
    days: { type: Array, required: true }, // ['YYYY-MM-DD', …] Monday to Sunday
})
</script>

<template>
    <Head :title="__('roster.title')" />
    <AppLayout />
</template>
