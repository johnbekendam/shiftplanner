<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // null until a full period is set on the Settings page.
    period: { type: Object, default: null },
    days: { type: Array, default: () => [] },
    overall: { type: Object, default: null },
    lines: { type: Array, default: () => [] },
})

const blocks = computed(() => {
    if (!props.overall) return []
    return [
        { key: 'overall', title: __('dashboard.overall'), ...props.overall },
        ...props.lines.map((line) => ({
            key: line.abbreviation,
            title: `${line.abbreviation} — ${line.description}`,
            available: line.available,
            target: line.target,
        })),
    ]
})
</script>

<template>
    <AppLayout>
        <Head :title="__('dashboard.title')" />

        <p v-if="!period" class="text-sm text-(--color-text-secondary)">
            {{ __('dashboard.no_period') }}
        </p>

        <div v-else class="space-y-6">
            <Card v-for="block in blocks" :key="block.key" data-testid="dashboard-block">
                <h2 class="mb-2 text-sm font-semibold text-(--color-text-primary)">{{ block.title }}</h2>
                <p class="text-xs text-(--color-text-secondary)">
                    {{ block.available.join(', ') }}
                </p>
                <p class="text-xs text-(--color-text-secondary)">target: {{ block.target }}</p>
            </Card>
        </div>
    </AppLayout>
</template>
