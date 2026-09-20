<script setup>
import AppLogo from '@/components/AppLogo.vue'

// The wall-screen shell: no sidebar, no navigation, the whole viewport.
// Not a Layout.vue wrapper — that shell is a padded, max-width frame.
defineProps({
    title: { type: String, required: true },
})
</script>

<template>
    <div class="flex min-h-dvh flex-col bg-(--color-page-bg) text-(--color-content-text)">
        <header class="flex items-center gap-4 border-b border-(--color-separator-horizontal) bg-(--color-header-bg) px-6 py-4 text-(--color-header-text) shadow-md">
            <AppLogo />
            <h1 class="min-w-0 flex-1 truncate text-2xl font-semibold">{{ title }}</h1>
            <div class="shrink-0 text-lg">
                <slot name="status" />
            </div>
        </header>

        <main class="flex flex-1 flex-col gap-6 bg-(--color-content-bg) p-6">
            <slot />
        </main>
    </div>
</template>
