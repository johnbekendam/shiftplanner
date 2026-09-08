<script setup>
import { reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Icon from '@/components/ui/Icon.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Array of { weekday, daypart, level } for the non-available cells.
    availability: { type: Array, default: () => [] },
    // Base URL, e.g. /employees/7/availability.
    endpoint: { type: String, required: true },
})

const DAYPARTS = ['morning', 'afternoon', 'evening']
const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7]
const STATES = ['available', 'not_preferred', 'unavailable']
// available -> not_preferred -> unavailable -> available
const CYCLE = { available: 'not_preferred', not_preferred: 'unavailable', unavailable: 'available' }

const key = (weekday, daypart) => `${weekday}-${daypart}`

// Local state for instant feedback; the server write follows.
const cells = reactive({})

function sync(rows) {
    for (const weekday of WEEKDAYS) {
        for (const daypart of DAYPARTS) cells[key(weekday, daypart)] = 'available'
    }
    for (const row of rows) cells[key(row.weekday, row.daypart)] = row.level
}
sync(props.availability)
watch(() => props.availability, sync)

// Theme-builder badge tokens: Success / Warning / Error.
const LEVEL_CLASS = {
    available: 'bg-(--color-badge-success-bg) text-(--color-badge-success-text) border-(--color-badge-success-border)',
    not_preferred: 'bg-(--color-badge-warning-bg) text-(--color-badge-warning-text) border-(--color-badge-warning-border)',
    unavailable: 'bg-(--color-badge-error-bg) text-(--color-badge-error-text) border-(--color-badge-error-border)',
}

const LEVEL_ICON = {
    available: 'check-circle',
    not_preferred: 'exclamation-triangle',
    unavailable: 'x-circle',
}

function cycle(weekday, daypart) {
    const next = CYCLE[cells[key(weekday, daypart)]]
    cells[key(weekday, daypart)] = next
    router.put(`${props.endpoint}/${weekday}/${daypart}`, { level: next }, {
        preserveScroll: true,
        preserveState: true,
    })
}
</script>

<template>
    <div class="space-y-3">
        <table class="w-full table-fixed text-sm">
            <thead>
                <tr class="text-(--color-table-header-text)">
                    <th class="w-24 py-2" />
                    <th v-for="weekday in WEEKDAYS" :key="weekday" class="py-2 text-center font-medium">
                        {{ __(`availability.weekday.${weekday}`) }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="daypart in DAYPARTS" :key="daypart">
                    <th class="py-1 pr-3 text-left font-medium text-(--color-table-row-text)">
                        {{ __(`availability.daypart.${daypart}`) }}
                    </th>
                    <td v-for="weekday in WEEKDAYS" :key="weekday" class="p-1">
                        <button
                            type="button"
                            :data-testid="`cell-${weekday}-${daypart}`"
                            :aria-label="__('availability.grid.cell', {
                                daypart: __(`availability.daypart.${daypart}`),
                                day: __(`availability.weekday.${weekday}`),
                                state: __(`availability.state.${cells[`${weekday}-${daypart}`]}`),
                            })"
                            class="flex h-8 w-full items-center justify-center rounded border transition-colors"
                            :class="LEVEL_CLASS[cells[`${weekday}-${daypart}`]]"
                            @click="cycle(weekday, daypart)"
                        >
                            <Icon :name="LEVEL_ICON[cells[`${weekday}-${daypart}`]]" class="size-4" />
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <ul class="flex flex-wrap justify-end gap-x-4 gap-y-1 text-xs text-(--color-text-secondary)">
            <li v-for="level in STATES" :key="level" class="flex items-center gap-1.5">
                <span
                    class="flex size-5 items-center justify-center rounded border"
                    :class="LEVEL_CLASS[level]"
                >
                    <Icon :name="LEVEL_ICON[level]" class="size-3.5" />
                </span>
                {{ __(`availability.state.${level}`) }}
            </li>
        </ul>
    </div>
</template>
