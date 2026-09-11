<script setup>
import { computed, ref, watch } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import Tabs from '@/components/ui/Tabs.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { EmailInput, PasswordInput, TextInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()
const page = usePage()

const props = defineProps({
    activeTab: { type: String, default: 'signin', validator: (v) => ['signin', 'personal-link'].includes(v) },
})

const tab = ref(props.activeTab)
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

// Get my link
const signupForm = useForm({ first_name: '', last_name: '', email: '' })
const signupConfirmation = computed(() => page.props.flash?.success ?? null)

function requestPersonalLink() {
    signupForm.post('/signup', {
        onFinish: () => signupForm.reset('first_name', 'last_name', 'email'),
    })
}

// Leaving a tab resets its form, matching a fresh page load of either route.
watch(tab, (value, previous) => {
    if (previous === 'signin') {
        loginForm.reset()
        loginForm.clearErrors()
        linkRequested.value = false
    }
    if (previous === 'personal-link') {
        signupForm.reset()
        signupForm.clearErrors()
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
            <p v-if="signupConfirmation" data-testid="signup-confirmation" class="text-sm text-(--color-text-secondary)">
                {{ signupConfirmation }}
            </p>

            <form v-else @submit.prevent="requestPersonalLink" class="space-y-5">
                <p class="text-sm text-(--color-text-secondary)">{{ __('signup.intro') }}</p>

                <LabeledInput :label="__('signup.field.first_name')" :error="signupForm.errors.first_name">
                    <TextInput v-model="signupForm.first_name" autocomplete="given-name" required class="w-full" />
                </LabeledInput>

                <LabeledInput :label="__('signup.field.last_name')" :error="signupForm.errors.last_name">
                    <TextInput v-model="signupForm.last_name" autocomplete="family-name" required class="w-full" />
                </LabeledInput>

                <LabeledInput :label="__('signup.field.email')" :error="signupForm.errors.email">
                    <EmailInput v-model="signupForm.email" autocomplete="email" required class="w-full" />
                </LabeledInput>

                <div class="flex justify-end">
                    <ButtonPrimary type="submit" :disabled="signupForm.processing">
                        {{ __('signup.submit') }}
                    </ButtonPrimary>
                </div>
            </form>
        </template>
    </CenteredLayout>
</template>
