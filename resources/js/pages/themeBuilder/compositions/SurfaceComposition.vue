<template>
    <!-- A realistic surface-heavy screen: page heading and a row of cards. The
         Modal for this category is rendered by the frame's overlay slot. -->
    <div class="flex flex-1 flex-col gap-3 overflow-hidden">
        <div class="flex items-baseline justify-between px-0.5">
            <span class="text-sm font-bold" style="color: var(--color-text-heading)">Overview</span>
            <span class="text-[10px]" style="color: var(--color-text-secondary)">Last updated just now</span>
        </div>

        <div class="grid flex-1 grid-cols-3 gap-3 overflow-hidden text-[10px]">
            <Card class="flex min-w-0 flex-col">
                <template #header>
                    <p class="px-3 py-2 text-xs font-semibold">Summary</p>
                </template>
                <div class="space-y-1 p-3">
                    <p class="text-lg font-bold" style="color: var(--color-text-heading)">128</p>
                    <p style="color: var(--color-text-muted)">records this month</p>
                </div>
            </Card>

            <Card class="flex min-w-0 flex-col">
                <template #header>
                    <p class="px-3 py-2 text-xs font-semibold">Recent</p>
                </template>
                <div class="space-y-1.5 p-3">
                    <p v-for="n in 3" :key="n">Item {{ n }}</p>
                    <p style="color: var(--color-text-muted)">and 12 more</p>
                </div>
            </Card>

            <Card class="flex min-w-0 flex-col">
                <template #header>
                    <p class="px-3 py-2 text-xs font-semibold">Notes</p>
                </template>
                <div class="space-y-1 p-3">
                    <p>Body text inside a card.</p>
                    <p style="color: var(--color-text-muted)">Muted supporting line.</p>
                </div>
                <template #footer>
                    <p class="px-3 py-1.5 text-[9px]" style="color: var(--color-text-secondary)">Edited 2 days ago</p>
                </template>
            </Card>
        </div>
    </div>
</template>

<script setup>
import Card from '@/components/ui/Card.vue'
</script>
