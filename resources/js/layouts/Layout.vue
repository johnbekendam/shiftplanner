<script setup>
import { useBrandCss } from '@/composables/useBrandCss'

const props = defineProps({
    showSidebar: { type: Boolean, default: true },
    sidebarOpen: { type: Boolean, default: false },
    brandCss:    { type: String, default: null },
})

defineEmits(['close-sidebar'])

useBrandCss(props.brandCss)
</script>

<template>
    <div class="flex flex-col h-dvh">

        <!-- ── Top row: Logo area + TopBar ─────────────────────────────────── -->
        <div class="relative z-10 flex h-16 flex-shrink-0 border-b border-(--color-separator-horizontal) shadow-md">

            <!-- Logo area — same width as sidebar, desktop only -->
            <div
                v-if="showSidebar"
                class="hidden lg:flex w-64 flex-shrink-0 items-center px-8 bg-(--color-logo-bg)"
            >
                <slot name="logo" />
            </div>

            <!-- TopBar — fills remaining width -->
            <header class="flex-1 flex items-center bg-(--color-header-bg) px-4 text-(--color-header-text)">
                <slot name="topbar" />
            </header>

        </div>

        <!-- ── Bottom row: Sidebar + Page ──────────────────────────────────── -->
        <div class="flex flex-1 overflow-hidden">

            <template v-if="showSidebar">
                <!-- Backdrop (mobile only) -->
                <Transition
                    enter-active-class="transition-opacity ease-out duration-200"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition-opacity ease-in duration-150"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="sidebarOpen"
                        class="fixed inset-0 z-30 lg:hidden bg-(--color-overlay)"
                        @click="$emit('close-sidebar')"
                    />
                </Transition>

                <!-- Sidebar panel -->
                <!-- Mobile: fixed off-canvas overlay; Desktop: static in flex flow -->
                <aside
                    :class="[
                        'w-64 flex-shrink-0 bg-(--color-sidebar-bg) border-r border-(--color-separator-vertical) overflow-y-auto shadow-md',
                        'fixed inset-y-0 left-0 z-40 lg:static lg:translate-x-0',
                        'transition-transform duration-200 ease-in-out',
                        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                    ]"
                >
                    <slot name="sidebar" />
                </aside>
            </template>

            <!-- Page area -->
            <main class="flex-1 overflow-y-auto bg-(--color-content-bg)">
                <slot />
            </main>

        </div>

    </div>
</template>
