<script setup>
import { reactive, ref, watch } from 'vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import { TextInput, TimeInput, CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Rows of { id, name, start_time, end_time }.
    items: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:items'])

// Local, edit-until-Save state, seeded once from props. The parent forces
// a fresh seed by remounting this component (a :key bump) after its own
// successful save.
let nextLocalKey = -1
const rows = ref(props.items.map((item) => ({ ...item })))

watch(rows, () => emit('update:items', rows.value), { deep: true })

const draft = reactive({ name: '', start_time: '', end_time: '', visible_by_default: true })

function add() {
    if ((draft.name ?? '').trim() === '') return

    rows.value = [
        ...rows.value,
        {
            id: null,
            _key: nextLocalKey--,
            name: draft.name,
            start_time: draft.start_time,
            end_time: draft.end_time,
            visible_by_default: draft.visible_by_default,
        },
    ]
    draft.name = ''
    draft.start_time = ''
    draft.end_time = ''
    draft.visible_by_default = true
}

function remove(item) {
    rows.value = rows.value.filter((r) => r !== item)
}
</script>

<template>
    <form @submit.prevent="add">
        <table class="w-full table-fixed text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                    <th class="py-2 pr-3 font-medium">{{ __('shifts.name') }}</th>
                    <th class="w-28 py-2 pr-3 font-medium">{{ __('shifts.start_time') }}</th>
                    <th class="w-28 py-2 pr-3 font-medium">{{ __('shifts.end_time') }}</th>
                    <th class="w-40 py-2 pr-3 font-medium">{{ __('shifts.visible_by_default') }}</th>
                    <th class="w-14 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="item in rows"
                    :key="item.id ?? item._key"
                    data-testid="shift-row"
                    class="border-b border-(--color-table-row-separator)"
                >
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="item.name"
                            class="w-full"
                            :data-testid="`shift-name-${item.id ?? item._key}`"
                        />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TimeInput
                            v-model="item.start_time"
                            :data-testid="`shift-start-time-${item.id ?? item._key}`"
                        />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TimeInput
                            v-model="item.end_time"
                            :data-testid="`shift-end-time-${item.id ?? item._key}`"
                        />
                    </td>
                    <td class="py-2 pr-3 align-middle">
                        <CheckboxInput v-model="item.visible_by_default" />
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonDanger
                            type="button"
                            icon="bin"
                            class="w-full px-0"
                            :aria-label="__('shifts.delete')"
                            @click="remove(item)"
                        />
                    </td>
                </tr>

                <tr v-if="!rows.length">
                    <td colspan="5" class="py-6 text-center text-(--color-text-secondary)">
                        {{ __('shifts.list_empty') }}
                    </td>
                </tr>

                <tr data-testid="shift-add-row" class="border-t border-(--color-table-row-separator)">
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="draft.name"
                            class="w-full"
                            :placeholder="__('shifts.add_name_placeholder')"
                        />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TimeInput v-model="draft.start_time" />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TimeInput v-model="draft.end_time" />
                    </td>
                    <td class="py-2 pr-3 align-middle">
                        <CheckboxInput v-model="draft.visible_by_default" />
                    </td>
                    <td class="px-1 py-2 text-right align-top">
                        <ButtonPrimary
                            type="submit"
                            icon="plus-circle"
                            class="px-2.5"
                            :aria-label="__('shifts.add')"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</template>
