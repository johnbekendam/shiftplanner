<script setup>
import { reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import { TextInput, TimeInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Rows of { id, name, start_time, end_time }.
    items: { type: Array, default: () => [] },
    // Base URL for the resource, e.g. /settings/shifts.
    endpoint: { type: String, required: true },
    // Shared useSaveStatus() tracker for the card body's SaveStatusBadge.
    saveStatus: { type: Object, default: null },
})

// Each write keeps this component (and the open tab) mounted across the
// redirect, the same as the other settings lists.
const stay = { preserveScroll: true, preserveState: true }

// Local editable copy of each row. The typed inputs commit on blur, Enter,
// or Tab, so a deep watch on this map is the "edit finished" signal.
const rows = reactive({})
const errors = reactive({})
const saving = new Set()

const FIELDS = ['name', 'start_time', 'end_time']

function sync(list) {
    for (const key of Object.keys(rows)) delete rows[key]
    for (const item of list) {
        rows[item.id] = { name: item.name, start_time: item.start_time, end_time: item.end_time }
    }
}
sync(props.items)
watch(() => props.items, sync)

watch(rows, () => {
    for (const item of props.items) {
        const row = rows[item.id]
        if (!row || saving.has(item.id)) continue
        const changed = FIELDS.some((f) => row[f] !== item[f])
        if (!changed || (row.name ?? '').trim() === '') continue
        save(item)
    }
})

function save(item) {
    saving.add(item.id)
    props.saveStatus?.start()
    router.put(`${props.endpoint}/${item.id}`, { ...rows[item.id] }, {
        ...stay,
        onSuccess: () => {
            delete errors[item.id]
            props.saveStatus?.succeed()
        },
        onError: (e) => {
            errors[item.id] = e
            props.saveStatus?.fail()
        },
        onFinish: () => {
            saving.delete(item.id)
        },
    })
}

function remove(item) {
    if (!window.confirm(__('shifts.delete_confirm'))) return

    props.saveStatus?.start()
    router.delete(`${props.endpoint}/${item.id}`, {
        ...stay,
        onSuccess: () => props.saveStatus?.succeed(),
        onError: () => props.saveStatus?.fail(),
    })
}

const draft = reactive({ name: '', start_time: '', end_time: '' })
const addErrors = ref({})
const busy = ref(false)

function add() {
    busy.value = true
    props.saveStatus?.start()
    router.post(props.endpoint, { ...draft }, {
        ...stay,
        onSuccess: () => {
            draft.name = ''
            draft.start_time = ''
            draft.end_time = ''
            addErrors.value = {}
            props.saveStatus?.succeed()
        },
        onError: (e) => {
            addErrors.value = e
            props.saveStatus?.fail()
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
                    <th class="py-2 pr-3 font-medium">{{ __('shifts.name') }}</th>
                    <th class="w-28 py-2 pr-3 font-medium">{{ __('shifts.start_time') }}</th>
                    <th class="w-28 py-2 pr-3 font-medium">{{ __('shifts.end_time') }}</th>
                    <th class="w-14 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="item in items"
                    :key="item.id"
                    data-testid="shift-row"
                    class="border-b border-(--color-table-row-separator)"
                >
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="rows[item.id].name"
                            class="w-full"
                            :data-testid="`shift-name-${item.id}`"
                        />
                        <p v-if="errors[item.id]?.name" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors[item.id].name }}
                        </p>
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TimeInput
                            v-model="rows[item.id].start_time"
                            :data-testid="`shift-start-time-${item.id}`"
                        />
                        <p v-if="errors[item.id]?.start_time" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors[item.id].start_time }}
                        </p>
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TimeInput
                            v-model="rows[item.id].end_time"
                            :data-testid="`shift-end-time-${item.id}`"
                        />
                        <p v-if="errors[item.id]?.end_time" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors[item.id].end_time }}
                        </p>
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

                <tr v-if="!items.length">
                    <td colspan="4" class="py-6 text-center text-(--color-text-secondary)">
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
                        <p v-if="addErrors.name" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ addErrors.name }}
                        </p>
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TimeInput v-model="draft.start_time" />
                        <p v-if="addErrors.start_time" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ addErrors.start_time }}
                        </p>
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TimeInput v-model="draft.end_time" />
                        <p v-if="addErrors.end_time" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ addErrors.end_time }}
                        </p>
                    </td>
                    <td class="px-1 py-2 text-right align-top">
                        <ButtonPrimary
                            type="submit"
                            icon="plus-circle"
                            class="px-2.5"
                            :disabled="busy"
                            :aria-label="__('shifts.add')"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</template>
