<script setup>
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import ShiftWeekTable from '@/components/scheduling/ShiftWeekTable.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    workcenter: { type: Object, required: true }, // { id, name }
    weekPublished: { type: Boolean, default: false },
    // [{ shift: { id, name }, cells: [7 cell objects] }]
    schedule: { type: Array, required: true },
})
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center justify-between gap-3 px-6 py-3">
                <div class="text-base font-semibold">{{ workcenter.name }}</div>
                <span
                    v-if="weekPublished"
                    class="rounded-full border border-(--color-badge-warning-border) bg-(--color-badge-warning-bg) px-2 py-0.5 text-xs font-medium text-(--color-badge-warning-text)"
                >
                    {{ __('scheduling.published_label') }}
                </span>
            </div>
        </template>

        <div class="p-6">
            <template v-for="(entry, index) in schedule" :key="entry.shift.id">
                <CardSeparator v-if="index > 0" />
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-(--color-text-primary)">
                        {{ entry.shift.name }}
                    </h3>
                    <ShiftWeekTable :workcenter-id="workcenter.id" :shift-id="entry.shift.id" :cells="entry.cells" />
                </div>
            </template>
        </div>
    </Card>
</template>
