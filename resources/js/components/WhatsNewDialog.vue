<script setup>
import Card from '@/components/ui/Card.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ShiftNote from '@/components/ShiftNote.vue'
import { formatDate } from '@/utils/date'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    open: { type: Boolean, default: false },
    // [{ id, date, title, html }], newest first. See features/whats-new/.
    entries: { type: Array, default: () => [] },
})

defineEmits(['close'])
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            role="dialog"
            aria-modal="true"
            @keydown.escape.stop="$emit('close')"
        >
            <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />

            <Card class="relative z-10 flex max-h-[85vh] w-full max-w-2xl flex-col">
                <template #header>
                    <div class="px-6 py-4 text-base font-semibold">{{ __('whats_new.title') }}</div>
                </template>

                <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-5">
                    <article v-for="entry in entries" :key="entry.id" data-testid="whats-new-entry">
                        <h3 class="text-sm font-semibold text-(--color-text-heading)">{{ entry.title }}</h3>
                        <p class="mb-2 text-xs text-(--color-text-muted)">{{ formatDate(entry.date) }}</p>
                        <ShiftNote :html="entry.html" />
                    </article>
                </div>

                <template #footer>
                    <div class="flex justify-end px-6 py-4">
                        <ButtonPrimary type="button" @click="$emit('close')">{{ __('whats_new.close') }}</ButtonPrimary>
                    </div>
                </template>
            </Card>
        </div>
    </Teleport>
</template>
