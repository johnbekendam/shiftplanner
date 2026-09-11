<script setup>
import { computed } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { EmailInput, TextInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()
const page = usePage()

// After a request the controller redirects back with a flash message.
// The same text is shown whether the employee was new or already known,
// so the page never reveals who is registered.
const confirmation = computed(() => page.props.flash?.success ?? null)

const form = useForm({ first_name: '', last_name: '', email: '' })

function submit() {
    form.post('/signup', {
        onFinish: () => form.reset('first_name', 'last_name', 'email'),
    })
}
</script>

<template>
    <CenteredLayout align="top">
        <Head :title="__('signup.page_title')" />

        <template #title>{{ __('signup.card_title') }}</template>

        <p
            v-if="confirmation"
            data-testid="signup-confirmation"
            class="text-sm text-(--color-text-secondary)"
        >
            {{ confirmation }}
        </p>

        <form v-else @submit.prevent="submit" class="space-y-5">
            <p class="text-sm text-(--color-text-secondary)">{{ __('signup.intro') }}</p>

            <LabeledInput :label="__('signup.field.first_name')" :error="form.errors.first_name">
                <TextInput v-model="form.first_name" autocomplete="given-name" required class="w-full" />
            </LabeledInput>

            <LabeledInput :label="__('signup.field.last_name')" :error="form.errors.last_name">
                <TextInput v-model="form.last_name" autocomplete="family-name" required class="w-full" />
            </LabeledInput>

            <LabeledInput :label="__('signup.field.email')" :error="form.errors.email">
                <EmailInput v-model="form.email" autocomplete="email" required class="w-full" />
            </LabeledInput>

            <div class="flex justify-end">
                <ButtonPrimary type="submit" :disabled="form.processing">
                    {{ __('signup.submit') }}
                </ButtonPrimary>
            </div>
        </form>
    </CenteredLayout>
</template>
