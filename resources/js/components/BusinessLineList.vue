<script setup>
import { reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import { TextInput, NumberInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Rows of { id, abbreviation, description, target_fte, employee_count }.
    items: { type: Array, default: () => [] },
    // Base URL for the resource, e.g. /settings/business-lines.
    endpoint: { type: String, required: true },
})

// Each write keeps this component (and the open tab) mounted across the
// redirect, the same as the other settings lists.
const stay = { preserveScroll: true, preserveState: true }

// Local editable copy of each row. The typed inputs commit on blur, Enter,
// or Tab, so a deep watch on this map is the "edit finished" signal.
const rows = reactive({})
const errors = reactive({})
const saving = new Set()

function sync(list) {
    for (const key of Object.keys(rows)) delete rows[key]
    for (const item of list) {
        rows[item.id] = {
            abbreviation: item.abbreviation,
            description: item.description,
            target_fte: item.target_fte,
        }
    }
}
sync(props.items)
watch(() => props.items, sync)

watch(rows, () => {
    for (const item of props.items) {
        const row = rows[item.id]
        if (!row || saving.has(item.id)) continue
        const changed =
            row.abbreviation !== item.abbreviation ||
            row.description !== item.description ||
            row.target_fte !== item.target_fte
        if (!changed || (row.abbreviation ?? '').trim() === '') continue
        save(item)
    }
})

function save(item) {
    saving.add(item.id)
    router.put(`${props.endpoint}/${item.id}`, { ...rows[item.id] }, {
        ...stay,
        onSuccess: () => {
            delete errors[item.id]
        },
        onError: (e) => {
            errors[item.id] = e
        },
        onFinish: () => {
            saving.delete(item.id)
        },
    })
}

function move(item, direction) {
    router.put(`${props.endpoint}/${item.id}/move`, { direction }, stay)
}

function remove(item) {
    if (!window.confirm(__('business_lines.delete_confirm', { count: item.employee_count }))) return

    router.delete(`${props.endpoint}/${item.id}`, stay)
}

const draft = reactive({ abbreviation: '', description: '', target_fte: null })
const addErrors = ref({})
const busy = ref(false)

function add() {
    busy.value = true
    router.post(props.endpoint, { ...draft }, {
        ...stay,
        onSuccess: () => {
            draft.abbreviation = ''
            draft.description = ''
            draft.target_fte = null
            addErrors.value = {}
        },
        onError: (e) => {
            addErrors.value = e
        },
        onFinish: () => {
            busy.value = false
        },
    })
}
</script>

<template>
    <form @submit.prevent="add">
        <table class="w-full table-fixed text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                    <th class="w-24 py-2 pr-3 font-medium">{{ __('business_lines.abbreviation') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('business_lines.description') }}</th>
                    <th class="w-24 py-2 pr-3 font-medium">{{ __('business_lines.target_fte') }}</th>
                    <th class="w-14 py-2" />
                    <th class="w-14 py-2" />
                    <th class="w-14 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(item, index) in items"
                    :key="item.id"
                    data-testid="business-line-row"
                    class="border-b border-(--color-table-row-separator)"
                >
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="rows[item.id].abbreviation"
                            class="w-full"
                            :data-testid="`business-line-abbreviation-${item.id}`"
                        />
                        <p v-if="errors[item.id]?.abbreviation" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors[item.id].abbreviation }}
                        </p>
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="rows[item.id].description"
                            class="w-full"
                            :data-testid="`business-line-description-${item.id}`"
                        />
                        <p v-if="errors[item.id]?.description" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors[item.id].description }}
                        </p>
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <NumberInput
                            v-model="rows[item.id].target_fte"
                            :min="0"
                            :step="0.1"
                            class="w-full"
                            :data-testid="`business-line-target-fte-${item.id}`"
                        />
                        <p v-if="errors[item.id]?.target_fte" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors[item.id].target_fte }}
                        </p>
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonSecondary
                            v-if="index > 0"
                            type="button"
                            icon="chevron-up"
                            class="w-full px-0"
                            :aria-label="__('business_lines.move_up')"
                            @click="move(item, 'up')"
                        />
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonSecondary
                            v-if="index < items.length - 1"
                            type="button"
                            icon="chevron-down"
                            class="w-full px-0"
                            :aria-label="__('business_lines.move_down')"
                            @click="move(item, 'down')"
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

                <tr v-if="!items.length">
                    <td colspan="6" class="py-6 text-center text-(--color-text-secondary)">
                        {{ __('business_lines.list_empty') }}
                    </td>
                </tr>

                <tr data-testid="business-line-add-row" class="border-t border-(--color-table-row-separator)">
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="draft.abbreviation"
                            class="w-full"
                            :placeholder="__('business_lines.add_abbreviation_placeholder')"
                        />
                        <p v-if="addErrors.abbreviation" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ addErrors.abbreviation }}
                        </p>
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="draft.description"
                            class="w-full"
                            :placeholder="__('business_lines.add_description_placeholder')"
                        />
                        <p v-if="addErrors.description" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ addErrors.description }}
                        </p>
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <NumberInput v-model="draft.target_fte" :min="0" :step="0.1" class="w-full" />
                        <p v-if="addErrors.target_fte" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ addErrors.target_fte }}
                        </p>
                    </td>
                    <td colspan="3" class="px-1 py-2 text-right align-top">
                        <ButtonPrimary
                            type="submit"
                            icon="plus-circle"
                            class="px-2.5"
                            :disabled="busy"
                            :aria-label="__('business_lines.add')"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</template>
