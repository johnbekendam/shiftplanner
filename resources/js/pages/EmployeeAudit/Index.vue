<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Icon from '@/components/ui/Icon.vue'
import { DateInput, SearchInput, SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    events: { type: Object, required: true },
    filters: { type: Object, required: true },
    actions: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
})

const employee = ref(props.filters.employee ?? '')
const actor = ref(props.filters.actor ?? '')
const action = ref(props.filters.action ?? null)
const source = ref(props.filters.source ?? null)
const from = ref(props.filters.from ?? '')
const to = ref(props.filters.to ?? '')
const expandedIds = ref([])

const actionOptions = computed(() => [
    { value: null, label: __('audit.filter.all_actions') },
    ...props.actions.map((value) => ({ value, label: __(`employees.audit.action.${value}`) })),
])
const sourceOptions = computed(() => [
    { value: null, label: __('audit.filter.all_sources') },
    ...props.sources.map((value) => ({ value, label: __(`employees.audit.source.${value}`) })),
])

function query() {
    const result = {}
    const employeeSearch = employee.value.trim()
    const actorSearch = actor.value.trim()
    if (employeeSearch) result.employee = employeeSearch
    if (actorSearch) result.actor = actorSearch
    if (action.value) result.action = action.value
    if (source.value) result.source = source.value
    if (from.value) result.from = from.value
    if (to.value) result.to = to.value
    return result
}

function reload() {
    router.get('/employee-audit', query(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

let searchTimer = null
watch([employee, actor], () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(reload, 250)
})
watch([action, source, from, to], reload)
onBeforeUnmount(() => clearTimeout(searchTimer))

function toggle(id) {
    expandedIds.value = expandedIds.value.includes(id)
        ? expandedIds.value.filter((eventId) => eventId !== id)
        : [...expandedIds.value, id]
}

function isExpanded(id) {
    return expandedIds.value.includes(id)
}

function actorName(event) {
    return event.actor_name || event.actor_email || __('employees.audit.system_actor')
}

function formatDate(value) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value))
}

function fields(event) {
    return [...new Set([
        ...Object.keys(event.old_values ?? {}),
        ...Object.keys(event.new_values ?? {}),
    ])]
}

function hasValue(values, field) {
    return Object.prototype.hasOwnProperty.call(values, field)
}

function formatValue(value, present) {
    if (!present || value === null || value === '') return __('employees.audit.not_set')
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
}

const paginationRange = computed(() => __('audit.pagination.range', {
    from: props.events.from ?? 0,
    to: props.events.to ?? 0,
    total: props.events.total ?? props.events.data.length,
}))

const paginationLinks = computed(() => {
    const links = props.events.links ?? []
    const lastIndex = links.length - 1
    return links.map((link, index) => ({
        ...link,
        key: `${index}-${link.label}-${link.url ?? 'disabled'}`,
        label: index === 0 ? '‹' : index === lastIndex ? '›' : link.label,
        ariaLabel: index === 0
            ? __('audit.pagination.prev')
            : index === lastIndex
                ? __('audit.pagination.next')
                : __('audit.pagination.page_label', { page: link.label }),
        disabled: !link.url || link.active,
        edge: index === 0 ? 'first' : index === lastIndex ? 'last' : null,
    }))
})

function paginationLinkClass(link) {
    const classes = ['inline-flex min-w-9 items-center justify-center px-3 py-1.5 text-sm outline outline-1 -outline-offset-1']
    if (link.edge === 'first') classes.push('rounded-l-md')
    if (link.edge === 'last') classes.push('rounded-r-md')
    if (link.active) {
        classes.push('bg-[var(--color-pagination-active-bg)] text-[var(--color-pagination-active-text)] outline-[var(--color-pagination-active-border)] font-semibold')
    } else if (link.disabled) {
        classes.push('cursor-not-allowed bg-[var(--color-pagination-bg)] text-[var(--color-pagination-muted-text)] outline-[var(--color-pagination-border)]')
    } else {
        classes.push('bg-[var(--color-pagination-bg)] text-[var(--color-pagination-text)] outline-[var(--color-pagination-border)] hover:bg-[var(--color-pagination-hover-bg)] hover:text-[var(--color-pagination-hover-text)] hover:outline-[var(--color-pagination-hover-border)]')
    }
    return classes
}

function goToPage(url) {
    if (url) router.get(url, {}, { preserveState: true })
}
</script>

