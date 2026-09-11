<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import Icon from '@/components/ui/Icon.vue'
import { CheckboxInput, SearchInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    employees: { type: Object, required: true },
    search: { type: String, default: '' },
    sort: { type: String, default: 'name' },
    direction: { type: String, default: 'asc' },
})

const columns = [
    { key: 'name', label: 'employees.column.name', sortable: true },
    { key: 'business_line', label: 'employees.column.business_line', sortable: true },
    { key: 'weekly_hours', label: 'employees.column.weekly_hours', sortable: true },
    { key: 'flexibility', label: 'employees.column.flexibility', sortable: false },
]

const searchTerm = ref(props.search ?? '')
const selectedIds = ref([])
const allSelected = computed(() =>
    props.employees.data.length > 0 && selectedIds.value.length === props.employees.data.length,
)

const query = computed(() => {
    const q = {}
    const search = (searchTerm.value ?? '').trim()
    if (search !== '') q.search = search
    if (props.sort !== 'name') q.sort = props.sort
    if (props.direction !== 'asc') q.direction = props.direction
    return q
})

const paginationRange = computed(() => {
    const from = props.employees.from ?? 0
    const to = props.employees.to ?? 0
    const total = props.employees.total ?? props.employees.data.length

    return __('employees.pagination.range', { from, to, total })
})

const paginationLinks = computed(() => {
    const links = props.employees.links ?? []
    const lastIndex = links.length - 1

    return links.map((link, index) => ({
        ...link,
        key: `${index}-${link.label}-${link.url ?? 'disabled'}`,
        label: index === 0 ? '‹' : index === lastIndex ? '›' : link.label,
        ariaLabel: index === 0
            ? __('employees.pagination.prev')
            : index === lastIndex
                ? __('employees.pagination.next')
                : __('employees.pagination.page_label', { page: link.label }),
        disabled: !link.url || link.active,
        edge: index === 0 ? 'first' : index === lastIndex ? 'last' : null,
    }))
})

function paginationLinkClass(link) {
    const classes = [
        'inline-flex min-w-9 items-center justify-center px-3 py-1.5 text-sm outline outline-1 -outline-offset-1',
    ]

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

function reload(overrides) {
    selectedIds.value = []
    router.get('/employees', { ...query.value, ...overrides }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

function sortBy(key) {
    const direction = props.sort === key && props.direction === 'asc' ? 'desc' : 'asc'
    reload({ sort: key === 'name' ? undefined : key, direction: direction === 'asc' ? undefined : direction })
}

let searchTimer = null
watch(searchTerm, () => {
    selectedIds.value = []
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => reload(), 250)
})
onBeforeUnmount(() => clearTimeout(searchTimer))

function goToPage(url) {
    if (!url) return

    selectedIds.value = []
    router.get(url, {}, { preserveState: true })
}

function openEmployee(employee) {
    router.visit(`/employees/${employee.id}/edit`)
}

// In-button feedback: which row is mid-request, and which just finished
// (briefly shows a checkmark before reverting to the normal label).
const sendingLinkId = ref(null)
const linkSentId = ref(null)
let linkSentTimeout = null

function sendLink(employee) {
    sendingLinkId.value = employee.id

    router.post(`/employees/${employee.id}/send-link`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            linkSentId.value = employee.id
            clearTimeout(linkSentTimeout)
            linkSentTimeout = setTimeout(() => (linkSentId.value = null), 2000)
        },
        onFinish: () => {
            sendingLinkId.value = null
        },
    })
}

function toggleSelectAll(checked) {
    selectedIds.value = checked ? props.employees.data.map(employee => employee.id) : []
}

function bulkDelete() {
    if (!confirm(__('employees.confirm.delete_selected', { count: selectedIds.value.length }))) return

    router.post('/employees/bulk-delete', { ids: selectedIds.value }, {
        onSuccess: () => { selectedIds.value = [] },
    })
}
</script>

