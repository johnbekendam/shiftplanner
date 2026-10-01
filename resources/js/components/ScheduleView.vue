<script setup>
// The schedule week grid with its week navigation and business line filter.
// Shared by the logged-in Schedule page and the public schedule link.
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import Card from '@/components/ui/Card.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import BusinessLineFilter from '@/components/BusinessLineFilter.vue'
import { useI18n } from '@/composables/useI18n'
import { formatDate } from '@/utils/date'

const __ = useI18n()
const locale = usePage().props.locale

const props = defineProps({
    weekStart: { type: String, required: true }, // the Monday, YYYY-MM-DD
    weekNumber: { type: Number, required: true },
    // The date of today, from the server, so the page and the planning agree.
    today: { type: String, required: true },
    days: { type: Array, required: true }, // ['YYYY-MM-DD', …] Monday to Sunday
    // [{ id, name, business_line, days: [[{ shift, workcenter }], …seven] }]
    rows: { type: Array, required: true },
    businessLines: { type: Array, default: () => [] }, // { id, abbreviation }
    selectedBusinessLines: { type: Array, default: () => [] },
    url: { type: String, required: true }, // the page path every week or filter change visits
    // The sessionStorage key of the once-per-tab business line default; null for no default.
    defaultSessionKey: { type: String, default: null },
})

function dayLabel(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString(locale, { weekday: 'short', day: 'numeric' })
}

// UTC arithmetic, so a daylight saving change never shifts the date.
function addDays(dateStr, count) {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(Date.UTC(y, m - 1, d + count)).toISOString().slice(0, 10)
}

const allBusinessLinesSelected = computed(() =>
    [...props.businessLines.map((line) => line.id), 'none'].every((value) => props.selectedBusinessLines.includes(value)),
)

const query = computed(() => {
    const q = { week: props.weekStart }
    if (!allBusinessLinesSelected.value) q.business_lines = props.selectedBusinessLines
    return q
})

function visit(overrides, options = {}) {
    router.get(props.url, { ...query.value, ...overrides }, {
        preserveState: true,
        preserveScroll: true,
        ...options,
    })
}

function goToWeek(offset) {
    visit({ week: addDays(props.weekStart, offset * 7) })
}
</script>

<template>
    <Card class="overflow-visible">
        <div class="flex items-center gap-3 px-6 py-4">
            <ButtonSecondary
                type="button"
                icon="chevron-left"
                :aria-label="__('schedule.previous_week')"
                @click="goToWeek(-1)"
            />
            <span data-testid="schedule-week-label" class="whitespace-nowrap font-semibold">{{ __('schedule.week', { number: weekNumber }) }}</span>
            <span data-testid="schedule-week-dates" class="whitespace-nowrap text-(--color-text-muted)">
                {{ formatDate(days[0]) }} - {{ formatDate(days[days.length - 1]) }}
            </span>
            <ButtonSecondary
                type="button"
                icon="chevron-right"
                :aria-label="__('schedule.next_week')"
                @click="goToWeek(1)"
            />
            <BusinessLineFilter
                class="ml-auto"
                align="right"
                :business-lines="businessLines"
                :selected="selectedBusinessLines"
                :default-session-key="defaultSessionKey"
                @change="(value) => visit({ business_lines: value })"
                @default="(value) => visit({ business_lines: value }, { replace: true })"
            />
        </div>

        <p v-if="!rows.length" class="px-6 pb-8 pt-4 text-(--color-text-muted)">
            {{ __('schedule.empty') }}
        </p>

        <div v-else class="overflow-x-auto border-t border-(--color-table-header-separator)">
            <table class="w-full text-sm">
                <thead>
                    <tr class="whitespace-nowrap border-b border-(--color-table-header-separator) bg-(--color-table-header-bg) text-left text-(--color-table-header-text)">
                        <th class="px-4 py-2 font-medium">{{ __('schedule.column.name') }}</th>
                        <th class="px-4 py-2 font-medium">{{ __('schedule.column.business_line') }}</th>
                        <th
                            v-for="day in days"
                            :key="day"
                            class="px-4 py-2 font-medium"
                            :class="day === today ? 'bg-(--color-table-row-selected-bg) text-(--color-table-row-selected-text)' : ''"
                            :data-date="day"
                            :data-today="day === today"
                        >
                            {{ dayLabel(day) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        data-testid="schedule-row"
                        class="border-b border-(--color-table-row-separator) last:border-b-0 hover:bg-(--color-table-row-hover-bg)"
                    >
                        <td class="whitespace-nowrap px-4 py-2 align-top text-(--color-table-row-text)">{{ row.name }}</td>
                        <td data-testid="schedule-business-line" class="whitespace-nowrap px-4 py-2 align-top text-(--color-table-row-text)">
                            {{ row.business_line ?? __('schedule.no_business_line') }}
                        </td>
                        <td
                            v-for="(assignments, index) in row.days"
                            :key="days[index]"
                            class="px-4 py-2 align-top"
                            :class="days[index] === today ? 'bg-(--color-table-row-selected-bg)' : ''"
                            :data-testid="`schedule-cell-${row.id}-${days[index]}`"
                            :data-today="days[index] === today"
                        >
                            <div
                                v-for="(assignment, i) in assignments"
                                :key="i"
                                data-testid="schedule-assignment"
                                class="whitespace-nowrap"
                            >
                                <div class="text-(--color-text-primary)">{{ assignment.shift }}</div>
                                <div data-testid="schedule-workcenter" class="text-[10px] leading-tight text-(--color-text-muted)">{{ assignment.workcenter }}</div>
                            </div>
                            <span v-if="!assignments.length" class="text-(--color-text-muted)">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Card>
</template>
