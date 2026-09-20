<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { SelectInput, CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'
import { formatDate } from '@/utils/date'

const __ = useI18n()

const props = defineProps({
    rows: { type: Array, default: () => [] }, // { id, name, business_line, uninformed_count, first_date }
    businessLines: { type: Array, default: () => [] }, // { id, abbreviation }
})

const businessLine = defineModel('businessLine', { default: '' })

const businessLineOptions = computed(() => [
    { value: '', label: __('reports.uninformed_planning.business_line_placeholder') },
    ...props.businessLines.map((l) => ({ value: l.id, label: l.abbreviation })),
])

const selectedIds = ref([])
watch(() => props.rows, () => { selectedIds.value = [] })

const allSelected = computed(() =>
    props.rows.length > 0 && selectedIds.value.length === props.rows.length,
)

function toggleSelectAll(checked) {
    selectedIds.value = checked ? props.rows.map((e) => e.id) : []
}

function emailSelected() {
    const query = selectedIds.value.map((id) => `employee_ids[]=${id}`).join('&')
    router.visit(`/mailbox?tab=compose&type=planning&${query}`)
}
</script>

<template>
    <div class="p-6 space-y-5">
        <div class="flex flex-wrap items-center gap-4">
            <SelectInput
                v-model="businessLine"
                :options="businessLineOptions"
                :placeholder="__('reports.uninformed_planning.business_line_placeholder')"
                class="w-56"
            />
        </div>

        <CardSeparator />

        <div v-if="rows.length > 0" class="flex justify-end">
            <ButtonPrimary
                type="button"
                icon="envelope"
                :disabled="selectedIds.length === 0"
                :aria-label="__('reports.uninformed_planning.email_selected') + ' ' + selectedIds.length"
                @click="emailSelected"
            >
                {{ __('reports.uninformed_planning.email_selected') }}
                <span v-if="selectedIds.length > 0" class="text-sm font-semibold">
                    ({{ selectedIds.length }})
                </span>
            </ButtonPrimary>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                    <th class="w-8 px-2 py-2 pr-3">
                        <CheckboxInput
                            :model-value="allSelected"
                            :aria-label="__('reports.uninformed_planning.select_all')"
                            @update:model-value="toggleSelectAll"
                        />
                    </th>
                    <th class="px-2 py-2 font-medium">{{ __('reports.uninformed_planning.column.name') }}</th>
                    <th class="px-2 py-2 font-medium">{{ __('reports.uninformed_planning.column.business_line') }}</th>
                    <th class="px-2 py-2 font-medium">{{ __('reports.uninformed_planning.column.count') }}</th>
                    <th class="px-2 py-2 font-medium">{{ __('reports.uninformed_planning.column.first_date') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.id"
                    class="border-b border-(--color-table-row-separator) hover:bg-(--color-table-row-hover-bg)"
                >
                    <td class="w-8 px-2 py-2 pr-3">
                        <CheckboxInput
                            v-model="selectedIds"
                            :value="row.id"
                            :aria-label="__('reports.uninformed_planning.select_employee', { name: row.name })"
                        />
                    </td>
                    <td class="px-2 py-2 text-(--color-table-row-text)">{{ row.name }}</td>
                    <td class="px-2 py-2 text-(--color-table-row-text)">
                        {{ row.business_line ?? __('reports.uninformed_planning.no_business_line') }}
                    </td>
                    <td class="px-2 py-2 text-(--color-table-row-text)">{{ row.uninformed_count }}</td>
                    <td class="px-2 py-2 text-(--color-table-row-text)">{{ formatDate(row.first_date) }}</td>
                </tr>
            </tbody>
        </table>

        <p v-if="rows.length === 0" class="text-sm text-(--color-text-secondary)">
            {{ __('reports.uninformed_planning.empty') }}
        </p>
    </div>
</template>
