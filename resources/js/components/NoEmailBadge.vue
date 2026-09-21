<script setup>
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border border-(--color-badge-warning-border) bg-(--color-badge-warning-bg) px-2 py-0.5 text-xs font-medium text-(--color-badge-warning-text)"
    >
        {{ __('employees.no_email') }}
    </span>
</template>
