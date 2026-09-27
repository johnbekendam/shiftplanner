<script setup>
import { ref, watch } from 'vue'
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
        validator: (v) => ['md', 'lg', 'xl'].includes(v),
    },
    // The card is at most as tall as the page area: header and footer stay
    // on screen and the body scrolls.
    fitHeight: { type: Boolean, default: false },
    // A change scrolls the body back to its top, e.g. the current tab.
    scrollKey: { type: [String, Number], default: null },
})

const body = ref(null)
watch(() => props.scrollKey, () => {
    if (body.value) body.value.scrollTop = 0
})

const widthClass = { md: 'max-w-md', lg: 'max-w-xl', xl: 'max-w-2xl' }[props.width]
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
            data-testid="centered-frame"
            class="flex flex-col items-center px-4"
            :class="[
                fitHeight ? 'h-full' : 'min-h-full',
                align === 'top' ? 'justify-start pt-8 pb-12' : 'justify-center py-12',
            ]"
        >
            <div v-if="$slots.banner" class="w-full mb-4" :class="widthClass">
                <slot name="banner" />
            </div>

            <Card
                data-testid="centered-card"
                class="w-full"
                :class="[widthClass, fitHeight ? 'flex max-h-full min-h-0 flex-col' : '']"
                footer-class="bg-[var(--color-card-body-bg)] text-[var(--color-card-body-text)]"
            >
                <template v-if="$slots.header || $slots.title" #header>
                    <slot name="header">
                        <div class="px-10 py-4 text-base font-semibold">
                            <slot name="title" />
                        </div>
                    </slot>
                </template>

                <div
                    ref="body"
                    data-testid="centered-body"
                    class="px-10 pt-8 pb-6"
                    :class="fitHeight ? 'min-h-0 flex-1 overflow-y-auto' : ''"
                >
                    <slot />
                </div>

                <template v-if="$slots.footer" #footer>
                    <div data-testid="centered-footer" class="px-10 py-4">
                        <slot name="footer" />
                    </div>
                </template>
            </Card>
        </div>

    </Layout>
</template>
