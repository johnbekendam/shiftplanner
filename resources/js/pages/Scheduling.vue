<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Calendar from '@/components/ui/Calendar.vue'
import { CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    workcenters: { type: Array, default: () => [] }, // { id, name }, active only
    shifts: { type: Array, default: () => [] }, // { id, name }, all shift definitions
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    coverage: { type: Array, default: () => [] }, // { workcenter_id, shift_id, date, spots, assigned }
})

const checkedWorkcenterIds = ref(props.workcenters.map((w) => w.id))
const checkedShiftIds = ref(props.shifts.map((s) => s.id))

function toggleWorkcenter(id, checked) {
    checkedWorkcenterIds.value = checked
        ? [...checkedWorkcenterIds.value, id]
        : checkedWorkcenterIds.value.filter((x) => x !== id)
}

function toggleShift(id, checked) {
    checkedShiftIds.value = checked
        ? [...checkedShiftIds.value, id]
        : checkedShiftIds.value.filter((x) => x !== id)
}

const daysInMonth = computed(() => new Date(props.year, props.month, 0).getDate())

const dayStates = computed(() => {
    const states = {}
    for (let day = 1; day <= daysInMonth.value; day++) {
        const dateStr = `${props.year}-${String(props.month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
        const relevant = props.coverage.filter(
            (c) =>
                c.date === dateStr &&
                checkedWorkcenterIds.value.includes(c.workcenter_id) &&
                checkedShiftIds.value.includes(c.shift_id),
        )
        if (!relevant.length) {
            states[day] = 'muted'
        } else {
            states[day] = relevant.every((c) => c.assigned >= c.spots) ? 'success' : 'warning'
        }
    }
    return states
})

const legenda = computed(() => ({
    success: __('scheduling.legend_staffed'),
    warning: __('scheduling.legend_open_spots'),
}))

function onCalendarChange({ year, month }) {
    if (year !== props.year || month !== props.month) {
        router.get('/scheduling', { year, month }, { preserveState: true, preserveScroll: true })
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="__('scheduling.title')" />

        <div class="mx-auto max-w-4xl">
            <p v-if="!workcenters.length" class="py-6 text-center text-(--color-text-secondary)">
                {{ __('scheduling.no_workcenters') }}
            </p>
            <div v-else class="flex flex-col items-start gap-4 lg:flex-row">
                <Calendar
                    :year="year"
                    :month="month"
                    :day-states="dayStates"
                    :legenda="legenda"
                    :enable-day-selection="false"
                    :enable-week-day-selection="false"
                    @change="onCalendarChange"
                />

                <div class="flex w-full flex-col gap-4 sm:flex-row lg:w-auto">
                    <Card class="w-full sm:w-auto">
                        <template #header>
                            <div class="px-6 py-3 text-base font-semibold">{{ __('scheduling.filter_workcenters') }}</div>
                        </template>

                        <div class="flex flex-col gap-1.5 p-6">
                            <CheckboxInput
                                v-for="workcenter in workcenters"
                                :key="workcenter.id"
                                :model-value="checkedWorkcenterIds.includes(workcenter.id)"
                                @update:model-value="(checked) => toggleWorkcenter(workcenter.id, checked)"
                            >
                                <span class="whitespace-nowrap">{{ workcenter.name }}</span>
                            </CheckboxInput>
                        </div>
                    </Card>

                    <Card class="w-full sm:w-48">
                        <template #header>
                            <div class="px-6 py-3 text-base font-semibold">{{ __('scheduling.filter_shifts') }}</div>
                        </template>

                        <div class="flex flex-col gap-1.5 p-6">
                            <CheckboxInput
                                v-for="shift in shifts"
                                :key="shift.id"
                                :model-value="checkedShiftIds.includes(shift.id)"
                                @update:model-value="(checked) => toggleShift(shift.id, checked)"
                            >
                                {{ shift.name }}
                            </CheckboxInput>
                        </div>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
