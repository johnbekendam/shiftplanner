<template>
    <!-- Always-visible fake app screen. The content slot swaps per category;
         the chrome (logo, header, sidebar, page background) never changes.
         CSS custom properties are injected via :style — see AGENTS.md for the
         inline style exception. -->
    <!-- Width fits the container: full-bleed on the standalone builder page,
         fitted when embedded in a future app layout. -->
    <div
        data-theme-preview
        :style="styleVars"
        class="w-full mb-6 rounded-xl border border-zinc-300 p-2 dark:border-zinc-600"
        style="background-color: var(--color-page-bg)"
    >
        <div class="relative flex overflow-hidden rounded-lg" style="min-height: 436px">
            <!-- Left column: logo + sidebar -->
            <div class="flex w-60 shrink-0 flex-col">
                <div
                    class="flex h-14 items-center border-b px-4"
                    style="background-color: var(--color-logo-bg); border-color: var(--color-separator-horizontal)"
                >
                    <img v-if="logoUrl" :src="logoUrl" alt="Logo" class="h-7 w-auto object-contain" />
                    <span v-else class="text-sm font-bold tracking-wide" style="color: var(--color-logo-text)"
                        >APP-NAME</span
                    >
                </div>

                <div
                    class="flex flex-1 flex-col gap-y-0.5 border-r p-3"
                    style="background-color: var(--color-sidebar-bg); border-color: var(--color-separator-vertical)"
                >
                    <slot name="sidebar">
                        <!-- A specimen of the sidebar text colour. -->
                        <div
                            class="mb-1 px-2 text-[10px] font-semibold tracking-wider"
                            style="color: var(--color-sidebar-text)"
                        >
                            SIDEBAR Text
                        </div>
                    </slot>
                </div>
            </div>

            <!-- Right column: header + content -->
            <div class="flex flex-1 flex-col overflow-hidden">
                <div
                    class="flex h-14 items-center gap-3 border-b px-5"
                    style="background-color: var(--color-header-bg); border-color: var(--color-separator-horizontal)"
                >
                    <nav
                        data-testid="frame-breadcrumb"
                        class="flex items-center gap-1.5 text-sm"
                        style="color: var(--color-header-text)"
                    >
                        <Icon name="home" class="size-4 shrink-0 opacity-70" />
                        <template v-for="(crumb, i) in crumbs" :key="i">
                            <span class="opacity-50">/</span>
                            <span :class="{ 'font-semibold': i === crumbs.length - 1 }">{{ crumb }}</span>
                        </template>
                    </nav>

                    <div class="ml-auto flex items-center" style="color: var(--color-header-text)">
                        <slot name="header-actions" />
                    </div>
                </div>

                <div
                    class="flex flex-1 flex-col gap-3 overflow-hidden p-3"
                    style="background-color: var(--color-content-bg)"
                >
                    <slot />
                </div>
            </div>

            <!-- Overlay layer: modal, toast etc. sit over the whole frame,
                 dimming the sidebar and header too. -->
            <div v-if="$slots.overlay" class="absolute inset-0 z-10">
                <slot name="overlay" />
            </div>
        </div>
    </div>
</template>

<script setup>
import Icon from '@/components/ui/Icon.vue'

defineProps({
    styleVars: { type: String, default: '' },
    logoUrl: { type: String, default: null },
    crumbs: { type: Array, default: () => [] },
})
</script>
