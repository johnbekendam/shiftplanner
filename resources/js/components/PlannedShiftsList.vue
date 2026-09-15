<script setup>
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    // [{ weekStart, weekEnd, published, assignments: [{ date, workcenter_name, shift_name, start_time, end_time }] }]
    weeks: { type: Array, default: () => [] },
    // Shows a Published/Draft marker per week — only meaningful for the admin's view.
    showPublishedMarker: { type: Boolean, default: false },
})

function formatDate(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })
}

function formatWeekRange(start, end) {
    const [y1, m1, d1] = start.split('-').map(Number)
    const [y2, m2, d2] = end.split('-').map(Number)
    const startLabel = new Date(y1, m1 - 1, d1).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    const endLabel = new Date(y2, m2 - 1, d2).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
    return `${startLabel} – ${endLabel}`
}
</script>

<template>
    <p v-if="!weeks.length" class="text-sm text-(--color-text-secondary)">
        {{ __('planning.empty') }}
    </p>
    <div v-else class="space-y-6">
        <section v-for="week in weeks" :key="week.weekStart">
            <div class="mb-2 flex items-center gap-2">
                <h3 class="text-sm font-semibold text-(--color-text-primary)">
                    {{ formatWeekRange(week.weekStart, week.weekEnd) }}
                </h3>
                <span
                    v-if="showPublishedMarker"
                    class="rounded-full border px-2 py-0.5 text-xs font-medium"
                    :class="week.published
                        ? 'border-(--color-badge-custom-border) bg-(--color-badge-custom-bg) text-(--color-badge-custom-text)'
                        : 'border-(--color-badge-standard-border) bg-(--color-badge-standard-bg) text-(--color-badge-standard-text)'"
                >
                    {{ week.published ? __('planning.published') : __('planning.draft') }}
                </span>
            </div>
            <ul class="divide-y divide-(--color-table-row-separator)">
                <li
                    v-for="(assignment, i) in week.assignments"
                    :key="i"
                    class="flex items-center justify-between gap-3 py-2 text-sm"
                >
                    <span class="text-(--color-text-secondary)">{{ formatDate(assignment.date) }}</span>
                    <span>{{ assignment.workcenter_name }}</span>
                    <span class="text-(--color-text-secondary)">
                        {{ assignment.shift_name }} {{ assignment.start_time }}–{{ assignment.end_time }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
