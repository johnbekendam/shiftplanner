<script setup>
import Icon from './Icon.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

// status: 'idle' | 'saving' | 'saved' | 'error' — from useSaveStatus().
defineProps({
    status: { type: String, default: 'idle' },
})
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-150"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="status !== 'idle'"
            data-testid="save-status-badge"
            class="absolute top-3 right-3 flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
            :class="{
                'bg-(--color-badge-standard-bg) text-(--color-badge-standard-text)': status === 'saving',
                'bg-(--color-badge-success-bg) text-(--color-badge-success-text)': status === 'saved',
                'bg-(--color-badge-error-bg) text-(--color-badge-error-text)': status === 'error',
            }"
        >
            <Icon v-if="status === 'saving'" name="arrow-path" class="size-3.5 animate-spin" />
            <Icon v-else-if="status === 'saved'" name="check-circle" class="size-3.5" />
            <Icon v-else name="x-circle" class="size-3.5" />
            <span>
                {{
                    status === 'saving'
                        ? __('app.saving')
                        : status === 'saved'
                          ? __('app.saved')
                          : __('app.save_failed')
                }}
            </span>
        </div>
    </Transition>
</template>
