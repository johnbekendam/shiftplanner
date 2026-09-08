<template>
    <textarea
        v-bind="$attrs"
        :value="model"
        @input="model = $event.target.value"
        :class="[
            'block w-full rounded-lg px-3 py-1.5 text-sm transition duration-100',
            'bg-[var(--color-input-bg)]',
            'text-[var(--color-input-text)]',
            'outline outline-1 -outline-offset-1 outline-[var(--color-input-border)]',
            'focus:outline-2 focus:-outline-offset-2 focus:outline-[var(--color-input-focus-border)]',
            'disabled:bg-[var(--color-input-disabled-bg)]',
            'disabled:text-[var(--color-input-disabled-text)]',
            'disabled:outline-[var(--color-input-disabled-border)]',
        ]"
    />
</template>

<script setup>
const model = defineModel()
defineOptions({ inheritAttrs: false })
</script>
