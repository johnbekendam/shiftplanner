<script setup>
import { computed, ref } from 'vue'
import { SearchInput } from '@/components/ui/Input'
import Icon from '@/components/ui/Icon.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // [{ id, name, email }]
    employees: { type: Array, default: () => [] },
    // [{ id, name, email }]
    users: { type: Array, default: () => [] },
    // { employee_ids: number[], user_ids: number[] }
    modelValue: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue'])

const sources = ['employee', 'user']
const activeSource = ref('employee')
const term = ref('')

function idsFor(source) {
    return source === 'employee' ? props.modelValue.employee_ids : props.modelValue.user_ids
}

const sourceList = computed(() => (activeSource.value === 'employee' ? props.employees : props.users))

// Already-selected people drop out of the source list — they live in the
// selected list below instead.
const available = computed(() => sourceList.value.filter((person) => !idsFor(activeSource.value).includes(person.id)))

const filtered = computed(() => {
    const q = term.value.trim().toLowerCase()
    if (!q) return available.value
    return available.value.filter(
        (person) => person.name.toLowerCase().includes(q) || person.email.toLowerCase().includes(q),
    )
})

function setIds(source, ids) {
    emit('update:modelValue', {
        employee_ids: source === 'employee' ? ids : props.modelValue.employee_ids,
        user_ids: source === 'user' ? ids : props.modelValue.user_ids,
    })
}

function add(source, id) {
    setIds(source, [...idsFor(source), id])
}

function addAll(source) {
    setIds(source, [...idsFor(source), ...filtered.value.map((person) => person.id)])
}

function remove(source, id) {
    setIds(source, idsFor(source).filter((x) => x !== id))
}

const selected = computed(() => [
    ...props.employees
        .filter((employee) => props.modelValue.employee_ids.includes(employee.id))
        .map((employee) => ({ ...employee, source: 'employee' })),
    ...props.users
        .filter((user) => props.modelValue.user_ids.includes(user.id))
        .map((user) => ({ ...user, source: 'user' })),
])
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <div class="flex gap-2">
                <button
                    v-for="source in sources"
                    :key="source"
                    type="button"
                    class="rounded-full px-3 py-1 text-xs font-medium"
                    :class="activeSource === source
                        ? 'bg-(--color-tab-active-border) text-(--color-btn-primary-text)'
                        : 'bg-(--color-badge-standard-bg) text-(--color-badge-standard-text)'"
                    @click="activeSource = source"
                >
                    {{ __(`mailbox.compose.source.${source}s`) }}
                </button>
            </div>

            <button
                v-if="filtered.length"
                type="button"
                class="shrink-0 text-xs font-medium text-(--color-tab-active-border) hover:opacity-80"
                @click="addAll(activeSource)"
            >
                {{ __('mailbox.compose.recipient_add_all') }}
            </button>
        </div>

        <SearchInput
            v-model="term"
            :placeholder="__('mailbox.compose.recipients_search')"
            class="w-full"
        />

        <ul
            class="max-h-56 divide-y divide-(--color-table-row-separator) overflow-y-auto rounded-md border border-(--color-border)"
        >
            <li
                v-for="person in filtered"
                :key="person.id"
                class="flex items-center gap-3 px-3 py-2"
            >
                <button
                    type="button"
                    class="shrink-0 text-(--color-text-secondary) hover:text-(--color-text-primary)"
                    :aria-label="__('mailbox.compose.recipient_add')"
                    @click="add(activeSource, person.id)"
                >
                    <Icon name="plus-circle" class="size-5 opacity-60" />
                </button>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm text-(--color-text-primary)">{{ person.name }}</span>
                    <span class="block truncate text-xs text-(--color-text-secondary)">{{ person.email }}</span>
                </span>
            </li>
            <li
                v-if="!filtered.length"
                class="px-3 py-4 text-center text-sm text-(--color-text-secondary)"
            >
                {{ __('mailbox.compose.recipients_empty') }}
            </li>
        </ul>

        <div class="space-y-1">
            <p class="text-xs text-(--color-text-secondary)">
                {{ selected.length
                    ? __('mailbox.compose.recipients_selected', { count: selected.length })
                    : __('mailbox.compose.recipients_selected_empty') }}
            </p>

            <ul v-if="selected.length" class="space-y-1">
                <li
                    v-for="person in selected"
                    :key="`${person.source}-${person.id}`"
                    class="flex items-center gap-2 rounded-md border border-(--color-border) px-2.5 py-1.5"
                >
                    <span
                        class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium bg-(--color-badge-standard-bg) text-(--color-badge-standard-text)"
                    >
                        {{ __(`mailbox.badge.${person.source}`) }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm text-(--color-text-primary)">
                        {{ person.name }}
                        <span class="text-(--color-text-secondary)">{{ person.email }}</span>
                    </span>
                    <button
                        type="button"
                        class="shrink-0 text-(--color-text-secondary) hover:text-(--color-text-primary)"
                        :aria-label="__('mailbox.compose.recipient_remove')"
                        @click="remove(person.source, person.id)"
                    >
                        <Icon name="x-mark" class="size-4" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
