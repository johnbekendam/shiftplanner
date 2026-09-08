<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import LabeledInput from '@/components/LabeledInput.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
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

function add() {
    busy.value = true
    router.post(props.endpoint, { ...draft.value }, {
        preserveScroll: true,
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
    router.delete(`${props.endpoint}/${holiday.id}`, { preserveScroll: true })
}
</script>

<template>
    <div class="space-y-4">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                    <th class="py-2">{{ __('availability.holidays.start') }}</th>
                    <th class="py-2">{{ __('availability.holidays.end') }}</th>
                    <th class="py-2">{{ __('availability.holidays.note') }}</th>
                    <th class="py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="holiday in holidays"
                    :key="holiday.id"
                    class="border-b border-(--color-table-row-separator)"
                >
                    <td class="py-2 text-(--color-table-row-text)">{{ holiday.start_date }}</td>
                    <td class="py-2 text-(--color-table-row-text)">{{ holiday.end_date }}</td>
                    <td class="py-2 text-(--color-text-secondary)">{{ holiday.note }}</td>
                    <td class="py-2 text-right">
                        <ButtonSecondary type="button" icon="minus-circle" @click="remove(holiday)">
                            {{ __('availability.holidays.delete') }}
                        </ButtonSecondary>
                    </td>
                </tr>
                <tr v-if="!holidays.length">
                    <td colspan="4" class="py-8 text-center text-(--color-text-secondary)">
                        {{ __('availability.holidays.empty') }}
                    </td>
                </tr>
            </tbody>
        </table>

        <form class="flex flex-wrap items-end gap-3" @submit.prevent="add">
            <LabeledInput class="w-40" :label="__('availability.holidays.start')" :error="errors.start_date">
                <DateInput v-model="draft.start_date" class="w-full" />
            </LabeledInput>
            <LabeledInput class="w-40" :label="__('availability.holidays.end')" :error="errors.end_date">
                <DateInput v-model="draft.end_date" class="w-full" />
            </LabeledInput>
            <LabeledInput class="min-w-[12rem] flex-1" :label="__('availability.holidays.note')" :error="errors.note">
                <TextInput v-model="draft.note" class="w-full" />
            </LabeledInput>
            <ButtonPrimary type="submit" icon="plus-circle" :disabled="busy" class="shrink-0">
                {{ __('availability.holidays.add') }}
            </ButtonPrimary>
        </form>
    </div>
</template>
