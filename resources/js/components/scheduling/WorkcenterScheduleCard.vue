<script setup>
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import ShiftWeekTable from '@/components/scheduling/ShiftWeekTable.vue'

defineProps({
    workcenter: { type: Object, required: true }, // { id, name }
    // [{ shift: { id, name, start_time, end_time }, cells: [7 cell objects] }]
    schedule: { type: Array, required: true },
})
</script>

<template>
    <Card>
        <template #header>
            <div class="px-6 py-3 text-base font-semibold">{{ workcenter.name }}</div>
        </template>

        <div class="p-6">
            <template v-for="(entry, index) in schedule" :key="entry.shift.id">
                <CardSeparator v-if="index > 0" />
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-(--color-text-primary)">
                        {{ entry.shift.name }}
                        <span class="font-normal text-(--color-text-secondary)">
                            {{ entry.shift.start_time }}–{{ entry.shift.end_time }}
                        </span>
                    </h3>
                    <ShiftWeekTable :workcenter-id="workcenter.id" :shift-id="entry.shift.id" :cells="entry.cells" />
                </div>
            </template>
        </div>
    </Card>
</template>
