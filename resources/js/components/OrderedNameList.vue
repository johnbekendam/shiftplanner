<script setup>
import { reactive, ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import { TextInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Rows of { id, name, holder_count }.
    items: { type: Array, default: () => [] },
    // Base URL for the resource, e.g. /settings/competences.
    endpoint: { type: String, required: true },
    // i18n key namespace: `${i18nPrefix}.name`, `.add`, `.add_placeholder`,
    // `.move_up`, `.move_down`, `.delete`, `.delete_confirm`, `.list_empty`.
    i18nPrefix: { type: String, required: true },
    // Shared useSaveStatus() tracker for the card body's SaveStatusBadge.
    saveStatus: { type: Object, default: null },
})

const t = computed(() => (key, replace) => __(`${props.i18nPrefix}.${key}`, replace))

// Each write keeps this component (and the open tab) mounted across the
// redirect, the same as the holiday and availability lists.
const stay = { preserveScroll: true, preserveState: true }

// Local editable copy of each name. TextInput commits on blur, Enter, or
// Tab, so a watch on this map is the "edit finished" signal.
const names = reactive({})
const errors = reactive({})
const renaming = new Set()

function sync(list) {
    for (const key of Object.keys(names)) delete names[key]
    for (const item of list) names[item.id] = item.name
}
sync(props.items)
watch(() => props.items, sync)

watch(names, () => {
    for (const item of props.items) {
        const next = (names[item.id] ?? '').trim()
        if (next === '' || next === item.name || renaming.has(item.id)) continue
        rename(item, next)
    }
})

function rename(item, next) {
    renaming.add(item.id)
    props.saveStatus?.start()
    router.put(`${props.endpoint}/${item.id}`, { name: next }, {
        ...stay,
        onSuccess: () => {
            delete errors[item.id]
            props.saveStatus?.succeed()
        },
        onError: (e) => {
            errors[item.id] = e.name
            props.saveStatus?.fail()
        },
        onFinish: () => {
            renaming.delete(item.id)
        },
    })
}

function move(item, direction) {
    props.saveStatus?.start()
    router.put(`${props.endpoint}/${item.id}/move`, { direction }, {
        ...stay,
        onSuccess: () => props.saveStatus?.succeed(),
        onError: () => props.saveStatus?.fail(),
    })
}

function remove(item) {
    if (!window.confirm(t.value('delete_confirm', { count: item.holder_count }))) return

    props.saveStatus?.start()
    router.delete(`${props.endpoint}/${item.id}`, {
        ...stay,
        onSuccess: () => props.saveStatus?.succeed(),
        onError: () => props.saveStatus?.fail(),
    })
}

const draft = ref('')
const addError = ref('')
const busy = ref(false)

function add() {
    busy.value = true
    props.saveStatus?.start()
    router.post(props.endpoint, { name: draft.value }, {
        ...stay,
        onSuccess: () => {
            draft.value = ''
            addError.value = ''
            props.saveStatus?.succeed()
        },
        onError: (e) => {
            addError.value = e.name
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
                    <th class="py-2 pr-3 font-medium">{{ t('name') }}</th>
                    <th class="w-14 py-2" />
                    <th class="w-14 py-2" />
                    <th class="w-14 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(item, index) in items"
                    :key="item.id"
                    data-testid="ordered-name-row"
                    class="border-b border-(--color-table-row-separator)"
                >
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="names[item.id]"
                            class="w-full"
                            :data-testid="`ordered-name-input-${item.id}`"
                        />
                        <p v-if="errors[item.id]" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors[item.id] }}
                        </p>
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonSecondary
                            v-if="index > 0"
                            type="button"
                            icon="chevron-up"
                            class="w-full px-0"
                            :aria-label="t('move_up')"
                            @click="move(item, 'up')"
                        />
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonSecondary
                            v-if="index < items.length - 1"
                            type="button"
                            icon="chevron-down"
                            class="w-full px-0"
                            :aria-label="t('move_down')"
                            @click="move(item, 'down')"
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

                <tr v-if="!items.length">
                    <td colspan="4" class="py-6 text-center text-(--color-text-secondary)">
                        {{ t('list_empty') }}
                    </td>
                </tr>

                <tr data-testid="ordered-name-add-row" class="border-t border-(--color-table-row-separator)">
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="draft"
                            class="w-full"
                            :placeholder="t('add_placeholder')"
                        />
                        <p v-if="addError" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ addError }}
                        </p>
                    </td>
                    <td colspan="3" class="px-1 py-2 text-right align-top">
                        <ButtonPrimary
                            type="submit"
                            icon="plus-circle"
                            class="px-2.5"
                            :disabled="busy"
                            :aria-label="t('add')"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</template>
