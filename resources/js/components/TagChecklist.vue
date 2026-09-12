<script setup>
import { ref } from 'vue'
import { CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Every configured tag, as { id, name }.
    items: { type: Array, default: () => [] },
    // Ids the employee currently has selected.
    selectedIds: { type: Array, default: () => [] },
    // i18n key for the empty-list message.
    emptyKey: { type: String, required: true },
    // Read-only: boxes render but do not toggle (employee change lock).
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:selectedIds'])

// Seeded once — the parent forces a fresh seed by remounting this
// component (a :key bump) after its own successful save.
const selected = ref([...props.selectedIds])

function toggle(item, checked) {
    if (props.disabled) return
    selected.value = checked
        ? [...selected.value, item.id]
        : selected.value.filter((id) => id !== item.id)
    emit('update:selectedIds', selected.value)
}
</script>

<template>
    <div class="space-y-2">
        <div v-for="item in items" :key="item.id">
            <CheckboxInput
                :model-value="selected.includes(item.id)"
                :disabled="disabled"
                :data-testid="`tag-${item.id}`"
                @update:model-value="toggle(item, $event)"
            >
                {{ item.name }}
            </CheckboxInput>
        </div>

        <p v-if="!items.length" class="text-sm text-(--color-text-secondary)">
            {{ __(emptyKey) }}
        </p>
    </div>
</template>
