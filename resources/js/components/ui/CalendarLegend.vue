<script setup>
import { computed } from 'vue'
import { BORDER_COLOR_CLASS, BORDER_STYLES, COLOR_CLASS } from '@/components/ui/calendarClasses'

const props = defineProps({
    // { color: text } — one swatch per day color.
    legenda: { type: Object, default: () => ({}) },
    // { solid?: text } — a swatch for the marked-day border, shown on a success day.
    borderLegenda: { type: Object, default: () => ({}) },
})

const legendaEntries = computed(() =>
    Object.entries(props.legenda)
        .filter(([, text]) => text)
        .map(([color, text]) => ({ color, text })),
)

const borderLegendaEntries = computed(() =>
    Object.entries(props.borderLegenda)
        .filter(([style, text]) => text && BORDER_STYLES.includes(style))
        .map(([style, text]) => ({ style, text })),
)
</script>

<template>
    <div data-testid="calendar-legend" class="flex flex-wrap items-center gap-4">
        <div v-for="entry in legendaEntries" :key="entry.color" class="flex items-center gap-2">
            <div
                :class="[
                    'm-1 h-6 w-6 shrink-0 rounded-md border-2 border-transparent text-center text-sm font-semibold',
                    COLOR_CLASS[entry.color] ?? '',
                ]"
            >
                x
            </div>
            <span class="text-xs text-(--color-text-muted)">{{ entry.text }}</span>
        </div>
        <div
            v-for="entry in borderLegendaEntries"
            :key="entry.style"
            :data-testid="'calendar-border-legend-' + entry.style"
            class="flex items-center gap-2"
        >
            <div
                :class="[
                    'm-1 h-6 w-6 shrink-0 rounded-md border-2',
                    COLOR_CLASS.success,
                    BORDER_COLOR_CLASS.success,
                ]"
            ></div>
            <span class="text-xs text-(--color-text-muted)">{{ entry.text }}</span>
        </div>
    </div>
</template>
