<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
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
    employeeStatusFilter: { type: String, default: 'confirmed' },
    unconfirmedEmployeeCount: { type: Number, default: 0 },
})

const employeeFilterOptions = [
    { value: 'confirmed', label: 'dashboard.employee_filter.confirmed' },
    { value: 'unconfirmed', label: 'dashboard.employee_filter.unconfirmed' },
    { value: 'both', label: 'dashboard.employee_filter.both' },
]

const activeEmployeeFilter = computed(() =>
    ['confirmed', 'unconfirmed', 'both'].includes(props.employeeStatusFilter) ? props.employeeStatusFilter : 'confirmed',
)

const selectEmployeeFilter = (filter) => {
    if (filter === activeEmployeeFilter.value) return

    router.get(
        '/dashboard',
        filter === 'confirmed' ? {} : { employees: filter },
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
            baseAvailable: activeEmployeeFilter.value === 'both' ? props.overall.available_confirmed : null,
            target: props.overall.target,
            availableHours: props.overall.available_hours,
            requiredHours: props.overall.required_hours,
        },
        ...props.lines.map((line) => ({
            key: line.abbreviation,
            title: `${line.abbreviation} — ${line.description}`,
            available: line.available,
            baseAvailable: activeEmployeeFilter.value === 'both' ? line.available_confirmed : null,
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
            <div class="flex flex-wrap gap-2" role="group" :aria-label="__('dashboard.employee_filter.label')">
                <component
                    :is="option.value === activeEmployeeFilter ? ButtonPrimary : ButtonSecondary"
                    v-for="option in employeeFilterOptions"
                    :key="option.value"
                    type="button"
                    data-testid="dashboard-employee-filter"
                    :aria-pressed="option.value === activeEmployeeFilter"
                    @click="selectEmployeeFilter(option.value)"
                >
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
                            :base-available="block.baseAvailable"
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
