<script setup>
import { computed } from 'vue'

const props = defineProps({
    title: { type: String, required: true },
    // ISO date strings, one per point.
    days: { type: Array, default: () => [] },
    // Available FTE, one value per day.
    available: { type: Array, default: () => [] },
    // Target FTE — a flat reference line.
    target: { type: Number, default: 0 },
    // Show the title as a visible caption above the chart. Turn off when a
    // surrounding container (e.g. a card header) already names the chart.
    showCaption: { type: Boolean, default: true },
})

// ── Geometry ────────────────────────────────────────────────────────────
const W = 640
const H = 240
const PAD = { top: 12, right: 12, bottom: 24, left: 40 }
const plotW = W - PAD.left - PAD.right
const plotH = H - PAD.top - PAD.bottom

const maxValue = computed(() => {
    const peak = Math.max(props.target, ...props.available, 1)
    return peak * 1.1
})

const x = (i) => {
    const n = props.available.length
    if (n <= 1) return PAD.left + plotW / 2
    return PAD.left + (plotW * i) / (n - 1)
}
const y = (v) => PAD.top + plotH * (1 - v / maxValue.value)

const linePoints = computed(() => props.available.map((v, i) => `${x(i)},${y(v)}`).join(' '))

const targetY = computed(() => y(props.target))

// ── Axis ticks ──────────────────────────────────────────────────────────
const yTicks = computed(() => {
    const steps = 4
    return Array.from({ length: steps + 1 }, (_, k) => {
        const value = (maxValue.value / steps) * k
        return { value, y: y(value), label: value.toFixed(1) }
    })
})

const xTicks = computed(() => {
    const ticks = []
    props.days.forEach((day, i) => {
        const first = i === 0
        const last = i === props.days.length - 1
        const monthStart = day.slice(8, 10) === '01'
        if (first || last || monthStart) {
            ticks.push({ x: x(i), label: day.slice(0, 7) })
        }
    })
    return ticks
})
</script>

<template>
    <figure class="space-y-2">
        <figcaption v-if="showCaption" class="text-sm font-semibold text-(--color-text-primary)">{{ title }}</figcaption>
        <svg
            :viewBox="`0 0 ${W} ${H}`"
            class="w-full"
            role="img"
            :aria-label="title"
            preserveAspectRatio="none"
        >
            <title>{{ title }}</title>

            <!-- Horizontal gridlines and Y labels -->
            <g>
                <line
                    v-for="tick in yTicks"
                    :key="`grid-${tick.value}`"
                    :x1="PAD.left"
                    :x2="W - PAD.right"
                    :y1="tick.y"
                    :y2="tick.y"
                    stroke="var(--color-border)"
                    stroke-width="1"
                />
                <text
                    v-for="tick in yTicks"
                    :key="`ylabel-${tick.value}`"
                    :x="PAD.left - 6"
                    :y="tick.y + 3"
                    text-anchor="end"
                    font-size="10"
                    fill="var(--color-text-secondary)"
                >{{ tick.label }}</text>
            </g>

            <!-- X labels -->
            <text
                v-for="(tick, i) in xTicks"
                :key="`xlabel-${i}`"
                :x="tick.x"
                :y="H - 6"
                text-anchor="middle"
                font-size="10"
                fill="var(--color-text-secondary)"
            >{{ tick.label }}</text>

            <!-- Target reference -->
            <line
                data-testid="target-line"
                :x1="PAD.left"
                :x2="W - PAD.right"
                :y1="targetY"
                :y2="targetY"
                stroke="var(--color-text-secondary)"
                stroke-width="1.5"
                stroke-dasharray="4 3"
            />

            <!-- Available FTE series -->
            <polyline
                data-testid="fte-line"
                :points="linePoints"
                fill="none"
                stroke="var(--color-brand-bg)"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
        </svg>
    </figure>
</template>
