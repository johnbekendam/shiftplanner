<script setup>
defineProps({
    // Pre-rendered HTML from the server, or null/'' when there is no note.
    html: { type: String, default: null },
})
</script>

<template>
    <!-- Server-rendered Markdown. Authored only by an admin. -->
    <div
        v-if="html"
        data-testid="shift-note"
        v-html="html"
        class="space-y-2 text-sm text-(--color-text-primary)
            [&_h1]:mt-1 [&_h1]:text-base [&_h1]:font-semibold [&_h1]:text-(--color-text-heading)
            [&_h2]:mt-1 [&_h2]:text-sm [&_h2]:font-semibold [&_h2]:text-(--color-text-heading)
            [&_h3]:font-semibold [&_h3]:text-(--color-text-heading)
            [&_p]:my-1
            [&_a]:text-(--color-text-link) [&_a]:underline
            [&_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-5
            [&_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-5
            [&_code]:rounded [&_code]:bg-(--color-input-bg) [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-xs
            [&_table]:my-2 [&_table]:w-full [&_table]:border-collapse
            [&_th]:border [&_th]:border-(--color-table-header-separator) [&_th]:bg-(--color-table-header-bg) [&_th]:px-2 [&_th]:py-1 [&_th]:text-left [&_th]:font-medium [&_th]:text-(--color-table-header-text)
            [&_td]:border [&_td]:border-(--color-table-row-separator) [&_td]:px-2 [&_td]:py-1 [&_td]:text-(--color-table-row-text)"
    />
</template>
