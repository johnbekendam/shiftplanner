<script setup>
import { ref } from 'vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import { DateInput, TextInput } from '@/components/ui/Input'
import { formatDate } from '@/utils/date'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    holidays: { type: Array, default: () => [] },
    // Read-only: the list shows but the add row and delete buttons are hidden
    // (employee change lock).
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:holidays'])

const blank = () => ({ start_date: '', end_date: '', note: '' })
const draft = ref(blank())

// A row with `id: null` is a pending add; a row present in `holidays` but
// missing here is a pending delete. Both stay purely local until Save.
// Seeded once — the parent forces a fresh seed by remounting this
// component (a :key bump) after its own successful save.
let nextLocalKey = -1
const rows = ref(props.holidays.map((h) => ({ ...h })))

function add() {
    if (props.disabled) return
    rows.value.push({ id: null, _key: nextLocalKey--, ...draft.value })
    draft.value = blank()
    emit('update:holidays', rows.value)
}

function remove(holiday) {
    if (props.disabled) return
    rows.value = rows.value.filter((r) => r !== holiday)
    emit('update:holidays', rows.value)
}
</script>

<template>
    <form @submit.prevent="add">
        <table class="w-full table-fixed text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                    <th class="w-36 py-2 pr-3 font-medium">{{ __('availability.holidays.start') }}</th>
                    <th class="w-36 py-2 pr-3 font-medium">{{ __('availability.holidays.end') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('availability.holidays.note') }}</th>
                    <th class="w-12 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="holiday in rows"
                    :key="holiday.id ?? holiday._key"
                    data-testid="holiday-row"
                    class="border-b border-(--color-table-row-separator)"
                >
                    <td class="py-2 pr-3 text-(--color-table-row-text)">{{ formatDate(holiday.start_date) }}</td>
                    <td class="py-2 pr-3 text-(--color-table-row-text)">{{ formatDate(holiday.end_date) }}</td>
                    <td class="py-2 pr-3 text-(--color-text-secondary)">{{ holiday.note }}</td>
                    <td class="py-2 text-right">
                        <ButtonDanger
                            v-if="!disabled"
                            type="button"
                            icon="bin"
                            class="px-2.5"
                            :aria-label="__('availability.holidays.delete')"
                            @click="remove(holiday)"
                        />
                    </td>
                </tr>

                <tr v-if="!rows.length">
                    <td colspan="3" class="py-6 text-center text-(--color-text-secondary)">
                        {{ __('availability.holidays.empty') }}
                    </td>
                    <td />
                </tr>

                <tr v-if="!disabled" data-testid="holiday-add-row" class="border-t border-(--color-table-row-separator)">
                    <td class="w-36 py-2 pr-3 align-top">
                        <DateInput v-model="draft.start_date" class="w-full" />
                    </td>
                    <td class="w-36 py-2 pr-3 align-top">
                        <DateInput v-model="draft.end_date" class="w-full" />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput v-model="draft.note" class="w-full" />
                    </td>
                    <td class="py-2 text-right align-top">
                        <ButtonPrimary
                            type="submit"
                            icon="plus-circle"
                            class="px-2.5"
                            :aria-label="__('availability.holidays.add')"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</template>
