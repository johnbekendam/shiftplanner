<script setup>
import LabeledInput from '@/components/LabeledInput.vue'
import { NumberInput, SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    form: { type: Object, required: true },
    shifts: { type: Array, default: () => [] },
    inheritedMinimum: { type: Number, required: true },
})

function visibilityValue(shiftId) {
    return props.form.shift_visibility.find((item) => item.shift_id === shiftId)?.override ?? null
}

function visibilityOptions(shift) {
    return [
        {
            value: null,
            label: shift.visible_by_default
                ? __('employees.settings.inherit_visible')
                : __('employees.settings.inherit_hidden'),
        },
        { value: true, label: __('employees.settings.visible') },
        { value: false, label: __('employees.settings.hidden') },
    ]
}

function updateVisibility(shiftId, override) {
    props.form.shift_visibility = props.form.shift_visibility.map((item) =>
        item.shift_id === shiftId ? { ...item, override } : item,
    )
}
</script>

<template>
    <div class="space-y-6">
        <div class="max-w-xs">
            <LabeledInput
                :label="__('employees.settings.minimum_hours')"
                :error="props.form.errors?.weekly_hours_minimum"
            >
                <NumberInput v-model="props.form.weekly_hours_minimum" :min="1" :max="48" class="w-full" />
                <p class="mt-1 text-xs text-(--color-text-secondary)">
                    {{ __('employees.settings.minimum_hours_hint', { min: props.inheritedMinimum }) }}
                </p>
            </LabeledInput>
        </div>

        <section class="space-y-3">
            <h3 class="text-sm font-semibold text-(--color-text-primary)">
                {{ __('employees.settings.shift_visibility') }}
            </h3>
            <div class="divide-y divide-(--color-table-row-separator) border-y border-(--color-table-row-separator)">
                <div
                    v-for="shift in props.shifts"
                    :key="shift.id"
                    class="grid grid-cols-[minmax(0,1fr)_12rem] items-center gap-4 py-3"
                >
                    <span class="text-sm text-(--color-text-primary)">{{ shift.name }}</span>
                    <SelectInput
                        :model-value="visibilityValue(shift.id)"
                        :options="visibilityOptions(shift)"
                        @update:model-value="updateVisibility(shift.id, $event)"
                    />
                </div>
            </div>
        </section>
    </div>
</template>