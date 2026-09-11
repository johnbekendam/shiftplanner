<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    token: { type: String, required: true },
})

const form = useForm({})

function submit() {
    form.post(`/login/link/${props.token}`)
}
</script>

<template>
    <CenteredLayout>
        <Head :title="__('auth.signin_link.title')" />

        <template #title>{{ __('auth.signin_link.title') }}</template>

        <form @submit.prevent="submit" class="flex justify-end">
            <ButtonPrimary type="submit" :disabled="form.processing">
                {{ __('auth.signin_link.submit') }}
            </ButtonPrimary>
        </form>
    </CenteredLayout>
</template>
