<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    // Without an href, the item is a button that only emits click.
    href:   { type: String, default: null },
    active: { type: Boolean, default: false },
})

const classes = computed(() => [
    'flex items-center gap-3 px-3 py-2 rounded-md text-lg font-medium w-full transition-colors',
    props.active
        ? 'bg-(--color-menu-item-selected-bg) text-(--color-menu-item-selected-text)'
        : 'text-(--color-menu-item-text) bg-(--color-menu-item-bg) hover:bg-(--color-menu-item-hover-bg) hover:text-(--color-menu-item-hover-text)',
])
</script>

<template>
    <Link v-if="href" :href="href" :class="classes">
        <slot />
    </Link>
    <button v-else type="button" :class="classes">
        <slot />
    </button>
</template>