<template>
    <AppLayout>
        <Head :title="__('employees.title')" />

        <Card class="max-w-5xl">
            <template #header>
                <div class="flex items-center justify-between gap-3 px-6 py-3">
                    <span class="text-base font-semibold">{{ __('employees.title') }}</span>
                </div>
            </template>

            <div class="space-y-4 p-6">
                <div class="flex items-center justify-between gap-3">
                    <SearchInput
                        v-model="searchTerm"
                        class="max-w-xs"
                        :placeholder="__('employees.search_placeholder')"
                    />

                    <div class="ml-auto flex items-center justify-end gap-3">
                        <ButtonDanger
                            type="button"
                            icon="bin"
                            icon-class="size-5"
                            :disabled="selectedIds.length === 0"
                            :aria-label="__('employees.action.delete_selected') + ' ' + selectedIds.length"
                            @click="bulkDelete"
                        >
                            <span v-if="selectedIds.length > 1" class="text-sm font-semibold">
                                ({{ selectedIds.length }})
                            </span>
                        </ButtonDanger>

                        <Link href="/employees/create">
                            <ButtonPrimary type="button" icon="user-plus" icon-class="size-5" aria-label="Add new" />
                        </Link>
                    </div>
                </div>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                            <th class="w-8 px-2 py-2 pr-3">
                                <CheckboxInput
                                    :model-value="allSelected"
                                    :aria-label="__('employees.selection.select_all')"
                                    @update:model-value="toggleSelectAll"
                                />
                            </th>
                            <th v-for="column in columns" :key="column.key" class="px-2 py-2">
                                <button
                                    v-if="column.sortable"
                                    type="button"
                                    class="flex items-center gap-1 font-medium hover:text-(--color-text-primary)"
                                    @click="sortBy(column.key)"
                                >
                                    {{ __(column.label) }}
                                    <Icon
                                        v-if="sort === column.key"
                                        :name="direction === 'asc' ? 'chevron-up' : 'chevron-down'"
                                        class="size-3.5"
                                    />
                                </button>
                                <span v-else class="font-medium">{{ __(column.label) }}</span>
                            </th>
                            <th class="px-2 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="employee in employees.data"
                            :key="employee.id"
                            class="cursor-pointer border-b border-(--color-table-row-separator) hover:bg-(--color-table-row-hover-bg)"
                            @click="openEmployee(employee)"
                        >
                            <td class="w-8 px-2 py-2 pr-3" @click.stop>
                                <CheckboxInput
                                    v-model="selectedIds"
                                    :value="employee.id"
                                    :aria-label="__('employees.selection.select_employee', { name: employee.name })"
                                />
                            </td>
                            <td class="px-2 py-2 text-(--color-table-row-text)">{{ employee.name }}</td>
                            <td class="px-2 py-2 text-(--color-table-row-text)">
                                {{ employee.business_line ?? __('employees.no_business_line') }}
                            </td>
                            <td class="px-2 py-2 text-(--color-table-row-text)">
                                {{ __('employees.hours_option', { count: employee.weekly_hours }) }}
                            </td>
                            <td class="px-2 py-2 text-(--color-table-row-text)">
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="shift in employee.shift_coverage ?? []"
                                        :key="shift.shift_id"
                                        class="inline-flex items-center rounded-full border border-(--color-badge-standard-border) bg-(--color-badge-standard-bg) px-2 py-0.5 text-xs font-medium text-(--color-badge-standard-text)"
                                    >
                                        {{ shift.name }} {{ shift.coverage_percentage }}%
                                    </span>
                                </div>
                            </td>
                            <td class="px-2 py-2 text-right" @click.stop>
                                <ButtonSecondary
                                    type="button"
                                    :icon="linkSentId === employee.id ? 'check-circle' : 'envelope'"
                                    :disabled="sendingLinkId === employee.id"
                                    @click="sendLink(employee)"
                                >
                                    {{
                                        sendingLinkId === employee.id
                                            ? __('employees.action.sending_link')
                                            : linkSentId === employee.id
                                              ? __('employees.action.link_sent')
                                              : employee.link_sent
                                                ? __('employees.action.resend_link')
                                                : __('employees.action.send_link')
                                    }}
                                </ButtonSecondary>
                            </td>
                        </tr>
                        <tr v-if="!employees.data.length">
                            <td :colspan="columns.length + 2" class="py-8 text-center text-(--color-text-secondary)">
                                {{ __('employees.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>

            </div>

            <template v-if="employees.last_page > 1" #footer>
                <div data-testid="employees-pagination" class="flex items-center justify-between gap-3 px-6 py-3 text-sm">
                    <span class="text-(--color-pagination-muted-text)">{{ paginationRange }}</span>
                    <div class="isolate inline-flex -space-x-px rounded-md">
                        <button
                            v-for="link in paginationLinks"
                            :key="link.key"
                            type="button"
                            :aria-label="link.ariaLabel"
                            :aria-current="link.active ? 'page' : undefined"
                            :disabled="link.disabled"
                            :class="paginationLinkClass(link)"
                            @click="goToPage(link.url)"
                        >
                            {{ link.label }}
                        </button>
                    </div>
                </div>
            </template>
        </Card>
    </AppLayout>
</template>
