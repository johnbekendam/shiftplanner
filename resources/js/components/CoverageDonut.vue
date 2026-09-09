<script setup>
import { computed } from 'vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Available person-hours over the period.
    available: { type: Number, default: 0 },
    // Required person-hours over the period. 0 means "no target set".
    required: { type: Number, default: 0 },
})

// ── Geometry ────────────────────────────────────────────────────────────
const R = 40
const CIRCUMFERENCE = 2 * Math.PI * R

const hasTarget = computed(() => props.required > 0)

// Clamp the drawn arc to a full ring; the label still shows the true value.
const fraction = computed(() =>
    hasTarget.value ? Math.min(Math.max(props.available / props.required, 0), 1) : 0,
)

const dashArray = computed(() => `${fraction.value * CIRCUMFERENCE} ${CIRCUMFERENCE}`)

// Once coverage reaches the target, switch the arc to the success colour.
const arcColor = computed(() =>
    hasTarget.value && props.available >= props.required
        ? 'var(--color-badge-success-text)'
        : 'var(--color-brand-bg)',
)

const label = computed(() =>
    hasTarget.value ? `${Math.round((props.available / props.required) * 100)}%` : '—',
)

const caption = computed(() =>
    __('dashboard.hours_ratio', {
        available: Math.round(props.available).toString(),
        required: Math.round(props.required).toString(),
    }),
)
</script>

<template>
    <figure class="flex flex-col items-center gap-1.5">
        <svg :viewBox="`0 0 100 100`" class="w-full" role="img" :aria-label="__('dashboard.coverage')">
            <title>{{ __('dashboard.coverage') }}</title>

            <circle
                data-testid="donut-track"
                cx="50"
                cy="50"
                :r="R"
                fill="none"
                stroke="var(--color-border)"
                stroke-width="10"
            />

            <circle
                v-if="hasTarget"
                data-testid="donut-arc"
                cx="50"
                cy="50"
                :r="R"
                fill="none"
                :stroke="arcColor"
                stroke-width="10"
                stroke-linecap="round"
                :stroke-dasharray="dashArray"
                transform="rotate(-90 50 50)"
            />

            <text
                data-testid="donut-label"
                x="50"
                y="50"
                text-anchor="middle"
                dominant-baseline="central"
                font-size="20"
                font-weight="600"
                fill="var(--color-text-primary)"
            >{{ label }}</text>
        </svg>

        <figcaption data-testid="donut-caption" class="text-xs text-(--color-text-secondary)">
            {{ caption }}
        </figcaption>
    </figure>
</template>
