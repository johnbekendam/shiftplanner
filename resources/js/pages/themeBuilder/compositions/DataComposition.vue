<template>
    <!-- A realistic list screen: a table with header / row / row hover / row
         selected, and pagination in the card's own footer. Table and
         Pagination have no components yet — mockup markup. The tab bar has
         its own composition/page. -->
    <div class="flex flex-1 flex-col gap-3 overflow-hidden p-1 text-[10px]">
        <div class="flex items-baseline justify-between px-0.5">
            <span class="text-sm font-bold" style="color: var(--color-text-heading)">Records</span>
            <span style="color: var(--color-text-secondary)">97 total</span>
        </div>

        <Card class="flex flex-1 flex-col overflow-hidden" header-class="">
            <template #header>
                <div
                    class="flex px-3 py-1.5 text-[9px] font-semibold tracking-wide uppercase"
                    style="
                        background-color: var(--color-table-header-bg);
                        border-bottom: 1px solid var(--color-table-header-separator);
                        color: var(--color-table-header-text);
                    "
                >
                    <span class="flex-1">Name</span>
                    <span class="w-20 text-center">Status</span>
                    <span class="w-12 text-center">Action</span>
                </div>
            </template>

            <div
                v-for="(row, i) in rows"
                :key="row.name"
                class="flex items-center px-3 py-2"
                :style="rowStyle(row, i)"
            >
                <span class="flex-1">
                    {{ row.name }}
                    <span v-if="row.note" class="opacity-50">({{ row.note }})</span>
                </span>
                <span class="w-20 text-center">{{ row.status }}</span>
                <span class="w-12 text-center">Edit</span>
            </div>

            <template #footer>
                <div data-testid="table-pagination" class="flex items-center justify-between px-3 py-2">
                    <span class="text-[9px]" style="color: var(--color-pagination-muted-text)">1–15 of 97</span>
                    <div class="isolate inline-flex -space-x-px rounded text-[9px]">
                        <span :class="pageBase" class="rounded-l" :style="pageStyle('muted')">‹</span>
                        <span :class="pageBase" :style="pageStyle('normal')">1</span>
                        <span :class="pageBase" :style="pageStyle('hover')">4</span>
                        <span :class="pageBase" class="font-semibold" :style="pageStyle('active')">5</span>
                        <span :class="pageBase" :style="pageStyle('normal')">6</span>
                        <span :class="pageBase" class="rounded-r" :style="pageStyle('normal')">›</span>
                    </div>
                </div>
            </template>
        </Card>
    </div>
</template>

<script setup>
import Card from '@/components/ui/Card.vue'

const rows = [
    { name: 'Record one', status: 'Active', note: null, state: 'normal' },
    { name: 'Record two', status: 'Active', note: 'hover', state: 'hover' },
    { name: 'Record three', status: 'Draft', note: 'selected', state: 'selected' },
    { name: 'Record four', status: 'Active', note: null, state: 'normal' },
    { name: 'Record five', status: 'Draft', note: null, state: 'normal' },
    { name: 'Record six', status: 'Active', note: null, state: 'normal' },
    { name: 'Record seven', status: 'Active', note: null, state: 'normal' },
    { name: 'Record eight', status: 'Draft', note: null, state: 'normal' },
]

function rowStyle(row, i) {
    const map = {
        normal: ['--color-table-row-bg', '--color-table-row-text'],
        hover: ['--color-table-row-hover-bg', '--color-table-row-hover-text'],
        selected: ['--color-table-row-selected-bg', '--color-table-row-selected-text'],
    }
    const [bg, text] = map[row.state]
    const border = i > 0 ? 'border-top: 1px solid var(--color-table-row-separator);' : ''
    return `background-color: var(${bg}); color: var(${text}); ${border}`
}

const pageBase = 'inline-flex min-w-[1.4rem] items-center justify-center px-1.5 py-0.5'

function pageStyle(kind) {
    if (kind === 'active') {
        return 'color: var(--color-pagination-active-text); background-color: var(--color-pagination-active-bg);'
    }
    if (kind === 'hover') {
        return 'color: var(--color-pagination-hover-text); background-color: var(--color-pagination-hover-bg); outline: 1px solid var(--color-pagination-hover-border);'
    }
    const textVar = kind === 'muted' ? '--color-pagination-muted-text' : '--color-pagination-text'
    return `color: var(${textVar}); background-color: var(--color-pagination-bg); outline: 1px solid var(--color-pagination-border);`
}
</script>
