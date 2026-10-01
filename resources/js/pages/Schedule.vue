<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import ScheduleView from '@/components/ScheduleView.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

// Every page prop goes through to ScheduleView, not onto the layout root.
defineOptions({ inheritAttrs: false })
</script>

<template>
    <AppLayout>
        <Head :title="__('schedule.title')" />

        <ScheduleView v-bind="$attrs" url="/schedule" default-session-key="schedule.businessLineDefaultApplied" />
    </AppLayout>
</template>
