<script setup>
import Card from '@/components/ui/Card.vue'
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

        <div class="flex flex-col gap-6 p-6">
            <div v-for="entry in schedule" :key="entry.shift.id">
                <h3 class="mb-2 text-sm font-semibold text-(--color-text-primary)">
                    {{ entry.shift.name }}
                    <span class="font-normal text-(--color-text-secondary)">
                        {{ entry.shift.start_time }}–{{ entry.shift.end_time }}
                    </span>
                </h3>
                <ShiftWeekTable :workcenter-id="workcenter.id" :shift-id="entry.shift.id" :cells="entry.cells" />
            </div>
        </div>
    </Card>
</template>
