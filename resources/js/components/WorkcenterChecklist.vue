<script setup>
import { ref } from 'vue'
import { CheckboxInput, SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Every eligible workcenter, as { id, name, archived }.
    items: { type: Array, default: () => [] },
    // Rows the employee currently holds, as { workcenter_id, mode }.
    selectedRows: { type: Array, default: () => [] },
    // i18n key for the empty-list message.
    emptyKey: { type: String, required: true },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:selectedRows'])

const modeOptions = [
    { value: 'hard', label: __('workcenters.mode.hard') },
    { value: 'soft', label: __('workcenters.mode.soft') },
]

// Seeded once — the parent forces a fresh seed by remounting this
// component (a :key bump) after its own successful save.
const rows = ref(props.selectedRows.map((row) => ({ ...row })))

function modeFor(itemId) {
    return rows.value.find((row) => row.workcenter_id === itemId)?.mode ?? null
}

function toggle(item, checked) {
    if (props.disabled) return
    rows.value = checked
        ? [...rows.value, { workcenter_id: item.id, mode: 'hard' }]
        : rows.value.filter((row) => row.workcenter_id !== item.id)
    emit('update:selectedRows', rows.value)
}

function setMode(item, mode) {
    if (props.disabled) return
    rows.value = rows.value.map((row) => (row.workcenter_id === item.id ? { ...row, mode } : row))
    emit('update:selectedRows', rows.value)
}

function itemLabel(item) {
    return item.archived ? __('workcenters.archived_suffix', { name: item.name }) : item.name
}
</script>

<template>
    <div class="space-y-2">
        <div v-for="item in items" :key="item.id" class="flex items-center gap-3">
            <CheckboxInput
                :model-value="modeFor(item.id) !== null"
                :disabled="disabled"
                :data-testid="`workcenter-${item.id}`"
                @update:model-value="toggle(item, $event)"
            >
                {{ itemLabel(item) }}
            </CheckboxInput>
            <SelectInput
                v-if="modeFor(item.id) !== null"
                :model-value="modeFor(item.id)"
                :options="modeOptions"
                :disabled="disabled"
                class="w-32"
                @update:model-value="setMode(item, $event)"
            />
        </div>

        <p v-if="!items.length" class="text-sm text-(--color-text-secondary)">
            {{ __(emptyKey) }}
        </p>
    </div>
</template>
