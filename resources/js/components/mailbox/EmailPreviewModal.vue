<script setup>
import { ref } from 'vue'
import Icon from '@/components/ui/Icon.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    subject: { type: String, default: '' },
    html:    { type: String, default: '' },
    loading: { type: Boolean, default: false },
})

defineEmits(['close'])

const iframe = ref(null)

function resizeIframe() {
    if (!iframe.value) return
    try {
        const height = iframe.value.contentDocument.documentElement.scrollHeight
        iframe.value.style.height = height + 'px'
    } catch (e) {}
}
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
                class="fixed inset-0 z-50 flex items-center justify-center px-4"
                @keydown.escape.stop="$emit('close')"
            >
                <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />
                <div class="relative z-10 flex w-full max-w-2xl max-h-[90vh] flex-col rounded-xl border border-(--color-card-border) bg-(--color-card-body-bg) shadow-xl">
                    <div class="flex shrink-0 items-start justify-between border-b border-(--color-card-border) px-4 py-3 gap-4">
                        <div class="min-w-0">
                            <div class="font-semibold text-(--color-text-body) truncate">{{ subject }}</div>
                            <slot name="meta" />
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <slot name="actions" />
                            <button
                                type="button"
                                class="rounded p-1 text-(--color-text-secondary) transition-colors hover:text-(--color-text-body)"
                                :aria-label="__('app.close')"
                                @click="$emit('close')"
                            >
                                <Icon name="x-mark" class="size-4" />
                            </button>
                        </div>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <div v-if="loading" class="flex items-center justify-center py-12 text-(--color-text-secondary) text-sm">
                            {{ __('mailbox.compose.preview') }}…
                        </div>
                        <iframe
                            v-else-if="html"
                            ref="iframe"
                            :srcdoc="html"
                            class="w-full border-0"
                            @load="resizeIframe"
                        />
                        <p v-else class="px-4 py-8 text-center text-(--color-text-secondary) text-sm">
                            {{ __('mailbox.preview.no_body') }}
                        </p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
