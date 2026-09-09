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
const H = 180
const PAD = { top: 12, right: 12, bottom: 24, left: 40 }
const plotW = W - PAD.left - PAD.right
const plotH = H - PAD.top - PAD.bottom

const Y_STEPS = 4

const maxValue = computed(() => {
    const peak = Math.max(props.target, ...props.available, 1)
    // Round up to a whole-number axis top that divides evenly into Y_STEPS,
    // so every tick lands on an integer.
    const step = Math.max(1, Math.ceil((peak * 1.1) / Y_STEPS))
    return step * Y_STEPS
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
    return Array.from({ length: Y_STEPS + 1 }, (_, k) => {
        const value = (maxValue.value / Y_STEPS) * k
        return { value, y: y(value), label: String(value) }
    })
})

// ISO-8601 week number for a "YYYY-MM-DD" string.
const isoWeek = (iso) => {
    const d = new Date(`${iso}T00:00:00Z`)
    const dayOfWeek = (d.getUTCDay() + 6) % 7 // Mon = 0
    d.setUTCDate(d.getUTCDate() - dayOfWeek + 3) // Thursday of this week
    const firstThursday = new Date(Date.UTC(d.getUTCFullYear(), 0, 4))
    const ftDay = (firstThursday.getUTCDay() + 6) % 7
    firstThursday.setUTCDate(firstThursday.getUTCDate() - ftDay + 3)
    return 1 + Math.round((d - firstThursday) / (7 * 24 * 3600 * 1000))
}

const xTicks = computed(() => {
    const ticks = []
    // Minimum horizontal gap between labels, in viewBox units, so short
    // partial weeks at the edges don't collide with the next label.
    const minGap = 30
    let prevWeek = null
    let lastX = -Infinity
    props.days.forEach((day, i) => {
        const week = isoWeek(day)
        const mondayFirstDay = i === 0 && new Date(`${day}T00:00:00Z`).getUTCDay() === 1
        const weekChanged = prevWeek !== null && week !== prevWeek
        prevWeek = week
        if (!mondayFirstDay && !weekChanged) return
        const px = x(i)
        if (px - lastX < minGap) return
        ticks.push({ x: px, label: `W${week}` })
        lastX = px
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

            <!-- Axis lines -->
            <line
                :x1="PAD.left"
                :x2="PAD.left"
                :y1="PAD.top"
                :y2="PAD.top + plotH"
                stroke="var(--color-text-secondary)"
                stroke-width="1"
            />
            <line
                :x1="PAD.left"
                :x2="W - PAD.right"
                :y1="PAD.top + plotH"
                :y2="PAD.top + plotH"
                stroke="var(--color-text-secondary)"
                stroke-width="1"
            />

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
