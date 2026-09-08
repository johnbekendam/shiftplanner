<script setup>
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: null },
})
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-(--color-text-primary) mb-1">
            {{ label }}
        </label>
        <slot />
        <p v-if="error" class="mt-1 text-xs text-[var(--color-badge-error-text)]">{{ error }}</p>
    </div>
</template>
