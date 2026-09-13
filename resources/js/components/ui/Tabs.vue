<script setup>
defineProps({
    // [{ value, label }]
    tabs: { type: Array, required: true },
    modelValue: { type: [String, Number], required: true },
})

defineEmits(['update:modelValue'])
</script>

<template>
    <div class="flex w-full bg-(--color-tab-bg) border-b border-(--color-tab-separator)">
        <button
            v-for="t in tabs"
            :key="t.value"
            type="button"
            class="flex-1 -mb-px whitespace-nowrap border-b-[3px] px-4 py-2.5 text-center text-sm font-medium transition-colors"
            :class="modelValue === t.value
                ? 'border-(--color-tab-active-border) bg-(--color-tab-active-bg) text-(--color-tab-active-text)'
                : 'border-transparent bg-(--color-tab-inactive-bg) text-(--color-tab-text) hover:border-(--color-tab-hover-border) hover:text-(--color-tab-hover-text)'"
            @click="$emit('update:modelValue', t.value)"
        >
            {{ t.label }}
            <span
                v-if="t.hasError"
                data-testid="tab-error-dot"
                class="ml-1 inline-block size-1.5 rounded-full bg-(--color-badge-error-bg)"
            />
            <span
                v-else-if="t.dirty"
                data-testid="tab-dirty-dot"
                class="ml-1 inline-block size-1.5 rounded-full bg-(--color-badge-warning-bg)"
            />
        </button>
    </div>
</template>
