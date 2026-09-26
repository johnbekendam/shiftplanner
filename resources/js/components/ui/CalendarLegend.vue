<script setup>
import { computed } from 'vue'
import { BORDER_STYLE_CLASS, COLOR_CLASS } from '@/components/ui/calendarClasses'

const props = defineProps({
    // { color: text } — one swatch per day color.
    legenda: { type: Object, default: () => ({}) },
    // { solid?: text, dashed?: text } — one swatch per day border style.
    borderLegenda: { type: Object, default: () => ({}) },
})

const legendaEntries = computed(() =>
    Object.entries(props.legenda)
        .filter(([, text]) => text)
        .map(([color, text]) => ({ color, text })),
)

const borderLegendaEntries = computed(() =>
    Object.entries(props.borderLegenda)
        .filter(([style, text]) => text && style in BORDER_STYLE_CLASS)
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
                    'm-1 h-6 w-6 shrink-0 rounded-md border-2 border-(--color-tab-active-border)',
                    BORDER_STYLE_CLASS[entry.style],
                ]"
            ></div>
            <span class="text-xs text-(--color-text-muted)">{{ entry.text }}</span>
        </div>
    </div>
</template>
