<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    employees: { type: Array, default: () => [] }, // { id, name, business_line, weekly_hours, confirmed }
    shifts: { type: Array, default: () => [] }, // { id, name, start_time, end_time }
    businessLines: { type: Array, default: () => [] }, // { id, abbreviation }
    filters: { type: Object, required: true }, // { shift, business_line, unconfirmed }
})
</script>

<template>
    <Head :title="__('reports.title')" />
    <AppLayout>
        <Card>
            {{ __('reports.title') }}
        </Card>
    </AppLayout>
</template>
