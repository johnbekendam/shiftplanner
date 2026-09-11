<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import { PasswordInput } from '@/components/ui/Input'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    token: { type: String, required: true },
})

const form = useForm({ password: '', password_confirmation: '' })

function submit() {
    form.post(`/login/link/${props.token}`)
}
</script>

<template>
    <CenteredLayout>
        <Head :title="__('auth.setpw.title')" />

        <template #title>{{ __('auth.setpw.title') }}</template>

        <p class="mb-6 text-sm text-(--color-text-secondary)">{{ __('auth.setpw.intro') }}</p>

        <form @submit.prevent="submit" class="space-y-5">
            <LabeledInput :label="__('auth.setpw.field.password')" :error="form.errors.password">
                <PasswordInput v-model="form.password" autocomplete="new-password" class="w-full" />
            </LabeledInput>

            <LabeledInput :label="__('auth.setpw.field.confirm')">
                <PasswordInput v-model="form.password_confirmation" autocomplete="new-password" class="w-full" />
            </LabeledInput>

            <div class="flex justify-end">
                <ButtonPrimary type="submit" :disabled="form.processing">
                    {{ __('auth.setpw.submit') }}
                </ButtonPrimary>
            </div>
        </form>
    </CenteredLayout>
</template>
