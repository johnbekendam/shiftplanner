<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import { DateInput, TextInput } from '@/components/ui/Input'
import { formatDate } from '@/utils/date'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    holidays: { type: Array, default: () => [] },
    // Base URL for the holiday sub-resource, e.g. /employees/7/holidays.
    endpoint: { type: String, required: true },
    // Read-only: the list shows but the add row and delete buttons are hidden
    // (employee change lock).
    disabled: { type: Boolean, default: false },
})

const blank = () => ({ start_date: '', end_date: '', note: '' })
const draft = ref(blank())
const errors = ref({})
const busy = ref(false)

// Deleting a row: its own disappearance is the success signal, so only a
// pending state and a failure flash are needed (same reasoning as the
// checklists).
const removingId = ref(null)
const failedId = ref(null)
let failedTimeout = null

// preserveState keeps this component (and its open tab) mounted across the
// redirect, so a holiday change never bounces the page back to the Details tab.
const stay = { preserveScroll: true, preserveState: true }

function add() {
    if (props.disabled) return
    busy.value = true
    router.post(props.endpoint, { ...draft.value }, {
        ...stay,
        onSuccess: () => {
            draft.value = blank()
            errors.value = {}
        },
        onError: (e) => {
            errors.value = e
        },
        onFinish: () => {
            busy.value = false
        },
    })
}

function remove(holiday) {
    if (props.disabled) return

    removingId.value = holiday.id
    router.delete(`${props.endpoint}/${holiday.id}`, {
        ...stay,
        onError: () => {
            failedId.value = holiday.id
            clearTimeout(failedTimeout)
            failedTimeout = setTimeout(() => (failedId.value = null), 2000)
        },
        onFinish: () => {
            removingId.value = null
        },
    })
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
                    v-for="holiday in holidays"
                    :key="holiday.id"
                    data-testid="holiday-row"
                    class="border-b border-(--color-table-row-separator)"
                    :class="failedId === holiday.id ? 'outline outline-2 -outline-offset-1 outline-(--color-badge-error-border)' : ''"
                >
                    <td class="py-2 pr-3 text-(--color-table-row-text)">{{ formatDate(holiday.start_date) }}</td>
                    <td class="py-2 pr-3 text-(--color-table-row-text)">{{ formatDate(holiday.end_date) }}</td>
                    <td class="py-2 pr-3 text-(--color-text-secondary)">{{ holiday.note }}</td>
                    <td class="py-2 text-right">
                        <ButtonDanger
                            v-if="!disabled"
                            type="button"
                            :icon="removingId === holiday.id ? 'arrow-path' : 'bin'"
                            :icon-class="removingId === holiday.id ? 'size-4 animate-spin' : 'size-4'"
                            class="px-2.5"
                            :disabled="removingId === holiday.id"
                            :aria-label="__('availability.holidays.delete')"
                            @click="remove(holiday)"
                        />
                    </td>
                </tr>

                <tr v-if="!holidays.length">
                    <td colspan="3" class="py-6 text-center text-(--color-text-secondary)">
                        {{ __('availability.holidays.empty') }}
                    </td>
                    <td />
                </tr>

                <tr v-if="!disabled" data-testid="holiday-add-row" class="border-t border-(--color-table-row-separator)">
                    <td class="w-36 py-2 pr-3 align-top">
                        <DateInput v-model="draft.start_date" class="w-full" />
                        <p v-if="errors.start_date" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors.start_date }}
                        </p>
                    </td>
                    <td class="w-36 py-2 pr-3 align-top">
                        <DateInput v-model="draft.end_date" class="w-full" />
                        <p v-if="errors.end_date" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors.end_date }}
                        </p>
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput v-model="draft.note" class="w-full" />
                        <p v-if="errors.note" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors.note }}
                        </p>
                    </td>
                    <td class="py-2 text-right align-top">
                        <ButtonPrimary
                            type="submit"
                            icon="plus-circle"
                            class="px-2.5"
                            :disabled="busy"
                            :aria-label="__('availability.holidays.add')"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</template>
