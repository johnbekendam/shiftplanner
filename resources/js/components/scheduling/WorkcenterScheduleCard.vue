<script setup>
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import ShiftWeekTable from '@/components/scheduling/ShiftWeekTable.vue'
import { useI18n } from '@/composables/useI18n'
import { postAsync, deleteAsync } from '@/utils/inertiaAsync'

const __ = useI18n()

const props = defineProps({
    workcenter: { type: Object, required: true }, // { id, name }
    weekStart: { type: String, required: true }, // Y-m-d, the Monday of the shown week
    published: { type: Boolean, default: false },
    // [{ shift: { id, name }, cells: [7 cell objects] }]
    schedule: { type: Array, required: true },
    // The viewed cycle's most recent run's unfulfilled spots, across every
    // workcenter/shift. [{ workcenter_id, shift_id, date, reason }]
    unfulfilled: { type: Array, default: () => [] },
})

async function togglePublish() {
    const url = `/planning/weeks/${props.weekStart}/workcenters/${props.workcenter.id}/publish`
    if (props.published) {
        await deleteAsync(url).catch(() => {})
    } else {
        await postAsync(url).catch(() => {})
    }
}
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center justify-between gap-3 px-6 py-3">
                <div class="text-base font-semibold">{{ workcenter.name }}</div>
                <ButtonDanger v-if="published" type="button" data-testid="publish-workcenter-button" @click="togglePublish">
                    {{ __('scheduling.unpublish') }}
                </ButtonDanger>
                <ButtonPrimary v-else type="button" data-testid="publish-workcenter-button" @click="togglePublish">
                    {{ __('scheduling.publish') }}
                </ButtonPrimary>
            </div>
        </template>

        <div class="p-6">
            <template v-for="(entry, index) in schedule" :key="entry.shift.id">
                <CardSeparator v-if="index > 0" />
                <div>
                    <h3 class="mb-2 text-sm font-semibold text-(--color-text-primary)">
                        {{ entry.shift.name }}
                    </h3>
                    <ShiftWeekTable
                        :workcenter-id="workcenter.id"
                        :shift-id="entry.shift.id"
                        :cells="entry.cells"
                        :unfulfilled="unfulfilled"
                        :published="published"
                    />
                </div>
            </template>
        </div>
    </Card>
</template>
