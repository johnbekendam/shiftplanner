<script setup>
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    token: { type: String, required: true },
    employee: { type: Object, required: true },
})

const form = useForm({
    shift_preference: props.employee.shift_preference,
})

const preferenceOptions = computed(() => ({
    morning: __('personal.preference.morning'),
    evening: __('personal.preference.evening'),
    either: __('personal.preference.either'),
}))

function save() {
    form.put(`/personal/${props.token}/preference`, { preserveScroll: true })
}
</script>

<template>
    <CenteredLayout>
        <Head :title="__('personal.title')" />

        <template #title>{{ __('personal.greeting', { name: employee.name }) }}</template>

        <form class="space-y-5" @submit.prevent="save">
            <p class="text-sm text-(--color-text-secondary)">{{ __('personal.intro') }}</p>

            <LabeledInput :label="__('personal.field.preference')" :error="form.errors.shift_preference">
                <SelectInput v-model="form.shift_preference" :options="preferenceOptions" class="w-full" />
            </LabeledInput>

            <div class="flex items-center justify-end gap-3">
                <span v-if="form.recentlySuccessful" class="text-sm text-(--color-badge-success-text)">
                    {{ __('personal.saved') }}
                </span>
                <ButtonPrimary type="submit" :disabled="form.processing">
                    {{ __('personal.action.save') }}
                </ButtonPrimary>
            </div>
        </form>
    </CenteredLayout>
</template>
