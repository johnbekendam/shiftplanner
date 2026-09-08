<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import { DateInput, TextInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    holidays: { type: Array, default: () => [] },
    // Base URL for the holiday sub-resource, e.g. /employees/7/holidays.
    endpoint: { type: String, required: true },
})

const blank = () => ({ start_date: '', end_date: '', note: '' })
const draft = ref(blank())
const errors = ref({})
const busy = ref(false)

// preserveState keeps this component (and its open tab) mounted across the
// redirect, so a holiday change never bounces the page back to the Details tab.
const stay = { preserveScroll: true, preserveState: true }

function add() {
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
    router.delete(`${props.endpoint}/${holiday.id}`, stay)
}
</script>

<template>
    <form @submit.prevent="add">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                    <th class="w-32 py-2 pr-3 font-medium">{{ __('availability.holidays.start') }}</th>
                    <th class="w-32 py-2 pr-3 font-medium">{{ __('availability.holidays.end') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('availability.holidays.note') }}</th>
                    <th class="w-0 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="holiday in holidays"
                    :key="holiday.id"
                    data-testid="holiday-row"
                    class="border-b border-(--color-table-row-separator)"
                >
                    <td class="py-2 pr-3 text-(--color-table-row-text)">{{ holiday.start_date }}</td>
                    <td class="py-2 pr-3 text-(--color-table-row-text)">{{ holiday.end_date }}</td>
                    <td class="py-2 pr-3 text-(--color-text-secondary)">{{ holiday.note }}</td>
                    <td class="py-2 text-right">
                        <ButtonDanger
                            type="button"
                            icon="bin"
                            class="px-2.5"
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

                <tr data-testid="holiday-add-row" class="border-t border-(--color-table-row-separator)">
                    <td class="w-32 py-2 pr-3 align-top">
                        <DateInput v-model="draft.start_date" class="w-full" />
                        <p v-if="errors.start_date" class="mt-1 text-xs text-[var(--color-badge-error-text)]">
                            {{ errors.start_date }}
                        </p>
                    </td>
                    <td class="w-32 py-2 pr-3 align-top">
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
