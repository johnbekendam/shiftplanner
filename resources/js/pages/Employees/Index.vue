<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { SearchInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    employees: { type: Object, required: true },
    search: { type: String, default: '' },
})

const searchTerm = ref(props.search)

let searchTimer = null
watch(searchTerm, (value) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.get('/employees', { search: value }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 250)
})
onBeforeUnmount(() => clearTimeout(searchTimer))

function goToPage(url) {
    if (url) router.get(url, {}, { preserveState: true })
}

function openEmployee(employee) {
    router.visit(`/employees/${employee.id}/edit`)
}
</script>

<template>
    <AppLayout>
        <Head :title="__('employees.title')" />

        <Card class="max-w-4xl">
            <template #header>
                <div class="flex items-center justify-between gap-3 px-6 py-3">
                    <span class="text-base font-semibold">{{ __('employees.title') }}</span>
                    <Link href="/employees/create">
                        <ButtonPrimary type="button" icon="user-plus">{{ __('employees.action.new') }}</ButtonPrimary>
                    </Link>
                </div>
            </template>

            <div class="space-y-4 p-6">
                <SearchInput
                    v-model="searchTerm"
                    class="max-w-xs"
                    :placeholder="__('employees.search_placeholder')"
                />

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                            <th class="py-2">{{ __('employees.column.name') }}</th>
                            <th class="py-2">{{ __('employees.column.email') }}</th>
                            <th class="py-2">{{ __('employees.column.weekly_hours') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="employee in employees.data"
                            :key="employee.id"
                            class="cursor-pointer border-b border-(--color-table-row-separator) hover:bg-(--color-table-row-hover-bg)"
                            @click="openEmployee(employee)"
                        >
                            <td class="py-2 text-(--color-table-row-text)">{{ employee.name }}</td>
                            <td class="py-2 text-(--color-table-row-text)">{{ employee.email }}</td>
                            <td class="py-2 text-(--color-table-row-text)">
                                {{ __('employees.hours_option', { count: employee.weekly_hours }) }}
                            </td>
                        </tr>
                        <tr v-if="!employees.data.length">
                            <td colspan="3" class="py-8 text-center text-(--color-text-secondary)">
                                {{ __('employees.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <template v-if="employees.last_page > 1" #footer>
                <div class="flex items-center justify-between px-6 py-3 text-sm text-(--color-text-secondary)">
                    <ButtonSecondary
                        type="button"
                        :disabled="!employees.prev_page_url"
                        @click="goToPage(employees.prev_page_url)"
                    >
                        {{ __('employees.pagination.prev') }}
                    </ButtonSecondary>
                    <span>{{ __('employees.pagination.page', { current: employees.current_page, total: employees.last_page }) }}</span>
                    <ButtonSecondary
                        type="button"
                        :disabled="!employees.next_page_url"
                        @click="goToPage(employees.next_page_url)"
                    >
                        {{ __('employees.pagination.next') }}
                    </ButtonSecondary>
                </div>
            </template>
        </Card>
    </AppLayout>
</template>
