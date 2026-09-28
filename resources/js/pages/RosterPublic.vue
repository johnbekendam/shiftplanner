<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import LiveLayout from '@/layouts/LiveLayout.vue'
import RosterView from '@/components/RosterView.vue'
import { useI18n } from '@/composables/useI18n'

// The roster by its secret link, with no login and no app chrome.
// See features/roster-public-link/.
const __ = useI18n()

// Every page prop goes through to RosterView, not onto the layout root.
defineOptions({ inheritAttrs: false })

// The token path, so every week or filter change stays on the secret link.
const url = usePage().url.split('?')[0]
</script>

<template>
    <Head :title="__('roster.title')" />

    <LiveLayout :title="__('roster.title')">
        <RosterView v-bind="$attrs" :url="url" />
    </LiveLayout>
</template>
