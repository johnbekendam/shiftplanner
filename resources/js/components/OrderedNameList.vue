<script setup>
import { ref, watch, computed } from 'vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import Icon from '@/components/ui/Icon.vue'
import { TextInput } from '@/components/ui/Input'
import { useDragReorder } from '@/composables/useDragReorder'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Rows of { id, name, holder_count }.
    items: { type: Array, default: () => [] },
    // i18n key namespace: `${i18nPrefix}.name`, `.add`, `.add_placeholder`,
    // `.drag_handle`, `.delete`, `.list_empty`.
    i18nPrefix: { type: String, required: true },
})

const emit = defineEmits(['update:items'])

const t = computed(() => (key, replace) => __(`${props.i18nPrefix}.${key}`, replace))

// Local, edit-until-Save state, seeded once from props. The parent forces
// a fresh seed by remounting this component (a :key bump) after its own
// successful save.
let nextLocalKey = -1
const rows = ref(props.items.map((item) => ({ ...item })))

watch(rows, () => emit('update:items', rows.value), { deep: true })

const { dragIndex, onDragStart, onDragOver, onDragEnd } = useDragReorder(rows)

const draft = ref('')

function add() {
    if ((draft.value ?? '').trim() === '') return

    rows.value = [...rows.value, { id: null, _key: nextLocalKey--, name: draft.value, holder_count: 0 }]
    draft.value = ''
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
                    <th class="py-2 pr-3 font-medium">{{ t('name') }}</th>
                    <th class="w-14 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(item, index) in rows"
                    :key="item.id ?? item._key"
                    data-testid="ordered-name-row"
                    class="border-b border-(--color-table-row-separator) transition-opacity"
                    :class="dragIndex === index ? 'opacity-40' : 'opacity-100'"
                    draggable="true"
                    @dragstart="onDragStart(index)"
                    @dragover.prevent="onDragOver(index)"
                    @dragend="onDragEnd"
                    @drop.prevent
                >
                    <td class="py-2 pl-1 align-middle text-(--color-text-secondary)" :aria-label="t('drag_handle')">
                        <Icon name="bars" class="size-4 cursor-grab" />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="item.name"
                            class="w-full"
                            :data-testid="`ordered-name-input-${item.id ?? item._key}`"
                        />
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonDanger
                            type="button"
                            icon="bin"
                            class="w-full px-0"
                            :aria-label="t('delete')"
                            @click="remove(item)"
                        />
                    </td>
                </tr>

                <tr v-if="!rows.length">
                    <td colspan="3" class="py-6 text-center text-(--color-text-secondary)">
                        {{ t('list_empty') }}
                    </td>
                </tr>

                <tr data-testid="ordered-name-add-row" class="border-t border-(--color-table-row-separator)">
                    <td />
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="draft"
                            class="w-full"
                            :placeholder="t('add_placeholder')"
                        />
                    </td>
                    <td class="px-1 py-2 text-right align-top">
                        <ButtonPrimary
                            type="submit"
                            icon="plus-circle"
                            class="px-2.5"
                            :aria-label="t('add')"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</template>
