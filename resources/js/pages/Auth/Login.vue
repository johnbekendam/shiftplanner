<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { EmailInput, PasswordInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const form = useForm({ email: '', password: '' })

function login() {
    form.post('/login', { onFinish: () => form.reset('password') })
}
</script>

<template>
    <CenteredLayout>
        <Head :title="__('auth.page_title')" />

        <template #title>{{ __('auth.card_title') }}</template>

        <form @submit.prevent="login" class="space-y-5">
            <LabeledInput :label="__('auth.field.email')" :error="form.errors.email">
                <EmailInput v-model="form.email" autocomplete="email" required class="w-full" />
            </LabeledInput>

            <LabeledInput :label="__('auth.field.password')" :error="form.errors.password">
                <PasswordInput v-model="form.password" autocomplete="current-password" required class="w-full" />
            </LabeledInput>

            <div class="flex justify-end">
                <ButtonPrimary type="submit" :disabled="form.processing">
                    {{ form.processing ? __('auth.action.signing_in') : __('auth.action.signin') }}
                </ButtonPrimary>
            </div>
        </form>
    </CenteredLayout>
</template>
