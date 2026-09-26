<template>
    <hr :class="['-mx-6 border-[var(--color-card-border)]', flushTop ? 'mt-0 mb-6' : 'my-6']" />
</template>

<script setup>
defineProps({
    // No top margin: the line sits close under the content above it.
    flushTop: { type: Boolean, default: false },
})
</script>
