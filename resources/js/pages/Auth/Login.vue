<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { EmailInput, PasswordInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const form = useForm({ email: '', password: '' })
const linkRequested = ref(false)

function login() {
    form.post('/login', { onFinish: () => form.reset('password') })
}

function requestLink() {
    form.post('/login/link', {
        onSuccess: () => {
            linkRequested.value = true
        },
    })
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
                <ButtonSecondary type="button" :disabled="form.processing" @click="requestLink">
                    {{ __('auth.action.request_link') }}
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

        <p v-if="linkRequested" data-testid="link-notice" class="mt-6 text-sm text-(--color-text-secondary)">
            {{ __('auth.link_sent') }}
        </p>
    </CenteredLayout>
</template>
