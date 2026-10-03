<script setup>
import { computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import { CheckboxInput } from '@/components/ui/Input'
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
})

const page = usePage()

// Toggle and paint order: the last line sits on top.
const lineOptions = [
    { key: 'demand', label: 'dashboard.lines.demand', series: 'demand', stroke: 'var(--color-brand-bg)', markerClass: 'bg-[var(--color-brand-bg)]', step: true },
    { key: 'available', label: 'dashboard.lines.available', series: 'available', stroke: 'var(--color-badge-success-text)', markerClass: 'bg-[var(--color-badge-success-text)]' },
    { key: 'planned', label: 'dashboard.lines.planned', series: 'planned', stroke: 'var(--color-badge-warning-text)', markerClass: 'bg-[var(--color-badge-warning-text)]', step: true },
]

const defaultLines = ['available', 'planned']

// No `lines` parameter gives the default set. An empty value turns every line off.
const visibleLines = computed(() => {
    const param = new URL(page.url ?? '/dashboard', 'http://localhost').searchParams.get('lines')
    if (param === null) return defaultLines
    const requested = param.split(',')
    return lineOptions.map((option) => option.key).filter((key) => requested.includes(key))
})

const isVisible = (key) => visibleLines.value.includes(key)

const toggleLine = (key) => {
    const next = lineOptions
        .map((option) => option.key)
        .filter((optionKey) => (optionKey === key ? !isVisible(optionKey) : isVisible(optionKey)))

    router.get(
        '/dashboard',
        next.join(',') === defaultLines.join(',') ? {} : { lines: next.join(',') },
        { preserveScroll: true, preserveState: true },
    )
}

// A business-line block has no demand series, so that line is left out there.
const chartLines = (block) =>
    lineOptions
        .filter((option) => isVisible(option.key) && Array.isArray(block[option.series]))
        .map((option) => ({ key: option.key, values: block[option.series], stroke: option.stroke, step: option.step === true }))

// A donut shows only while its line is on.
const donuts = (block) =>
    [
        { key: 'available', label: __('dashboard.lines.available'), available: block.available_hours, required: block.required_hours },
        { key: 'planned', label: __('dashboard.lines.planned'), available: block.planned_hours, required: block.available_hours },
    ].filter((donut) => isVisible(donut.key))

const blocks = computed(() => {
    if (!props.overall) return []
    return [
        {
            key: 'overall',
            title: __('dashboard.overall'),
            lines: chartLines(props.overall),
            target: props.overall.target,
            donuts: donuts(props.overall),
            employeesHref: '/employees',
        },
        ...props.lines.map((line) => ({
            key: line.abbreviation,
            title: `${line.abbreviation} — ${line.description}`,
            lines: chartLines(line),
            target: line.target,
            donuts: donuts(line),
            employeesHref: `/employees?business_lines[]=${line.id}`,
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
            <div class="flex flex-wrap gap-x-10 gap-y-3" role="group" :aria-label="__('dashboard.lines.label')">
                <div v-for="option in lineOptions" :key="option.key" class="inline-flex flex-col gap-1">
                    <CheckboxInput
                        data-testid="dashboard-line-toggle"
                        :model-value="isVisible(option.key)"
                        @update:model-value="toggleLine(option.key)"
                    >
                        {{ __(option.label) }}
                    </CheckboxInput>
                    <span
                        data-testid="dashboard-line-toggle-marker"
                        class="h-1 w-full rounded-full"
                        :class="option.markerClass"
                        aria-hidden="true"
                    ></span>
                </div>
            </div>

            <Card v-for="block in blocks" :key="block.key" data-testid="dashboard-block">
                <template #header>
                    <Link
                        :href="block.employeesHref"
                        data-testid="dashboard-block-header-link"
                        class="block px-4 py-2.5 text-sm font-semibold text-(--color-card-header-text) hover:underline"
                    >
                        {{ block.title }}
                    </Link>
                </template>

                <div class="flex flex-wrap items-center gap-4 p-4">
                    <div class="min-w-0 flex-1">
                        <FteLineChart
                            :title="block.title"
                            :days="days"
                            :lines="block.lines"
                            :target="block.target"
                            :show-caption="false"
                        />
                    </div>
                    <div v-if="block.donuts.length" class="flex w-28 shrink-0 flex-col gap-4">
                        <CoverageDonut
                            v-for="donut in block.donuts"
                            :key="donut.key"
                            :label="donut.label"
                            :available="donut.available"
                            :required="donut.required"
                        />
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
