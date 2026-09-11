<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'
import { useAuth } from '@/composables/useAuth'

const __ = useI18n()
const { user: currentUser } = useAuth()
const isAdmin = computed(() => currentUser.value?.role === 'admin')

defineProps({
    users: { type: Array, default: () => [] },
})

function openUser(user) {
    router.visit(`/users/${user.id}/edit`)
}

// In-button feedback: which row is mid-request, and which just finished
// (briefly shows a checkmark before reverting to the normal label).
const sendingUserId = ref(null)
const sentUserId = ref(null)
let sentTimeout = null

function resendInvite(user) {
    sendingUserId.value = user.id

    router.post(`/users/${user.id}/resend-invite`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            sentUserId.value = user.id
            clearTimeout(sentTimeout)
            sentTimeout = setTimeout(() => (sentUserId.value = null), 2000)
        },
        onFinish: () => {
            sendingUserId.value = null
        },
    })
}
</script>

<template>
    <AppLayout>
        <Head :title="__('users.title')" />

        <Card class="max-w-3xl">
            <template #header>
                <div class="flex items-center justify-between gap-3 px-6 py-3">
                    <span class="text-base font-semibold">{{ __('users.title') }}</span>
                    <Link v-if="isAdmin" href="/users/create">
                        <ButtonPrimary type="button" icon="user-plus">{{ __('users.action.new') }}</ButtonPrimary>
                    </Link>
                </div>
            </template>

            <div class="p-6">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                            <th class="py-2">{{ __('users.column.name') }}</th>
                            <th class="py-2">{{ __('users.column.email') }}</th>
                            <th class="py-2">{{ __('users.column.role') }}</th>
                            <th class="py-2">{{ __('users.column.status') }}</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            data-testid="user-row"
                            class="cursor-pointer border-b border-(--color-table-row-separator) hover:bg-(--color-table-row-hover-bg)"
                            @click="openUser(user)"
                        >
                            <td class="py-2 text-(--color-table-row-text)">{{ user.name }}</td>
                            <td class="py-2 text-(--color-table-row-text)">{{ user.email }}</td>
                            <td class="py-2 text-(--color-table-row-text)">{{ __(`users.role.${user.role}`) }}</td>
                            <td class="py-2 text-(--color-text-secondary)">
                                {{ user.is_active ? __('users.status.active') : __('users.status.inactive') }}
                            </td>
                            <td class="py-2 text-right">
                                <ButtonSecondary
                                    v-if="isAdmin && !user.has_password"
                                    type="button"
                                    data-testid="resend-invite"
                                    :icon="sentUserId === user.id ? 'check-circle' : null"
                                    :disabled="sendingUserId === user.id"
                                    @click.stop="resendInvite(user)"
                                >
                                    {{
                                        sendingUserId === user.id
                                            ? __('users.action.resending_invite')
                                            : sentUserId === user.id
                                              ? __('users.action.invite_sent')
                                              : __('users.action.resend_invite')
                                    }}
                                </ButtonSecondary>
                            </td>
                        </tr>
                        <tr v-if="!users.length">
                            <td colspan="5" class="py-8 text-center text-(--color-text-secondary)">
                                {{ __('users.empty') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </AppLayout>
</template>
