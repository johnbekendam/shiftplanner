<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { CheckboxInput } from '@/components/ui/Input'
import Icon from '@/components/ui/Icon.vue'

const props = defineProps({
    // Every configured question, as { id, text }.
    items: { type: Array, default: () => [] },
    // Ids of the questions the employee answered with yes.
    answeredIds: { type: Array, default: () => [] },
    // Base URL for the toggle, e.g. /employees/7/questions.
    endpoint: { type: String, required: true },
    // Read-only: boxes render but do not toggle (employee change lock).
    disabled: { type: Boolean, default: false },
})

// Each write keeps the component and the open tab mounted, the same as
// the availability grid and the holiday list.
const stay = { preserveScroll: true, preserveState: true }

// The checked state comes straight from the answeredIds prop (no local
// optimistic flip), so a spinner is the only signal that anything is
// happening while a toggle is in flight. Success needs no flash of its
// own — the box settling into its new state already confirms it; a
// failed save gets a brief red outline instead.
const pendingId = ref(null)
const failedId = ref(null)
let failedTimeout = null

function toggle(item, answer) {
    if (props.disabled) return

    pendingId.value = item.id
    router.put(`${props.endpoint}/${item.id}`, { answer }, {
        ...stay,
        onError: () => {
            failedId.value = item.id
            clearTimeout(failedTimeout)
            failedTimeout = setTimeout(() => (failedId.value = null), 2000)
        },
        onFinish: () => {
            pendingId.value = null
        },
    })
}
</script>

<template>
    <div class="space-y-2">
        <div v-for="item in items" :key="item.id" class="flex items-center gap-2">
            <CheckboxInput
                :model-value="answeredIds.includes(item.id)"
                :disabled="disabled || pendingId === item.id"
                :data-testid="`question-${item.id}`"
                :class="failedId === item.id ? 'rounded outline outline-2 outline-offset-2 outline-[var(--color-badge-error-border)]' : ''"
                @update:model-value="toggle(item, $event)"
            >
                {{ item.text }}
            </CheckboxInput>
            <span v-if="pendingId === item.id" data-testid="question-pending">
                <Icon name="arrow-path" class="size-3.5 shrink-0 animate-spin text-(--color-text-secondary)" />
            </span>
        </div>
    </div>
</template>
