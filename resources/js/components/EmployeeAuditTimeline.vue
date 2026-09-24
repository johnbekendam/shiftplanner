<script setup>
import { computed, ref } from 'vue'
import Icon from '@/components/ui/Icon.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    events: { type: Array, default: () => [] },
})

const expandedIds = ref([])

function isExpanded(id) {
    return expandedIds.value.includes(id)
}

function toggle(id) {
    expandedIds.value = isExpanded(id)
        ? expandedIds.value.filter((eventId) => eventId !== id)
        : [...expandedIds.value, id]
}

function actor(event) {
    return event.actor_name || event.actor_email || __('employees.audit.system_actor')
}

function formatDate(value) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

function formatValue(value, present) {
    if (!present || value === null || value === '') return __('employees.audit.not_set')
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
}

function hasValue(values, field) {
    return Object.prototype.hasOwnProperty.call(values, field)
}

const fieldsByEvent = computed(() => Object.fromEntries(props.events.map((event) => [
    event.id,
    [...new Set([
        ...Object.keys(event.old_values ?? {}),
        ...Object.keys(event.new_values ?? {}),
    ])],
])))
</script>

<template>
    <p v-if="events.length === 0" class="text-sm text-(--color-text-muted)">
        {{ __('employees.audit.empty') }}
    </p>

    <ol v-else class="divide-y divide-(--color-border-default)">
        <li
            v-for="event in events"
            :key="event.id"
            :data-testid="`audit-event-${event.id}`"
            class="py-4 first:pt-0 last:pb-0"
        >
            <button
                type="button"
                class="flex w-full items-start justify-between gap-4 text-left"
                :aria-expanded="isExpanded(event.id)"
                @click="toggle(event.id)"
            >
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-(--color-text-primary)">
                        {{ __(`employees.audit.action.${event.action}`) }}
                    </span>
                    <span class="mt-1 block text-xs text-(--color-text-muted)">
                        {{ formatDate(event.created_at) }} · {{ __('employees.audit.by', { actor: actor(event) }) }}
                    </span>
                    <span class="mt-1 block text-xs text-(--color-text-muted)">
                        {{ __(`employees.audit.source.${event.source}`) }}
                    </span>
                </span>
                <Icon :name="isExpanded(event.id) ? 'chevron-up' : 'chevron-down'" class="mt-1 size-4 shrink-0" />
            </button>

            <div v-if="isExpanded(event.id)" class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-xs text-(--color-text-muted)">
                        <tr>
                            <th class="pb-2 pr-4 font-medium"></th>
                            <th class="pb-2 pr-4 font-medium">{{ __('employees.audit.before') }}</th>
                            <th class="pb-2 font-medium">{{ __('employees.audit.after') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-(--color-border-default)">
                        <tr v-for="field in fieldsByEvent[event.id]" :key="field">
                            <th class="py-2 pr-4 font-medium text-(--color-text-primary)">{{ field }}</th>
                            <td class="py-2 pr-4 text-(--color-text-secondary)">
                                {{ formatValue(event.old_values?.[field], hasValue(event.old_values ?? {}, field)) }}
                            </td>
                            <td class="py-2 text-(--color-text-secondary)">
                                {{ formatValue(event.new_values?.[field], hasValue(event.new_values ?? {}, field)) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </li>
    </ol>
</template>
