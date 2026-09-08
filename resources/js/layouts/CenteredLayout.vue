<script setup>
import { usePage } from '@inertiajs/vue3'
import Layout from '@/layouts/Layout.vue'
import AppLogo from '@/components/AppLogo.vue'
import Card from '@/components/ui/Card.vue'

const page = usePage()
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
        <div class="flex flex-col items-center justify-center min-h-full py-12 px-4">
            <Card class="w-full max-w-md">
                <template v-if="$slots.title" #header>
                    <div class="px-10 py-4 text-base font-semibold">
                        <slot name="title" />
                    </div>
                </template>

                <div class="px-10 pt-8 pb-6">
                    <slot />
                </div>
            </Card>
        </div>

    </Layout>
</template>
