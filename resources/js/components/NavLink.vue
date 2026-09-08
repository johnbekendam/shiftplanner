<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    href:   { type: String, required: true },
    active: { type: Boolean, default: false },
})
</script>

<template>
    <Link
        :href="href"
        :class="[
            'flex items-center gap-3 px-3 py-2 rounded-md text-lg font-medium w-full transition-colors',
            active
                ? 'bg-(--color-menu-item-selected-bg) text-(--color-menu-item-selected-text)'
                : 'text-(--color-menu-item-text) bg-(--color-menu-item-bg) hover:bg-(--color-menu-item-hover-bg) hover:text-(--color-menu-item-hover-text)',
        ]"
    >
        <slot />
    </Link>
</template>
