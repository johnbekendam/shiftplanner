<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import LiveLayout from '@/layouts/LiveLayout.vue'
import ScheduleView from '@/components/ScheduleView.vue'
import { useI18n } from '@/composables/useI18n'

// The roster by its secret link, with no login and no app chrome.
// See features/roster-public-link/.
const __ = useI18n()

// Every page prop goes through to ScheduleView, not onto the layout root.
defineOptions({ inheritAttrs: false })

// The token path, so every week or filter change stays on the secret link.
const url = usePage().url.split('?')[0]
</script>

<template>
    <Head :title="__('schedule.title')" />

    <LiveLayout :title="__('schedule.title')">
        <ScheduleView v-bind="$attrs" :url="url" />
    </LiveLayout>
</template>
