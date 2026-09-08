<template>
    <div
        v-bind="$attrs"
        :class="[
            'inline-flex items-center rounded-lg border border-transparent transition duration-100',
            'bg-[var(--color-input-bg)]',
            'outline outline-1 -outline-offset-1 outline-[var(--color-input-border)]',
            'has-[:disabled]:bg-[var(--color-input-disabled-bg)]',
            'has-[:disabled]:outline-[var(--color-input-disabled-border)]',
            'focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-[var(--color-input-focus-border)]',
            'has-[[data-invalid]]:outline-[var(--color-input-invalid-border)]',
            'has-[[data-invalid]]:focus-within:outline-[var(--color-input-invalid-border)]',
        ]"
        @click="onClick"
    >
        <slot />
    </div>
</template>

<script setup>
defineOptions({ inheritAttrs: false })

function onClick(e) {
    // Focus the first non-disabled input inside when clicking the shell itself
    // (e.g. padding area). Avoids stealing focus from buttons inside the shell.
    if (e.target === e.currentTarget) {
        e.currentTarget.querySelector('input:not([disabled])')?.focus()
    }
}
</script>
