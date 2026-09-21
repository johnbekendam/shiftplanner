<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import LiveLayout from '@/layouts/LiveLayout.vue'
import Card from '@/components/ui/Card.vue'
import { useI18n } from '@/composables/useI18n'
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { formatDate } from '@/utils/date'

const __ = useI18n()
const locale = usePage().props.locale

const props = defineProps({
    workcenter: { type: Object, required: true },
    // The date of today, from the server, so the screen and the planning agree.
    today: { type: String, required: true },
    generatedAt: { type: String, required: true },
    // [{ weekStart, weekNumber, published, days: [date], shifts: [{ id, name,
    //   start_time, end_time, cells: [{ date, spots, names, open }] }] }]
    weeks: { type: Array, required: true },
})

useLiveRefresh({ only: ['workcenter', 'today', 'generatedAt', 'weeks'] })

function dayLabel(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString(locale, { weekday: 'short', day: 'numeric' })
}

function updatedTime() {
    return new Date(props.generatedAt).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <Head :title="workcenter.name" />

    <LiveLayout :title="workcenter.name">
        <template #status>
            <span data-testid="live-updated">{{ __('live.updated', { time: updatedTime() }) }}</span>
        </template>

        <Card v-for="week in weeks" :key="week.weekStart" data-testid="live-week">
            <template #header>
                <div class="flex items-baseline gap-4 px-6 py-3">
                    <span class="text-xl font-semibold">{{ __('live.week', { number: week.weekNumber }) }}</span>
                    <span class="text-lg text-(--color-text-muted)">
                        {{ formatDate(week.days[0]) }} - {{ formatDate(week.days[week.days.length - 1]) }}
                    </span>
                </div>
            </template>

            <p v-if="!week.published" class="px-6 py-8 text-xl text-(--color-text-muted)">
                {{ __('live.not_published') }}
            </p>

            <p v-else-if="!week.shifts.length" class="px-6 py-8 text-xl text-(--color-text-muted)">
                {{ __('live.no_shifts') }}
            </p>

            <table v-else class="w-full table-fixed text-xl">
                <thead>
                    <tr class="border-b border-(--color-table-header-separator) bg-(--color-table-header-bg) text-left text-(--color-table-header-text)">
                        <th class="w-48 px-4 py-3 font-medium">{{ __('live.shift') }}</th>
                        <th
                            v-for="day in week.days"
                            :key="day"
                            class="px-4 py-3 font-medium"
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
                        v-for="shift in week.shifts"
                        :key="shift.id"
                        class="border-b border-(--color-table-row-separator) last:border-b-0"
                    >
                        <th class="px-4 py-3 text-left align-top font-semibold">
                            <div>{{ shift.name }}</div>
                            <div class="text-base font-normal text-(--color-text-muted)">
                                {{ shift.start_time }} - {{ shift.end_time }}
                            </div>
                        </th>
                        <td
                            v-for="cell in shift.cells"
                            :key="cell.date"
                            class="px-4 py-3 align-top"
                            :class="cell.date === today ? 'bg-(--color-table-row-selected-bg)' : ''"
                            :data-testid="`live-cell-${shift.id}-${cell.date}`"
                            :data-date="cell.date"
                            :data-today="cell.date === today"
                        >
                            <div
                                v-for="(name, index) in cell.names"
                                :key="index"
                                data-testid="live-name"
                                class="truncate text-(--color-text-primary)"
                            >
                                {{ name }}
                            </div>
                            <span v-if="!cell.names.length" class="text-(--color-text-muted)">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </Card>
    </LiveLayout>
</template>
