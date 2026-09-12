<script setup>
import Card from './Card.vue'
import ButtonSecondary from './ButtonSecondary.vue'
import ButtonDanger from './ButtonDanger.vue'
import ButtonPrimary from './ButtonPrimary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    confirmLabel: { type: String, required: true },
    cancelLabel: { type: String, default: null },
    // 'danger' renders the confirm action as ButtonDanger; anything else as ButtonPrimary.
    variant: { type: String, default: 'danger', validator: (v) => ['danger', 'primary'].includes(v) },
})

defineEmits(['confirm', 'cancel'])
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center px-4"
                role="dialog"
                aria-modal="true"
                @keydown.escape.stop="$emit('cancel')"
            >
                <div class="absolute inset-0 bg-black/50" @click="$emit('cancel')" />

                <Card class="relative z-10 w-full max-w-md">
                    <template #header>
                        <div class="px-6 py-4 text-base font-semibold">{{ title }}</div>
                    </template>

                    <div class="px-6 py-5 text-sm text-(--color-text-secondary)">
                        <slot />
                    </div>

                    <template #footer>
                        <div class="flex items-center justify-end gap-3 px-6 py-4">
                            <ButtonSecondary type="button" @click="$emit('cancel')">
                                {{ cancelLabel ?? __('app.cancel') }}
                            </ButtonSecondary>
                            <component
                                :is="variant === 'danger' ? ButtonDanger : ButtonPrimary"
                                type="button"
                                @click="$emit('confirm')"
                            >
                                {{ confirmLabel }}
                            </component>
                        </div>
                    </template>
                </Card>
            </div>
        </Transition>
    </Teleport>
</template>
