<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    src: { type: String, default: null },
})

const page = usePage()
const src = computed(() => props.src ?? page.props.logoUrl ?? '/images/logo.svg')
</script>

<template>
    <img :src="src" alt="Logo" class="h-6 sm:h-8 w-auto" />
</template>
