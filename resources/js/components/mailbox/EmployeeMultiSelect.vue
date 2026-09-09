<script setup>
import { computed, ref } from 'vue'
import { SearchInput, CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // [{ id, name, email }]
    employees: { type: Array, default: () => [] },
    // array of selected employee ids
    modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const term = ref('')

const filtered = computed(() => {
    const q = term.value.trim().toLowerCase()
    if (!q) return props.employees
    return props.employees.filter(
        (e) => e.name.toLowerCase().includes(q) || e.email.toLowerCase().includes(q),
    )
})

function isSelected(id) {
    return props.modelValue.includes(id)
}

function toggle(id, checked) {
    emit(
        'update:modelValue',
        checked ? [...props.modelValue, id] : props.modelValue.filter((x) => x !== id),
    )
}
</script>

<template>
    <div class="space-y-2">
        <SearchInput
            v-model="term"
            :placeholder="__('mailbox.compose.employees_search')"
            class="w-full"
        />

        <p class="text-xs text-(--color-text-secondary)">
            {{ __('mailbox.compose.employees_selected', { count: modelValue.length }) }}
        </p>

        <ul
            class="max-h-56 divide-y divide-(--color-table-row-separator) overflow-y-auto rounded-md border border-(--color-border)"
        >
            <li
                v-for="employee in filtered"
                :key="employee.id"
                class="flex items-center gap-3 px-3 py-2"
            >
                <CheckboxInput
                    :model-value="isSelected(employee.id)"
                    @update:model-value="(checked) => toggle(employee.id, checked)"
                />
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm text-(--color-text-primary)">{{ employee.name }}</span>
                    <span class="block truncate text-xs text-(--color-text-secondary)">{{ employee.email }}</span>
                </span>
            </li>
            <li
                v-if="!filtered.length"
                class="px-3 py-4 text-center text-sm text-(--color-text-secondary)"
            >
                {{ __('mailbox.compose.employees_empty') }}
            </li>
        </ul>
    </div>
</template>
