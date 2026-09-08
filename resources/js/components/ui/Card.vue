<template>
    <div
        v-bind="$attrs"
        :class="[
            'overflow-hidden rounded-lg border',
            'bg-[var(--color-card-body-bg)]',
            'text-[var(--color-card-body-text)]',
            'border-[var(--color-card-border)]',
        ]"
    >
        <div
            v-if="$slots.header"
            :class="[
                'overflow-hidden rounded-t-lg border-b border-[var(--color-card-header-border)]',
                headerClass ?? 'bg-[var(--color-card-header-bg)] text-[var(--color-card-header-text)]',
            ]"
        >
            <slot name="header" />
        </div>

        <slot />

        <div
            v-if="$slots.footer"
            class="overflow-hidden rounded-b-lg border-t border-[var(--color-card-footer-border)] bg-[var(--color-card-footer-bg)] text-[var(--color-card-footer-text)]"
        >
            <slot name="footer" />
        </div>
    </div>
</template>

<script setup>
defineOptions({ inheritAttrs: false })
defineProps({
    headerClass: { type: String, default: null },
})
</script>
