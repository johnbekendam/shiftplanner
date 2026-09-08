<script setup>
import { router } from '@inertiajs/vue3'
import { CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Every configured competence, as { id, name }.
    competences: { type: Array, default: () => [] },
    // Ids the employee currently holds.
    selectedIds: { type: Array, default: () => [] },
    // Base URL for the toggle, e.g. /employees/7/competences.
    endpoint: { type: String, required: true },
})

// Each write keeps the component and the open tab mounted, the same as
// the holiday and availability lists.
const stay = { preserveScroll: true, preserveState: true }

function toggle(competence, checked) {
    const url = `${props.endpoint}/${competence.id}`
    if (checked) {
        router.put(url, {}, stay)
    } else {
        router.delete(url, stay)
    }
}
</script>

<template>
    <div class="space-y-2">
        <div v-for="competence in competences" :key="competence.id">
            <CheckboxInput
                :model-value="selectedIds.includes(competence.id)"
                :data-testid="`competence-${competence.id}`"
                @update:model-value="toggle(competence, $event)"
            >
                {{ competence.name }}
            </CheckboxInput>
        </div>

        <p v-if="!competences.length" class="text-sm text-(--color-text-secondary)">
            {{ __('competences.checklist_empty') }}
        </p>
    </div>
</template>
