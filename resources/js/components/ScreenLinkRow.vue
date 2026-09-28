<script setup>
import { ref, onBeforeUnmount } from 'vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useI18n } from '@/composables/useI18n'

// One link row on the Settings Screens tab: text on the left, copy and
// regenerate on the right. The URL itself stays off screen. Emits
// `regenerate` only after the confirm. See features/settings-screens-tab/.
const __ = useI18n()

const props = defineProps({
    title: { type: String, required: true },
    help: { type: String, default: null },
    url: { type: String, required: true },
    regenerateTitle: { type: String, required: true },
    regenerateBody: { type: String, required: true },
})

const emit = defineEmits(['regenerate'])

const copied = ref(false)
let copiedTimer = null
onBeforeUnmount(() => clearTimeout(copiedTimer))

async function copy() {
    try {
        await navigator.clipboard.writeText(props.url)
    } catch {
        // No clipboard (for example a plain-http page). Nothing to confirm.
        return
    }

    copied.value = true
    clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => { copied.value = false }, 2000)
}

const confirming = ref(false)

function regenerate() {
    confirming.value = false
    emit('regenerate')
}
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0 space-y-1">
            <div class="text-sm font-semibold text-(--color-text-primary)">{{ title }}</div>
            <p v-if="help" class="text-sm text-(--color-text-secondary)">{{ help }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-1.5">
            <ButtonSecondary
                type="button"
                :icon="copied ? 'check-circle' : 'link'"
                class="px-2.5"
                :aria-label="copied ? __('screens.copied') : __('screens.copy')"
                :title="__('screens.copy')"
                data-testid="screen-copy"
                @click="copy"
            />
            <ButtonSecondary
                type="button"
                icon="arrow-path"
                class="px-2.5"
                :aria-label="__('screens.regenerate')"
                :title="__('screens.regenerate')"
                data-testid="screen-regenerate"
                @click="confirming = true"
            />
        </div>

        <ConfirmDialog
            :open="confirming"
            :title="regenerateTitle"
            :confirm-label="__('screens.regenerate_confirm')"
            @confirm="regenerate"
            @cancel="confirming = false"
        >
            {{ regenerateBody }}
        </ConfirmDialog>
    </div>
</template>
