<template>
    <!-- Modal held open over the whole frame. The backdrop dims the sidebar and
         header too, not just the content area. -->
    <div class="absolute inset-0 flex items-center justify-center">
        <div class="absolute inset-0" style="background-color: var(--color-overlay)"></div>
        <div
            class="relative z-10 flex w-64 flex-col rounded-xl border shadow-xl"
            style="
                background-color: var(--color-card-body-bg);
                border-color: var(--color-card-border);
                color: var(--color-card-body-text);
            "
        >
            <div class="flex items-center justify-between border-b px-3 py-2" style="border-color: var(--color-card-border)">
                <span class="text-xs font-semibold">Dialog title</span>
                <Icon name="x-mark" class="size-3.5" style="color: var(--color-text-secondary)" />
            </div>
            <div class="space-y-1 p-3 text-[10px]">
                <p>A modal panel opens over the page on a dimmed backdrop.</p>
                <p style="color: var(--color-text-muted)">Held open so its colours stay visible.</p>
            </div>
            <div class="flex justify-end gap-1.5 px-3 pb-3">
                <ButtonSecondary class="!px-2 !py-1 text-[10px]">Cancel</ButtonSecondary>
                <ButtonPrimary class="!px-2 !py-1 text-[10px]">Confirm</ButtonPrimary>
            </div>
        </div>
    </div>
</template>

<script setup>
import Icon from '@/components/ui/Icon.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
</script>
