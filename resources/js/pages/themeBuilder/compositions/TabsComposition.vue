<template>
    <!-- Tabs usually sit in a card's header, so the specimen is the real
         Card component with the tab bar as its #header — not a bare mockup. -->
    <div class="flex flex-1 flex-col gap-3 overflow-hidden p-1 text-[10px]">
        <div class="flex items-baseline justify-between px-0.5">
            <span class="text-sm font-bold" style="color: var(--color-text-heading)">Records</span>
            <span style="color: var(--color-text-secondary)">97 total</span>
        </div>

        <Card class="flex flex-1 flex-col overflow-hidden" header-class="">
            <template #header>
                <div
                    class="flex"
                    style="
                        background-color: var(--color-tab-bg);
                        border-bottom: 1px solid var(--color-tab-separator);
                    "
                >
                    <div
                        class="flex-1 border-b-2 px-3 py-1.5 text-center font-semibold"
                        style="
                            border-color: var(--color-tab-active-border);
                            background-color: var(--color-tab-active-bg);
                            color: var(--color-tab-active-text);
                        "
                    >
                        Active
                    </div>
                    <div
                        class="flex-1 border-b-2 px-3 py-1.5 text-center"
                        style="
                            border-color: var(--color-tab-hover-border);
                            background-color: var(--color-tab-hover-bg);
                            color: var(--color-tab-hover-text);
                        "
                    >
                        Archived <span class="opacity-50">(hover)</span>
                    </div>
                    <div
                        class="flex-1 border-b-2 px-3 py-1.5 text-center"
                        style="
                            border-color: var(--color-tab-border);
                            background-color: var(--color-tab-inactive-bg);
                            color: var(--color-tab-text);
                        "
                    >
                        All
                    </div>
                    <div
                        class="flex-1 border-b-2 px-3 py-1.5 text-center"
                        style="
                            border-color: var(--color-tab-border);
                            background-color: var(--color-tab-inactive-bg);
                            color: var(--color-tab-text);
                        "
                    >
                        Drafts
                    </div>
                </div>
            </template>

            <div class="flex-1 p-3" style="color: var(--color-text-muted)">
                Tab content.
            </div>
        </Card>
    </div>
</template>

<script setup>
import Card from '@/components/ui/Card.vue'
</script>
