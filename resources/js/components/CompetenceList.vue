<script setup>
import { reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import { TextInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    competences: { type: Array, default: () => [] },
    // Base URL for the competence resource, e.g. /settings/competences.
    endpoint: { type: String, required: true },
})

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
    for (const competence of list) names[competence.id] = competence.name
}
sync(props.competences)
watch(() => props.competences, sync)

watch(names, () => {
    for (const competence of props.competences) {
        const next = (names[competence.id] ?? '').trim()
        if (next === '' || next === competence.name || renaming.has(competence.id)) continue
        rename(competence, next)
    }
})

function rename(competence, next) {
    renaming.add(competence.id)
    router.put(`${props.endpoint}/${competence.id}`, { name: next }, {
        ...stay,
        onSuccess: () => {
            delete errors[competence.id]
        },
        onError: (e) => {
            errors[competence.id] = e.name
        },
        onFinish: () => {
            renaming.delete(competence.id)
        },
    })
}

function move(competence, direction) {
    router.put(`${props.endpoint}/${competence.id}/move`, { direction }, stay)
}

function remove(competence) {
    const message = __('competences.delete_confirm', { count: competence.holder_count })
    if (!window.confirm(message)) return

    router.delete(`${props.endpoint}/${competence.id}`, stay)
}

const draft = ref('')
const addError = ref('')
const busy = ref(false)

function add() {
    busy.value = true
    router.post(props.endpoint, { name: draft.value }, {
        ...stay,
        onSuccess: () => {
            draft.value = ''
            addError.value = ''
        },
        onError: (e) => {
            addError.value = e.name
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
                    <th class="py-2 pr-3 font-medium">{{ __('competences.name') }}</th>
                    <th class="w-10 py-2" />
                    <th class="w-10 py-2" />
                    <th class="w-12 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(competence, index) in competences"
                    :key="competence.id"
                    data-testid="competence-row"
                    class="border-b border-(--color-table-row-separator)"
                >
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="names[competence.id]"
                            class="w-full"
                            :data-testid="`competence-name-${competence.id}`"
                        />
                        <p v-if="errors[competence.id]" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors[competence.id] }}
                        </p>
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonSecondary
                            v-if="index > 0"
                            type="button"
                            icon="chevron-up"
                            class="w-full px-0"
                            :aria-label="__('competences.move_up')"
                            @click="move(competence, 'up')"
                        />
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonSecondary
                            v-if="index < competences.length - 1"
                            type="button"
                            icon="chevron-down"
                            class="w-full px-0"
                            :aria-label="__('competences.move_down')"
                            @click="move(competence, 'down')"
                        />
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonDanger
                            type="button"
                            icon="bin"
                            class="w-full px-0"
                            :aria-label="__('competences.delete')"
                            @click="remove(competence)"
                        />
                    </td>
                </tr>

                <tr v-if="!competences.length">
                    <td colspan="4" class="py-6 text-center text-(--color-text-secondary)">
                        {{ __('competences.list_empty') }}
                    </td>
                </tr>

                <tr data-testid="competence-add-row" class="border-t border-(--color-table-row-separator)">
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="draft"
                            class="w-full"
                            :placeholder="__('competences.add_placeholder')"
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
                            :aria-label="__('competences.add')"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</template>
