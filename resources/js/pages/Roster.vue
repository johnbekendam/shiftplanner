<script setup>
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import RosterView from '@/components/RosterView.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

// Every page prop goes through to RosterView, not onto the layout root.
defineOptions({ inheritAttrs: false })
</script>

<template>
    <AppLayout>
        <Head :title="__('roster.title')" />

        <RosterView v-bind="$attrs" url="/roster" default-session-key="roster.businessLineDefaultApplied" />
    </AppLayout>
</template>
