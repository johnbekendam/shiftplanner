<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import { PasswordInput } from '@/components/ui/Input'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    token: { type: String, required: true },
})

const form = useForm({ password: '', password_confirmation: '' })
const skipping = ref(false)

function submit() {
    form.post(`/login/link/${props.token}`)
}

function skip() {
    skipping.value = true
    router.post(`/login/link/${props.token}/skip`, {}, { onFinish: () => (skipping.value = false) })
}
</script>

<template>
    <CenteredLayout>
        <Head :title="__('auth.setpw.title')" />

        <template #title>{{ __('auth.setpw.title') }}</template>

        <p class="text-sm text-(--color-text-secondary)">{{ __('auth.setpw.intro') }}</p>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            <LabeledInput :label="__('auth.setpw.field.password')" :error="form.errors.password">
                <PasswordInput v-model="form.password" autocomplete="new-password" class="w-full" />
            </LabeledInput>

            <LabeledInput :label="__('auth.setpw.field.confirm')">
                <PasswordInput v-model="form.password_confirmation" autocomplete="new-password" class="w-full" />
            </LabeledInput>

            <div class="flex justify-end">
                <ButtonPrimary type="submit" :disabled="form.processing || skipping">
                    {{ __('auth.setpw.submit') }}
                </ButtonPrimary>
            </div>
        </form>

        <CardSeparator />

        <div class="flex justify-end">
            <ButtonSecondary type="button" :disabled="form.processing || skipping" @click="skip">
                {{ __('auth.setpw.skip') }}
            </ButtonSecondary>
        </div>
    </CenteredLayout>
</template>
