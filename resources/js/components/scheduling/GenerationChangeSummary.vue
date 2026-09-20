<script setup>
import { ref, computed } from 'vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import Icon from '@/components/ui/Icon.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // [{ type: 'added'|'removed', employee_id, employee_name, workcenter_name, shift_name, date }]
    // from the viewed cycle's most recent done run.
    changes: { type: Array, default: () => [] },
})

const dismissed = ref(false)
const expanded = ref(false)

function formatDate(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })
}

function cellLabel(change) {
    return `${change.workcenter_name} / ${change.shift_name} / ${formatDate(change.date)}`
}

// Same employee holding both a removed and an added entry reads as a move —
// paired in order; any leftover from an uneven count stays a plain add/remove.
const grouped = computed(() => {
    const byEmployee = new Map()
    for (const change of props.changes) {
        if (!byEmployee.has(change.employee_id)) byEmployee.set(change.employee_id, { added: [], removed: [] })
        byEmployee.get(change.employee_id)[change.type === 'added' ? 'added' : 'removed'].push(change)
    }

    const moved = []
    const added = []
    const removed = []
    for (const entry of byEmployee.values()) {
        const pairCount = Math.min(entry.added.length, entry.removed.length)
        for (let i = 0; i < pairCount; i++) moved.push({ from: entry.removed[i], to: entry.added[i] })
        added.push(...entry.added.slice(pairCount))
        removed.push(...entry.removed.slice(pairCount))
    }

    return { moved, added, removed }
})

const summary = computed(() =>
    [
        grouped.value.added.length ? __('planning.change_summary.added', { count: grouped.value.added.length }) : null,
        grouped.value.moved.length ? __('planning.change_summary.moved', { count: grouped.value.moved.length }) : null,
        grouped.value.removed.length ? __('planning.change_summary.removed', { count: grouped.value.removed.length }) : null,
    ]
        .filter(Boolean)
        .join(', '),
)
</script>

<template>
    <Card v-if="!dismissed && changes.length" data-testid="generation-change-summary" class="mb-4">
        <div class="flex items-center justify-between gap-3 p-4">
            <button
                type="button"
                data-testid="toggle-change-details"
                class="flex items-center gap-1.5 text-sm font-medium"
                @click="expanded = !expanded"
            >
                <span>{{ summary }}</span>
                <Icon :name="expanded ? 'chevron-up' : 'chevron-down'" class="size-4" />
            </button>
            <button
                type="button"
                data-testid="dismiss-change-summary"
                :aria-label="__('planning.change_summary.dismiss')"
                @click="dismissed = true"
            >
                <Icon name="x-mark" class="size-4" />
            </button>
        </div>

        <template v-if="expanded">
            <CardSeparator />
            <ul class="space-y-1 p-4 text-sm">
                <li v-for="(move, i) in grouped.moved" :key="`moved-${i}`">
                    {{ __('planning.change_summary.moved_line', {
                        employee: move.from.employee_name,
                        from: cellLabel(move.from),
                        to: cellLabel(move.to),
                    }) }}
                </li>
                <li v-for="(change, i) in grouped.added" :key="`added-${i}`">
                    {{ __('planning.change_summary.added_line', { employee: change.employee_name, cell: cellLabel(change) }) }}
                </li>
                <li v-for="(change, i) in grouped.removed" :key="`removed-${i}`">
                    {{ __('planning.change_summary.removed_line', { employee: change.employee_name, cell: cellLabel(change) }) }}
                </li>
            </ul>
        </template>
    </Card>
</template>
