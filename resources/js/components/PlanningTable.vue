<script setup>
import { computed } from 'vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // [{ date, workcenter_name, shift_name, responsible, ... }] — flat list of assignments, any order.
    assignments: { type: Array, default: () => [] },
    emptyText: { type: String, required: true },
})

function parseDate(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d)
}

// ISO 8601 week number: the week containing the date's Thursday.
function isoWeek(date) {
    const thursday = new Date(date.getFullYear(), date.getMonth(), date.getDate() + 3 - ((date.getDay() + 6) % 7))
    const firstThursday = new Date(thursday.getFullYear(), 0, 4)
    return 1 + Math.round(((thursday - firstThursday) / 86400000 - 3 + ((firstThursday.getDay() + 6) % 7)) / 7)
}

function formatDate(date) {
    const dd = String(date.getDate()).padStart(2, '0')
    const mm = String(date.getMonth() + 1).padStart(2, '0')
    return `${dd}-${mm}-${date.getFullYear()}`
}

const rows = computed(() => [...props.assignments]
    .sort((a, b) => a.date.localeCompare(b.date))
    .map((assignment) => {
        const date = parseDate(assignment.date)

        return {
            week: isoWeek(date),
            day: date.toLocaleDateString('en-US', { weekday: 'long' }),
            date: formatDate(date),
            shift: assignment.shift_name,
            workcenter: assignment.workcenter_name,
            responsible: assignment.responsible || '-',
        }
    }))
</script>

<template>
    <p v-if="!rows.length" class="text-sm text-(--color-text-secondary)">{{ emptyText }}</p>
    <table v-else class="w-full text-left text-sm">
        <thead>
            <tr class="border-b border-(--color-table-header-separator) text-(--color-text-secondary)">
                <th class="px-2 py-2 font-medium">{{ __('planning.table.week') }}</th>
                <th class="px-2 py-2 font-medium">{{ __('planning.table.date') }}</th>
                <th class="px-2 py-2 font-medium">{{ __('planning.table.day') }}</th>
                <th class="px-2 py-2 font-medium">{{ __('planning.table.shift') }}</th>
                <th class="px-2 py-2 font-medium">{{ __('planning.table.workcenter') }}</th>
                <th class="px-2 py-2 font-medium">{{ __('planning.table.responsible') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-(--color-table-row-separator)">
            <tr v-for="(row, i) in rows" :key="i">
                <td class="px-2 py-2">{{ row.week }}</td>
                <td class="px-2 py-2">{{ row.date }}</td>
                <td class="px-2 py-2">{{ row.day }}</td>
                <td class="px-2 py-2">{{ row.shift }}</td>
                <td class="px-2 py-2">{{ row.workcenter }}</td>
                <td class="px-2 py-2">{{ row.responsible }}</td>
            </tr>
        </tbody>
    </table>
</template>
