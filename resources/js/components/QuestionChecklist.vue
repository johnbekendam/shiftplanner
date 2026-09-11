<script setup>
import { router } from '@inertiajs/vue3'
import { CheckboxInput } from '@/components/ui/Input'

const props = defineProps({
    // Every configured question, as { id, text }.
    items: { type: Array, default: () => [] },
    // Ids of the questions the employee answered with yes.
    answeredIds: { type: Array, default: () => [] },
    // Base URL for the toggle, e.g. /employees/7/questions.
    endpoint: { type: String, required: true },
    // Read-only: boxes render but do not toggle (employee change lock).
    disabled: { type: Boolean, default: false },
    // Shared useSaveStatus() tracker for the card body's SaveStatusBadge.
    saveStatus: { type: Object, default: null },
})

// Each write keeps the component and the open tab mounted, the same as
// the availability grid and the holiday list.
const stay = { preserveScroll: true, preserveState: true }

function toggle(item, answer) {
    if (props.disabled) return
    props.saveStatus?.start()
    router.put(`${props.endpoint}/${item.id}`, { answer }, {
        ...stay,
        onSuccess: () => props.saveStatus?.succeed(),
        onError: () => props.saveStatus?.fail(),
    })
}
</script>

<template>
    <div class="space-y-2">
        <div v-for="item in items" :key="item.id">
            <CheckboxInput
                :model-value="answeredIds.includes(item.id)"
                :disabled="disabled"
                :data-testid="`question-${item.id}`"
                @update:model-value="toggle(item, $event)"
            >
                {{ item.text }}
            </CheckboxInput>
        </div>
    </div>
</template>
