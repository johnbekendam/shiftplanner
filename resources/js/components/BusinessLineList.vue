<script setup>
import { reactive, ref, watch } from 'vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import Icon from '@/components/ui/Icon.vue'
import { TextInput, NumberInput } from '@/components/ui/Input'
import { useDragReorder } from '@/composables/useDragReorder'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Rows of { id, abbreviation, description, target_fte, employee_count }.
    items: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:items'])

// Local, edit-until-Save state, seeded once from props. The parent forces
// a fresh seed by remounting this component (a :key bump) after its own
// successful save.
let nextLocalKey = -1
const rows = ref(props.items.map((item) => ({ ...item })))

watch(rows, () => emit('update:items', rows.value), { deep: true })

const { dragIndex, onDragStart, onDragOver, onDragEnd } = useDragReorder(rows)

const draft = reactive({ abbreviation: '', description: '', target_fte: null })

function add() {
    if ((draft.abbreviation ?? '').trim() === '') return

    rows.value = [
        ...rows.value,
        {
            id: null,
            _key: nextLocalKey--,
            abbreviation: draft.abbreviation,
            description: draft.description,
            target_fte: draft.target_fte,
            employee_count: 0,
        },
    ]
    draft.abbreviation = ''
    draft.description = ''
    draft.target_fte = null
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
                    <th class="w-8 py-2" />
                    <th class="w-24 py-2 pr-3 font-medium">{{ __('business_lines.abbreviation') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('business_lines.description') }}</th>
                    <th class="w-24 py-2 pr-3 font-medium">{{ __('business_lines.target_fte') }}</th>
                    <th class="w-14 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(item, index) in rows"
                    :key="item.id ?? item._key"
                    data-testid="business-line-row"
                    class="border-b border-(--color-table-row-separator) transition-opacity"
                    :class="dragIndex === index ? 'opacity-40' : 'opacity-100'"
                    draggable="true"
                    @dragstart="onDragStart(index)"
                    @dragover.prevent="onDragOver(index)"
                    @dragend="onDragEnd"
                    @drop.prevent
                >
                    <td class="py-2 pl-1 align-middle text-(--color-text-secondary)" :aria-label="__('business_lines.drag_handle')">
                        <Icon name="bars" class="size-4 cursor-grab" />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="item.abbreviation"
                            class="w-full"
                            :data-testid="`business-line-abbreviation-${item.id ?? item._key}`"
                        />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="item.description"
                            class="w-full"
                            :data-testid="`business-line-description-${item.id ?? item._key}`"
                        />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <NumberInput
                            v-model="item.target_fte"
                            :min="0"
                            :step="0.1"
                            class="w-full"
                            :data-testid="`business-line-target-fte-${item.id ?? item._key}`"
                        />
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonDanger
                            type="button"
                            icon="bin"
                            class="w-full px-0"
                            :aria-label="__('business_lines.delete')"
                            @click="remove(item)"
                        />
                    </td>
                </tr>

                <tr v-if="!rows.length">
                    <td colspan="5" class="py-6 text-center text-(--color-text-secondary)">
                        {{ __('business_lines.list_empty') }}
                    </td>
                </tr>

                <tr data-testid="business-line-add-row" class="border-t border-(--color-table-row-separator)">
                    <td />
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="draft.abbreviation"
                            class="w-full"
                            :placeholder="__('business_lines.add_abbreviation_placeholder')"
                        />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="draft.description"
                            class="w-full"
                            :placeholder="__('business_lines.add_description_placeholder')"
                        />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <NumberInput v-model="draft.target_fte" :min="0" :step="0.1" class="w-full" />
                    </td>
                    <td class="px-1 py-2 text-right align-top">
                        <ButtonPrimary
                            type="submit"
                            icon="plus-circle"
                            class="px-2.5"
                            :aria-label="__('business_lines.add')"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</template>
