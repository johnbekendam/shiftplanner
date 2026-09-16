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
    businessLines: { type: Array, default: () => [] },
})

const isEdit = computed(() => props.user !== null)
// /users/create is admin-only at the route level, so this only ever
// applies once editing an existing user.
const readOnly = computed(() => isEdit.value && !isAdmin.value)
// A non-admin can't submit the main form (PUT /users/{user} is admin-only),
// but they can still reassign their own business line via the self-service
// /account/business-line endpoint — same one the Account page uses.
const isOwnRecord = computed(() => isEdit.value && currentUser.value?.id === props.user?.id)
const businessLineSelfEditable = computed(() => readOnly.value && isOwnRecord.value)

const title = computed(() => {
    if (!isEdit.value) return __('users.form.create_title')
    return readOnly.value ? __('users.form.view_title') : __('users.form.edit_title')
})

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    role: props.user?.role ?? 'manager',
    is_active: props.user?.is_active ?? true,
    business_line_id: props.user?.business_line_id ?? null,
})

const roleOptions = computed(() => [
    { value: 'manager', label: __('users.role.manager') },
    { value: 'admin', label: __('users.role.admin') },
])

const businessLineOptions = computed(() => [
    { value: null, label: __('users.field.business_line_none') },
    ...props.businessLines.map((line) => ({ value: line.id, label: line.abbreviation })),
])

const selfBusinessLineForm = useForm({
    business_line_id: props.user?.business_line_id ?? null,
})

function saveSelfBusinessLine() {
    selfBusinessLineForm.put('/account/business-line', {
        onSuccess: () => selfBusinessLineForm.defaults(),
    })
}

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

                <LabeledInput
                    v-if="businessLines.length"
                    :label="__('users.field.business_line')"
                    :error="businessLineSelfEditable ? selfBusinessLineForm.errors.business_line_id : form.errors.business_line_id"
                >
                    <div class="flex items-center gap-3">
                        <SelectInput
                            v-if="businessLineSelfEditable"
                            v-model="selfBusinessLineForm.business_line_id"
                            :options="businessLineOptions"
                            class="w-full"
                        />
                        <SelectInput
                            v-else
                            v-model="form.business_line_id"
                            :options="businessLineOptions"
                            :disabled="readOnly"
                            class="w-full"
                        />
                        <ButtonPrimary
                            v-if="businessLineSelfEditable"
                            type="button"
                            :disabled="selfBusinessLineForm.processing || !selfBusinessLineForm.isDirty"
                            @click="saveSelfBusinessLine"
                        >
                            {{ __('users.action.save') }}
                        </ButtonPrimary>
                    </div>
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
