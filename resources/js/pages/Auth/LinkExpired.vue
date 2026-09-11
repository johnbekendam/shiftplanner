<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    purpose: { type: String, default: null },
})

const message = computed(() => __(`auth.expired.${props.purpose ?? 'unknown'}`))
</script>

<template>
    <CenteredLayout align="top">
        <Head :title="__('auth.expired.title')" />

        <template #title>{{ __('auth.expired.title') }}</template>

        <p class="mb-6 text-sm text-(--color-text-secondary)" data-testid="expired-message">{{ message }}</p>

        <Link href="/login" class="text-sm font-medium text-(--color-text-link) hover:text-(--color-text-link-hover) hover:underline">
            {{ __('auth.expired.back_to_login') }}
        </Link>
    </CenteredLayout>
</template>
