<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { EmailInput, PasswordInput, TextInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const form = useForm({ email: '', password: '', code: '' })
const codeRequested = ref(false)

function login() {
    form.post('/login', { onFinish: () => form.reset('password') })
}

function requestCode() {
    form.post('/login/code', {
        onSuccess: () => {
            codeRequested.value = true
        },
    })
}

function verifyCode() {
    form.post('/login/code/verify', { onFinish: () => form.reset('code') })
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
                <PasswordInput v-model="form.password" autocomplete="current-password" class="w-full" />
            </LabeledInput>

            <div class="flex items-center justify-between gap-3">
                <ButtonSecondary type="button" :disabled="form.processing" @click="requestCode">
                    {{ __('auth.action.request_code') }}
                </ButtonSecondary>
                <ButtonPrimary type="submit" :disabled="form.processing">
                    {{ form.processing ? __('auth.action.signing_in') : __('auth.action.signin') }}
                </ButtonPrimary>
            </div>
        </form>

        <p class="mt-6 text-center text-sm text-(--color-text-secondary)">
            <Link href="/signup" class="font-medium text-(--color-text-link) hover:text-(--color-text-link-hover) hover:underline">
                {{ __('signup.login_link') }}
            </Link>
        </p>

        <div v-if="codeRequested" data-testid="code-section" class="mt-6 space-y-4 border-t border-[var(--color-card-border)] pt-6">
            <p class="text-sm text-(--color-text-secondary)">{{ __('auth.code_sent') }}</p>

            <form @submit.prevent="verifyCode" class="space-y-4">
                <LabeledInput :label="__('auth.field.code')" :error="form.errors.code">
                    <TextInput
                        v-model="form.code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        class="w-full"
                    />
                </LabeledInput>

                <div class="flex justify-end">
                    <ButtonPrimary type="submit" :disabled="form.processing">
                        {{ __('auth.action.verify_code') }}
                    </ButtonPrimary>
                </div>
            </form>
        </div>
    </CenteredLayout>
</template>
