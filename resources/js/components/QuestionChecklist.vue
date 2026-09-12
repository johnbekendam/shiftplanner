<script setup>
import { ref } from 'vue'
import { CheckboxInput } from '@/components/ui/Input'

const props = defineProps({
    // Every configured question, as { id, text }.
    items: { type: Array, default: () => [] },
    // Ids of the questions the employee answered with yes.
    answeredIds: { type: Array, default: () => [] },
    // Read-only: boxes render but do not toggle (employee change lock).
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:answeredIds'])

// Seeded once — the parent forces a fresh seed by remounting this
// component (a :key bump) after its own successful save.
const answered = ref([...props.answeredIds])

function toggle(item, answer) {
    if (props.disabled) return
    answered.value = answer
        ? [...answered.value, item.id]
        : answered.value.filter((id) => id !== item.id)
    emit('update:answeredIds', answered.value)
}
</script>

<template>
    <div class="space-y-2">
        <div v-for="item in items" :key="item.id">
            <CheckboxInput
                :model-value="answered.includes(item.id)"
                :disabled="disabled"
                :data-testid="`question-${item.id}`"
                @update:model-value="toggle(item, $event)"
            >
                {{ item.text }}
            </CheckboxInput>
        </div>
    </div>
</template>
