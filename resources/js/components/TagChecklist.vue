<script setup>
import { router } from '@inertiajs/vue3'
import { CheckboxInput } from '@/components/ui/Input'
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
    // Shared useSaveStatus() tracker for the card body's SaveStatusBadge.
    saveStatus: { type: Object, default: null },
})

// Each write keeps the component and the open tab mounted, the same as
// the holiday and availability lists.
const stay = { preserveScroll: true, preserveState: true }

function toggle(item, checked) {
    if (props.disabled) return

    const url = `${props.endpoint}/${item.id}`
    const opts = {
        ...stay,
        onSuccess: () => props.saveStatus?.succeed(),
        onError: () => props.saveStatus?.fail(),
    }

    props.saveStatus?.start()
    if (checked) {
        router.put(url, {}, opts)
    } else {
        router.delete(url, opts)
    }
}
</script>

<template>
    <div class="space-y-2">
        <div v-for="item in items" :key="item.id">
            <CheckboxInput
                :model-value="selectedIds.includes(item.id)"
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
