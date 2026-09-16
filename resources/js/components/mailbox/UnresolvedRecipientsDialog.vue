<script setup>
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    // [{ name, email, tokens: string[] }]
    recipients: { type: Array, default: () => [] },
})

defineEmits(['cancel', 'continue'])
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            @keydown.escape.stop="$emit('cancel')"
        >
            <div class="absolute inset-0 bg-black/50" @click="$emit('cancel')" />
            <div class="relative z-10 w-full max-w-md rounded-xl border border-(--color-card-border) bg-(--color-card-body-bg) shadow-xl">
                <div class="border-b border-(--color-card-border) px-4 py-3">
                    <div class="font-semibold text-(--color-text-body)">
                        {{ __('mailbox.compose.unresolved.title') }}
                    </div>
                </div>

                <div class="max-h-72 overflow-y-auto px-4 py-3 space-y-3">
                    <p class="text-sm text-(--color-text-secondary)">
                        {{ __('mailbox.compose.unresolved.description') }}
                    </p>

                    <ul class="space-y-1">
                        <li
                            v-for="recipient in recipients"
                            :key="recipient.email"
                            class="rounded-md border border-(--color-border) px-2.5 py-1.5 text-sm"
                        >
                            <span class="text-(--color-text-primary)">{{ recipient.name }}</span>
                            <span class="text-(--color-text-secondary)"> {{ recipient.email }}</span>
                            <span class="block text-xs text-(--color-text-secondary)">
                                {{ recipient.tokens.join(', ') }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="flex justify-end gap-3 border-t border-(--color-card-border) px-4 py-3">
                    <ButtonSecondary type="button" @click="$emit('cancel')">
                        {{ __('mailbox.compose.unresolved.cancel') }}
                    </ButtonSecondary>
                    <ButtonPrimary type="button" @click="$emit('continue')">
                        {{ __('mailbox.compose.unresolved.continue') }}
                    </ButtonPrimary>
                </div>
            </div>
        </div>
    </Teleport>
</template>
