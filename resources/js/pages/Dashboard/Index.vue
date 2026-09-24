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
    unconfirmedEmployeeCount: { type: Number, default: 0 },
})

const page = usePage()

// Toggle and paint order: the last line sits on top.
const lineOptions = [
    { key: 'unconfirmed', label: 'dashboard.lines.unconfirmed', series: 'available_unconfirmed', stroke: 'var(--color-text-secondary)', markerClass: 'bg-[var(--color-text-secondary)]' },
    { key: 'confirmed', label: 'dashboard.lines.confirmed', series: 'available_confirmed', stroke: 'var(--color-badge-success-text)', markerClass: 'bg-[var(--color-badge-success-text)]' },
    { key: 'total', label: 'dashboard.lines.total', series: 'available_total', stroke: 'var(--color-brand-bg)', markerClass: 'bg-[var(--color-brand-bg)]' },
    { key: 'planned', label: 'dashboard.lines.planned', series: 'planned', stroke: 'var(--color-badge-warning-text)', markerClass: 'bg-[var(--color-badge-warning-text)]', step: true },
]

const defaultLines = ['total', 'planned']

// No `lines` parameter gives the default set. An empty value turns every line off.
const visibleLines = computed(() => {
    const param = new URL(page.url ?? '/dashboard', 'http://localhost').searchParams.get('lines')
    if (param === null) return defaultLines
    const requested = param.split(',')
    return lineOptions.map((option) => option.key).filter((key) => requested.includes(key))
})

const isVisible = (key) => visibleLines.value.includes(key)

const showUnconfirmedNotice = computed(
    () => props.unconfirmedEmployeeCount > 0 && isVisible('confirmed') && !isVisible('unconfirmed') && !isVisible('total'),
)

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

const chartLines = (block) =>
    lineOptions
        .filter((option) => isVisible(option.key))
        .map((option) => ({ key: option.key, values: block[option.series], stroke: option.stroke, step: option.step === true }))

const blocks = computed(() => {
    if (!props.overall) return []
    return [
        {
            key: 'overall',
            title: __('dashboard.overall'),
            lines: chartLines(props.overall),
            target: props.overall.target,
            requiredHours: props.overall.required_hours,
            confirmedHours: props.overall.available_hours_confirmed,
            employeesHref: '/employees',
        },
        ...props.lines.map((line) => ({
            key: line.abbreviation,
            title: `${line.abbreviation} — ${line.description}`,
            lines: chartLines(line),
            target: line.target,
            requiredHours: line.required_hours,
            confirmedHours: line.available_hours_confirmed,
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
            <div class="flex flex-wrap gap-x-8 gap-y-3" role="group" :aria-label="__('dashboard.lines.label')">
                <CheckboxInput
                    v-for="option in lineOptions"
                    :key="option.key"
                    data-testid="dashboard-line-toggle"
                    :model-value="isVisible(option.key)"
                    @update:model-value="toggleLine(option.key)"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <span
                            data-testid="dashboard-line-toggle-marker"
                            class="h-1 w-4 shrink-0 rounded-full"
                            :class="option.markerClass"
                            aria-hidden="true"
                        ></span>
                        {{ __(option.label) }}
                    </span>
                </CheckboxInput>
            </div>

            <p
                v-if="showUnconfirmedNotice"
                data-testid="unconfirmed-employees-notice"
                class="text-sm text-(--color-text-secondary)"
            >
                {{ __('dashboard.unconfirmed_employees', { count: unconfirmedEmployeeCount }) }}
            </p>

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
                    <div class="w-28 shrink-0">
                        <CoverageDonut
                            :available="block.confirmedHours"
                            :required="block.requiredHours"
                        />
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
