<script setup>
import { usePage } from '@inertiajs/vue3'
import Layout from '@/layouts/Layout.vue'
import AppLogo from '@/components/AppLogo.vue'
import Card from '@/components/ui/Card.vue'

const page = usePage()

// Login-shaped pages sit centred in the viewport. Pages whose card
// changes height as the user interacts with it (tabs, expanding panels)
// pass `top` so the card keeps a fixed position instead of drifting
// vertically on every content swap.
const props = defineProps({
    align: {
        type: String,
        default: 'center',
        validator: (v) => ['center', 'top'].includes(v),
    },
    // Card width. Login-shaped pages keep the default; content-heavy
    // pages (the personal page) pass a wider value.
    width: {
        type: String,
        default: 'md',
        validator: (v) => ['md', 'lg'].includes(v),
    },
})

const widthClass = { md: 'max-w-md', lg: 'max-w-lg' }[props.width]
</script>

<template>
    <Layout :show-sidebar="false">

        <!-- TopBar: logo + app name -->
        <template #topbar>
            <div class="flex items-center gap-3">
                <AppLogo />
                <span class="text-lg font-medium text-(--color-header-text)">{{ page.props.appName }}</span>
            </div>
        </template>

        <!-- Page: centered form card -->
        <div
            class="flex flex-col items-center min-h-full px-4"
            :class="align === 'top' ? 'justify-start pt-8 pb-12' : 'justify-center py-12'"
        >
            <Card class="w-full" :class="widthClass">
                <template v-if="$slots.header || $slots.title" #header>
                    <slot name="header">
                        <div class="px-10 py-4 text-base font-semibold">
                            <slot name="title" />
                        </div>
                    </slot>
                </template>

                <div class="px-10 pt-8 pb-6">
                    <slot />
                </div>
            </Card>
        </div>

    </Layout>
</template>