<template>
    <AppLayout>
        <Head :title="__('audit.title')" />

        <Card class="max-w-6xl">
            <template #header>
                <div class="px-6 py-3 text-base font-semibold">{{ __('audit.title') }}</div>
            </template>

            <div class="space-y-5 p-6">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <SearchInput v-model="employee" :placeholder="__('audit.search_employee')" />
                    <SearchInput v-model="actor" :placeholder="__('audit.search_actor')" />
                    <SelectInput v-model="action" :options="actionOptions" :placeholder="__('audit.filter.action')" />
                    <SelectInput v-model="source" :options="sourceOptions" :placeholder="__('audit.filter.source')" />
                    <label class="space-y-1 text-xs text-(--color-text-secondary)">
                        <span>{{ __('audit.filter.from') }}</span>
                        <DateInput v-model="from" format="ymd" />
                    </label>
                    <label class="space-y-1 text-xs text-(--color-text-secondary)">
                        <span>{{ __('audit.filter.to') }}</span>
                        <DateInput v-model="to" format="ymd" />
                    </label>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-3xl table-fixed text-sm">
                        <thead>
                            <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                                <th class="w-40 py-2 pr-3">{{ __('audit.column.when') }}</th>
                                <th class="w-44 py-2 pr-3">{{ __('audit.column.employee') }}</th>
                                <th class="w-48 py-2 pr-3">{{ __('audit.column.action') }}</th>
                                <th class="w-44 py-2 pr-3">{{ __('audit.column.actor') }}</th>
                                <th class="w-44 py-2 pr-3">{{ __('audit.column.source') }}</th>
                                <th class="w-10 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="event in events.data" :key="event.id">
                                <tr
                                    :data-testid="`audit-row-${event.id}`"
                                    class="border-b border-(--color-table-row-separator) text-(--color-table-row-text)"
                                >
                                    <td class="py-3 pr-3">{{ formatDate(event.created_at) }}</td>
                                    <td class="py-3 pr-3 font-medium">{{ event.employee_name }}</td>
                                    <td class="py-3 pr-3">{{ __(`employees.audit.action.${event.action}`) }}</td>
                                    <td class="py-3 pr-3">{{ actorName(event) }}</td>
                                    <td class="py-3 pr-3">{{ __(`employees.audit.source.${event.source}`) }}</td>
                                    <td class="py-3 text-right">
                                        <button
                                            type="button"
                                            class="inline-flex size-8 items-center justify-center rounded-md text-(--color-table-row-text) hover:bg-(--color-table-row-hover-bg)"
                                            :aria-expanded="isExpanded(event.id)"
                                            @click="toggle(event.id)"
                                        >
                                            <Icon :name="isExpanded(event.id) ? 'chevron-up' : 'chevron-down'" class="size-4" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="isExpanded(event.id)" :data-testid="`audit-details-${event.id}`">
                                    <td colspan="6" class="border-b border-(--color-table-row-separator) bg-(--color-table-row-hover-bg) px-4 py-3">
                                        <table class="w-full text-left text-sm">
                                            <thead class="text-xs text-(--color-text-muted)">
                                                <tr>
                                                    <th class="pb-2 pr-4 font-medium"></th>
                                                    <th class="pb-2 pr-4 font-medium">{{ __('employees.audit.before') }}</th>
                                                    <th class="pb-2 font-medium">{{ __('employees.audit.after') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-(--color-table-row-separator)">
                                                <tr v-for="field in fields(event)" :key="field">
                                                    <th class="py-2 pr-4 font-medium">{{ field }}</th>
                                                    <td class="py-2 pr-4">{{ formatValue(event.old_values?.[field], hasValue(event.old_values ?? {}, field)) }}</td>
                                                    <td class="py-2">{{ formatValue(event.new_values?.[field], hasValue(event.new_values ?? {}, field)) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="events.data.length === 0">
                                <td colspan="6" class="py-10 text-center text-(--color-text-secondary)">{{ __('audit.empty') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="text-sm text-(--color-text-secondary)">{{ paginationRange }}</span>
                    <div class="inline-flex">
                        <button
                            v-for="link in paginationLinks"
                            :key="link.key"
                            type="button"
                            :aria-label="link.ariaLabel"
                            :disabled="link.disabled"
                            :class="paginationLinkClass(link)"
                            @click="goToPage(link.url)"
                        >
                            {{ link.label }}
                        </button>
                    </div>
                </div>
            </div>
        </Card>
    </AppLayout>
</template>
