<template>
    <!-- Status & feedback: alert banners, the six badge variants, an open
         tooltip, and a running progress bar. The Toast for this category is
         rendered by the frame's overlay slot. Alert, Tooltip and Progress have
         no colour token of their own yet — they borrow the closest existing
         one as a placeholder. -->
    <div class="flex flex-1 flex-col gap-3 overflow-hidden p-1 text-[10px]">
        <span class="text-sm font-bold" style="color: var(--color-text-heading)">Status &amp; feedback</span>

        <!-- Alert banners -->
        <div class="space-y-1.5">
            <div
                v-for="alert in alerts"
                :key="alert.key"
                class="rounded-md border px-2 py-1.5"
                :style="`background-color: var(--color-badge-${alert.key}-bg); color: var(--color-badge-${alert.key}-text); border-color: var(--color-badge-${alert.key}-border);`"
            >
                <span class="font-semibold">{{ alert.title }}</span> — {{ alert.body }}
            </div>
        </div>

        <!-- Badges -->
        <div class="flex flex-wrap gap-1">
            <span
                v-for="badge in badges"
                :key="badge.key"
                class="rounded border px-1.5 py-0.5 font-medium"
                :style="`background-color: var(--color-badge-${badge.key}-bg); color: var(--color-badge-${badge.key}-text); border-color: var(--color-badge-${badge.key}-border);`"
                >{{ badge.label }}</span
            >
        </div>

        <!-- Tooltip on a sample control -->
        <div class="flex items-center gap-2 pt-1">
            <span
                class="inline-flex items-center rounded-md border px-2 py-1 font-semibold"
                style="
                    background-color: var(--color-btn-secondary-bg);
                    color: var(--color-btn-secondary-text);
                    border-color: var(--color-btn-secondary-border);
                "
            >
                Hover target
            </span>
            <span class="relative">
                <span
                    class="rounded px-1.5 py-1 whitespace-nowrap"
                    style="background-color: var(--color-header-bg); color: var(--color-header-text)"
                    >Tooltip label</span
                >
            </span>
            <span style="color: var(--color-text-muted)">tooltip — placeholder colour</span>
        </div>

        <!-- Progress -->
        <div class="space-y-1 pt-1">
            <span style="color: var(--color-text-secondary)">Progress — running</span>
            <div class="h-1.5 overflow-hidden rounded-full" style="background-color: var(--color-input-border)">
                <div class="h-full w-3/5 animate-pulse rounded-full" style="background-color: var(--color-brand-bg)"></div>
            </div>
        </div>

    </div>
</template>

<script setup>
// Info reuses the neutral "standard" badge palette until it gets its own token.
const alerts = [
    { key: 'success', title: 'Success', body: 'Your changes were saved.' },
    { key: 'warning', title: 'Warning', body: 'Some fields need review.' },
    { key: 'error', title: 'Error', body: 'The request could not complete.' },
    { key: 'standard', title: 'Info', body: 'A new version is available.' },
]

const badges = [
    { key: 'success', label: 'Success' },
    { key: 'warning', label: 'Warning' },
    { key: 'error', label: 'Error' },
    { key: 'standard', label: 'Standard' },
    { key: 'custom', label: 'Custom' },
    { key: 'muted', label: 'Muted' },
]
</script>
