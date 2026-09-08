<script setup>
import { computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import { PasswordInput } from '@/components/ui/Input'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { useI18n } from '@/composables/useI18n'
import { useAuth } from '@/composables/useAuth'

const __ = useI18n()
const { user } = useAuth()

const props = defineProps({
    hasPassword: { type: Boolean, default: false },
})

const canLinkEmployee = computed(
    () => user.value?.role === 'manager' && !user.value?.employee_id,
)

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

function savePassword() {
    passwordForm.put('/account/password', {
        onSuccess: () => passwordForm.reset(),
    })
}

function addAsEmployee() {
    router.post('/account/employee')
}
</script>

<template>
    <AppLayout>
        <Head :title="__('account.title')" />

        <Card class="max-w-lg">
            <template #header>
                <div class="px-6 py-3 text-base font-semibold">{{ __('account.title') }}</div>
            </template>

            <div class="p-6">
                <section class="space-y-4">
                    <h3 class="text-sm font-semibold text-(--color-text-primary)">
                        {{ __('account.password.heading') }}
                    </h3>

                    <p v-if="!hasPassword" class="text-sm text-(--color-text-secondary)">
                        {{ __('account.password.none_hint') }}
                    </p>

                    <form class="space-y-4" @submit.prevent="savePassword">
                        <LabeledInput
                            v-if="hasPassword"
                            :label="__('account.password.current')"
                            :error="passwordForm.errors.current_password"
                        >
                            <PasswordInput v-model="passwordForm.current_password" class="w-full" />
                        </LabeledInput>

                        <LabeledInput
                            :label="__('account.password.new')"
                            :error="passwordForm.errors.password"
                        >
                            <PasswordInput v-model="passwordForm.password" class="w-full" />
                        </LabeledInput>

                        <LabeledInput :label="__('account.password.confirm')">
                            <PasswordInput v-model="passwordForm.password_confirmation" class="w-full" />
                        </LabeledInput>

                        <div class="flex justify-end">
                            <ButtonPrimary type="submit" :disabled="passwordForm.processing">
                                {{ __('account.password.save') }}
                            </ButtonPrimary>
                        </div>
                    </form>
                </section>

                <template v-if="canLinkEmployee">
                    <CardSeparator />

                    <section class="space-y-4" data-testid="link-employee">
                        <h3 class="text-sm font-semibold text-(--color-text-primary)">
                            {{ __('account.employee.heading') }}
                        </h3>
                        <p class="text-sm text-(--color-text-secondary)">{{ __('account.employee.hint') }}</p>
                        <ButtonPrimary type="button" icon="user-plus" @click="addAsEmployee">
                            {{ __('account.employee.add') }}
                        </ButtonPrimary>
                    </section>
                </template>
            </div>
        </Card>
    </AppLayout>
</template>
