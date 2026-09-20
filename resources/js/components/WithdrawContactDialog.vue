<script setup>
import Card from '@/components/ui/Card.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    open: { type: Boolean, default: false },
    // { name, email } of the business line responsible, or null when there is nobody to name.
    contact: { type: Object, default: null },
})

defineEmits(['close'])
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
                @keydown.escape.stop="$emit('close')"
            >
                <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />

                <Card class="relative z-10 w-full max-w-md">
                    <template #header>
                        <div class="px-6 py-4 text-base font-semibold">{{ __('personal.withdraw.title') }}</div>
                    </template>

                    <div class="space-y-3 px-6 py-5 text-sm text-(--color-text-secondary)">
                        <p>
                            {{ contact ? __('personal.withdraw.info') : __('personal.withdraw.contact_planner') }}
                        </p>
                        <p v-if="contact" class="text-(--color-text-primary)">
                            <span class="font-medium">{{ contact.name }}</span>
                            <br>
                            <a :href="`mailto:${contact.email}`" class="text-(--color-text-link) underline">{{ contact.email }}</a>
                        </p>
                    </div>

                    <template #footer>
                        <div class="flex items-center justify-end px-6 py-4">
                            <ButtonSecondary type="button" @click="$emit('close')">
                                {{ __('personal.withdraw.close') }}
                            </ButtonSecondary>
                        </div>
                    </template>
                </Card>
            </div>
        </Transition>
    </Teleport>
</template>
