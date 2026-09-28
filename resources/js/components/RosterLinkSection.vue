<script setup>
import { ref, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { useI18n } from '@/composables/useI18n'

// The public roster link on the Settings General tab: copy and regenerate
// buttons, with the URL itself kept off screen. See features/roster-public-link/.
const __ = useI18n()

defineProps({
    url: { type: String, required: true },
})

const copied = ref(false)
let copiedTimer = null
onBeforeUnmount(() => clearTimeout(copiedTimer))

async function copy(url) {
    try {
        await navigator.clipboard.writeText(url)
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
    router.post('/settings/roster-token', {}, { preserveScroll: true, preserveState: true })
}
</script>

<template>
    <section class="flex items-center justify-between gap-4">
        <div class="min-w-0 space-y-1">
            <h2 class="text-sm font-semibold text-(--color-text-primary)">{{ __('settings.roster_link.title') }}</h2>
            <p class="text-sm text-(--color-text-secondary)">{{ __('settings.roster_link.help') }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-1.5">
            <ButtonSecondary
                type="button"
                :icon="copied ? 'check-circle' : 'link'"
                class="px-2.5"
                :aria-label="copied ? __('settings.roster_link.copied') : __('settings.roster_link.copy')"
                :title="__('settings.roster_link.copy')"
                data-testid="roster-link-copy"
                @click="copy(url)"
            />
            <ButtonSecondary
                type="button"
                icon="arrow-path"
                class="px-2.5"
                :aria-label="__('settings.roster_link.regenerate')"
                :title="__('settings.roster_link.regenerate')"
                data-testid="roster-link-regenerate"
                @click="confirming = true"
            />
        </div>

        <ConfirmDialog
            :open="confirming"
            :title="__('settings.roster_link.regenerate_title')"
            :confirm-label="__('settings.roster_link.regenerate_confirm')"
            @confirm="regenerate"
            @cancel="confirming = false"
        >
            {{ __('settings.roster_link.regenerate_body') }}
        </ConfirmDialog>
    </section>
</template>
