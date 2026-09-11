<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { CheckboxInput } from '@/components/ui/Input'
import Icon from '@/components/ui/Icon.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Every configured tag, as { id, name }.
    items: { type: Array, default: () => [] },
    // Ids the employee currently has selected.
    selectedIds: { type: Array, default: () => [] },
    // Base URL for the toggle, e.g. /employees/7/competences.
    endpoint: { type: String, required: true },
    // i18n key for the empty-list message.
    emptyKey: { type: String, required: true },
    // Read-only: boxes render but do not toggle (employee change lock).
    disabled: { type: Boolean, default: false },
})

// Each write keeps the component and the open tab mounted, the same as
// the holiday and availability lists.
const stay = { preserveScroll: true, preserveState: true }

// See QuestionChecklist: the checked state itself confirms success, so
// only a pending spinner and a failure outline are needed here.
const pendingId = ref(null)
const failedId = ref(null)
let failedTimeout = null

function toggle(item, checked) {
    if (props.disabled) return

    const url = `${props.endpoint}/${item.id}`
    const opts = {
        ...stay,
        onError: () => {
            failedId.value = item.id
            clearTimeout(failedTimeout)
            failedTimeout = setTimeout(() => (failedId.value = null), 2000)
        },
        onFinish: () => {
            pendingId.value = null
        },
    }

    pendingId.value = item.id
    if (checked) {
        router.put(url, {}, opts)
    } else {
        router.delete(url, opts)
    }
}
</script>

<template>
    <div class="space-y-2">
        <div v-for="item in items" :key="item.id" class="flex items-center gap-2">
            <CheckboxInput
                :model-value="selectedIds.includes(item.id)"
                :disabled="disabled || pendingId === item.id"
                :data-testid="`tag-${item.id}`"
                :class="failedId === item.id ? 'rounded outline outline-2 outline-offset-2 outline-[var(--color-badge-error-border)]' : ''"
                @update:model-value="toggle(item, $event)"
            >
                {{ item.name }}
            </CheckboxInput>
            <span v-if="pendingId === item.id" data-testid="tag-pending">
                <Icon name="arrow-path" class="size-3.5 shrink-0 animate-spin text-(--color-text-secondary)" />
            </span>
        </div>

        <p v-if="!items.length" class="text-sm text-(--color-text-secondary)">
            {{ __(emptyKey) }}
        </p>
    </div>
</template>
