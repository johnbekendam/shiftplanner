<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import { TextInput, EmailInput, SelectInput, CheckboxInput } from '@/components/ui/Input'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'
import { useAuth } from '@/composables/useAuth'

const __ = useI18n()
const { user: currentUser } = useAuth()
const isAdmin = computed(() => currentUser.value?.role === 'admin')

const props = defineProps({
    user: { type: Object, default: null },
})

const isEdit = computed(() => props.user !== null)
// /users/create is admin-only at the route level, so this only ever
// applies once editing an existing user.
const readOnly = computed(() => isEdit.value && !isAdmin.value)

const title = computed(() => {
    if (!isEdit.value) return __('users.form.create_title')
    return readOnly.value ? __('users.form.view_title') : __('users.form.edit_title')
})

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    role: props.user?.role ?? 'manager',
    is_active: props.user?.is_active ?? true,
})

const roleOptions = computed(() => [
    { value: 'manager', label: __('users.role.manager') },
    { value: 'admin', label: __('users.role.admin') },
])

function submit() {
    if (isEdit.value) {
        form.put(`/users/${props.user.id}`)
    } else {
        form.post('/users')
    }
}
</script>

<template>
    <AppLayout>
        <Head :title="title" />

        <Card class="max-w-lg">
            <template #header>
                <div class="px-6 py-3 text-base font-semibold">{{ title }}</div>
            </template>

            <form class="space-y-5 p-6" @submit.prevent="submit">
                <LabeledInput :label="__('users.field.name')" :error="form.errors.name">
                    <TextInput v-model="form.name" :disabled="readOnly" class="w-full" />
                </LabeledInput>

                <LabeledInput :label="__('users.field.email')" :error="form.errors.email">
                    <EmailInput v-model="form.email" :disabled="readOnly" class="w-full" />
                </LabeledInput>

                <LabeledInput :label="__('users.field.role')" :error="form.errors.role">
                    <SelectInput v-model="form.role" :options="roleOptions" :disabled="readOnly" class="w-full" />
                </LabeledInput>

                <LabeledInput v-if="isEdit" :label="__('users.field.active')" :error="form.errors.is_active">
                    <CheckboxInput v-model="form.is_active" :disabled="readOnly" />
                </LabeledInput>

                <p v-else class="text-sm text-(--color-text-secondary)">{{ __('users.create_hint') }}</p>

                <div class="flex justify-end gap-3">
                    <Link href="/users">
                        <ButtonSecondary type="button">
                            {{ readOnly ? __('users.action.back') : __('users.action.cancel') }}
                        </ButtonSecondary>
                    </Link>
                    <ButtonPrimary v-if="!readOnly" type="submit" :disabled="form.processing">
                        {{ __('users.action.save') }}
                    </ButtonPrimary>
                </div>
            </form>
        </Card>
    </AppLayout>
</template>
