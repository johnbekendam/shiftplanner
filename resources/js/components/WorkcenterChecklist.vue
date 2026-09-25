<script setup>
import { ref } from 'vue'
import { CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Every eligible workcenter, as { id, name, archived }.
    items: { type: Array, default: () => [] },
    // Ids of the workcenters the employee is a member of.
    selectedIds: { type: Array, default: () => [] },
    // i18n key for the empty-list message.
    emptyKey: { type: String, required: true },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:selectedIds'])

// Seeded once — the parent forces a fresh seed by remounting this
// component (a :key bump) after its own successful save.
const ids = ref([...props.selectedIds])

function toggle(item, checked) {
    if (props.disabled) return
    ids.value = checked
        ? [...ids.value, item.id]
        : ids.value.filter((id) => id !== item.id)
    emit('update:selectedIds', ids.value)
}

function itemLabel(item) {
    return item.archived ? __('workcenters.archived_suffix', { name: item.name }) : item.name
}
</script>

<template>
    <div class="space-y-2">
        <div v-for="item in items" :key="item.id" class="flex items-center gap-3">
            <CheckboxInput
                :model-value="ids.includes(item.id)"
                :disabled="disabled"
                :data-testid="`workcenter-${item.id}`"
                @update:model-value="toggle(item, $event)"
            >
                {{ itemLabel(item) }}
            </CheckboxInput>
        </div>

        <p v-if="!items.length" class="text-sm text-(--color-text-secondary)">
            {{ __(emptyKey) }}
        </p>
    </div>
</template>
