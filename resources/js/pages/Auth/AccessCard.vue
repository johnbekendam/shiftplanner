<script setup>
import { ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import Tabs from '@/components/ui/Tabs.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { EmailInput, PasswordInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const tab = ref('signin')
const tabs = [
    { value: 'signin', label: __('auth.tab.signin') },
    { value: 'personal-link', label: __('auth.tab.personal_link') },
]

// Sign in
const loginForm = useForm({ email: '', password: '' })
const linkRequested = ref(false)

function login() {
    loginForm.post('/login', { onFinish: () => loginForm.reset('password') })
}

function requestLink() {
    loginForm.post('/login/link', {
        onSuccess: () => {
            linkRequested.value = true
        },
    })
}

// Get my link — resends an existing employee's personal-page link. Never creates one.
const personalLinkForm = useForm({ email: '' })
const personalLinkRequested = ref(false)

function requestPersonalLink() {
    personalLinkForm.post('/personal-link', {
        onSuccess: () => {
            personalLinkRequested.value = true
        },
    })
}

// Leaving a tab resets its form, matching a fresh page load.
watch(tab, (value, previous) => {
    if (previous === 'signin') {
        loginForm.reset()
        loginForm.clearErrors()
        linkRequested.value = false
    }
    if (previous === 'personal-link') {
        personalLinkForm.reset()
        personalLinkForm.clearErrors()
        personalLinkRequested.value = false
    }
})
</script>

<template>
    <CenteredLayout align="top">
        <Head :title="__('auth.page_title')" />

        <template #header>
            <Tabs :tabs="tabs" v-model="tab" />
        </template>

        <template v-if="tab === 'signin'">
            <form @submit.prevent="login" class="space-y-5">
                <LabeledInput :label="__('auth.field.email')" :error="loginForm.errors.email">
                    <EmailInput v-model="loginForm.email" autocomplete="email" required class="w-full" />
                </LabeledInput>

                <LabeledInput :label="__('auth.field.password')" :error="loginForm.errors.password">
                    <PasswordInput v-model="loginForm.password" autocomplete="current-password" class="w-full" />
                </LabeledInput>

                <div class="flex items-center justify-between gap-3">
                    <ButtonSecondary type="button" :disabled="loginForm.processing" @click="requestLink">
                        {{ __('auth.action.request_link') }}
                    </ButtonSecondary>
                    <ButtonPrimary type="submit" :disabled="loginForm.processing">
                        {{ loginForm.processing ? __('auth.action.signing_in') : __('auth.action.signin') }}
                    </ButtonPrimary>
                </div>
            </form>

            <p v-if="linkRequested" data-testid="link-notice" class="mt-6 text-sm text-(--color-text-secondary)">
                {{ __('auth.link_sent') }}
            </p>
        </template>

        <template v-else>
            <form @submit.prevent="requestPersonalLink" class="space-y-5">
                <p class="text-sm text-(--color-text-secondary)">{{ __('auth.personal_link.intro') }}</p>

                <LabeledInput :label="__('auth.personal_link.field.email')" :error="personalLinkForm.errors.email">
                    <EmailInput v-model="personalLinkForm.email" autocomplete="email" required class="w-full" />
                </LabeledInput>

                <div class="flex justify-end">
                    <ButtonPrimary type="submit" :disabled="personalLinkForm.processing">
                        {{ __('auth.personal_link.submit') }}
                    </ButtonPrimary>
                </div>
            </form>

            <p
                v-if="personalLinkRequested"
                data-testid="personal-link-notice"
                class="mt-6 text-sm text-(--color-text-secondary)"
            >
                {{ __('auth.personal_link.sent') }}
            </p>

            <p class="mt-6 text-center text-sm text-(--color-text-secondary)">
                <Link
                    href="/signup"
                    class="font-medium text-(--color-text-link) hover:text-(--color-text-link-hover) hover:underline"
                >
                    {{ __('auth.personal_link.new_employee') }}
                </Link>
            </p>
        </template>
    </CenteredLayout>
</template>
