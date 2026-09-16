<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
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
    employeeStatusFilter: { type: String, default: 'both' },
    unconfirmedEmployeeCount: { type: Number, default: 0 },
})

const employeeFilterOptions = [
    { value: 'unconfirmed', label: 'dashboard.employee_filter.unconfirmed', lineClass: 'bg-[var(--color-text-secondary)]' },
    { value: 'confirmed', label: 'dashboard.employee_filter.confirmed', lineClass: 'bg-[var(--color-badge-success-text)]' },
    { value: 'both', label: 'dashboard.employee_filter.both', lineClass: 'bg-[var(--color-brand-bg)]' },
]

const confirmedLineColor = 'var(--color-badge-success-text)'
const unconfirmedLineColor = 'var(--color-text-secondary)'
const stackedLineColor = 'var(--color-brand-bg)'

const activeEmployeeFilter = computed(() =>
    ['confirmed', 'unconfirmed', 'both'].includes(props.employeeStatusFilter) ? props.employeeStatusFilter : 'both',
)

const lineStrokeFor = (filter) => {
    if (filter === 'confirmed') return confirmedLineColor
    if (filter === 'unconfirmed') return unconfirmedLineColor
    return stackedLineColor
}

const stackedLines = (block) =>
    activeEmployeeFilter.value === 'both'
        ? {
              baseAvailable: block.available_confirmed,
              baseAvailableStroke: confirmedLineColor,
              secondaryAvailable: block.available_unconfirmed,
              secondaryAvailableStroke: unconfirmedLineColor,
          }
        : { baseAvailable: null, secondaryAvailable: null }

const selectEmployeeFilter = (filter) => {
    if (filter === activeEmployeeFilter.value) return

    router.get(
        '/dashboard',
        filter === 'both' ? {} : { employees: filter },
        { preserveScroll: true, preserveState: true },
    )
}

const blocks = computed(() => {
    if (!props.overall) return []
    return [
        {
            key: 'overall',
            title: __('dashboard.overall'),
            available: props.overall.available,
            availableStroke: lineStrokeFor(activeEmployeeFilter.value),
            target: props.overall.target,
            availableHours: props.overall.available_hours,
            requiredHours: props.overall.required_hours,
            confirmedHours: props.overall.available_hours_confirmed,
            employeesHref: '/employees',
            ...stackedLines(props.overall),
        },
        ...props.lines.map((line) => ({
            key: line.abbreviation,
            title: `${line.abbreviation} — ${line.description}`,
            available: line.available,
            availableStroke: lineStrokeFor(activeEmployeeFilter.value),
            target: line.target,
            availableHours: line.available_hours,
            requiredHours: line.required_hours,
            confirmedHours: line.available_hours_confirmed,
            employeesHref: `/employees?business_lines[]=${line.id}`,
            ...stackedLines(line),
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
            <div class="flex flex-wrap gap-2" role="group" :aria-label="__('dashboard.employee_filter.label')">
                <component
                    :is="ButtonSecondary"
                    v-for="option in employeeFilterOptions"
                    :key="option.value"
                    type="button"
                    data-testid="dashboard-employee-filter"
                    :class="option.value === activeEmployeeFilter ? 'outline outline-2 outline-offset-2 outline-[var(--color-brand-bg)]' : ''"
                    :aria-pressed="option.value === activeEmployeeFilter"
                    @click="selectEmployeeFilter(option.value)"
                >
                    <span
                        v-if="option.lineClass"
                        data-testid="dashboard-employee-filter-line"
                        class="h-0.5 w-5 shrink-0 rounded-full"
                        :class="option.lineClass"
                        aria-hidden="true"
                    ></span>
                    {{ __(option.label) }}
                </component>
            </div>

            <p
                v-if="activeEmployeeFilter === 'confirmed' && unconfirmedEmployeeCount > 0"
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
                            :available="block.available"
                            :available-stroke="block.availableStroke"
                            :base-available="block.baseAvailable"
                            :base-available-stroke="block.baseAvailableStroke"
                            :secondary-available="block.secondaryAvailable"
                            :secondary-available-stroke="block.secondaryAvailableStroke"
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
