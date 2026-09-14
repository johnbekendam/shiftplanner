<script setup>
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import FteLineChart from '@/components/FteLineChart.vue'
import CoverageDonut from '@/components/CoverageDonut.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // null until a full period is set on the Settings page.
    period: { type: Object, default: null },
    days: { type: Array, default: () => [] },
    overall: { type: Object, default: null },
    lines: { type: Array, default: () => [] },
    unconfirmedEmployeeCount: { type: Number, default: 0 },
})

const blocks = computed(() => {
    if (!props.overall) return []
    return [
        {
            key: 'overall',
            title: __('dashboard.overall'),
            available: props.overall.available,
            target: props.overall.target,
            availableHours: props.overall.available_hours,
            requiredHours: props.overall.required_hours,
        },
        ...props.lines.map((line) => ({
            key: line.abbreviation,
            title: `${line.abbreviation} — ${line.description}`,
            available: line.available,
            target: line.target,
            availableHours: line.available_hours,
            requiredHours: line.required_hours,
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

        <div v-else data-testid="dashboard-card-grid" class="grid w-full gap-6">
            <p
                v-if="unconfirmedEmployeeCount > 0"
                data-testid="unconfirmed-employees-notice"
                class="text-sm text-(--color-text-secondary)"
            >
                {{ __('dashboard.unconfirmed_employees', { count: unconfirmedEmployeeCount }) }}
            </p>

            <Card v-for="block in blocks" :key="block.key" data-testid="dashboard-block">
                <template #header>
                    <h2 class="px-4 py-2.5 text-sm font-semibold text-(--color-card-header-text)">
                        {{ block.title }}
                    </h2>
                </template>

                <div class="flex flex-wrap items-center gap-4 p-4">
                    <div class="min-w-0 flex-1">
                        <FteLineChart
                            :title="block.title"
                            :days="days"
                            :available="block.available"
                            :target="block.target"
                            :show-caption="false"
                        />
                    </div>
                    <div class="w-28 shrink-0">
                        <CoverageDonut
                            :available="block.availableHours"
                            :required="block.requiredHours"
                        />
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
