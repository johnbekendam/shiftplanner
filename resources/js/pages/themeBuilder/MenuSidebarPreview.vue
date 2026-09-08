<template>
    <!-- The four menu-item states, shown in the frame sidebar for the Menu tab. -->
    <div class="flex flex-1 flex-col gap-y-0.5">
        <div
            v-for="item in items"
            :key="item.label"
            class="flex items-center gap-2 rounded-md border px-2 py-1.5 text-sm font-medium whitespace-nowrap"
            :style="itemStyle(item.state)"
        >
            <span
                class="block size-3 shrink-0 rounded-sm"
                :style="`background-color: ${textVar(item.state)}`"
            ></span>
            <span class="truncate">{{ item.label }}</span>
        </div>
    </div>
</template>

<script setup>
const items = [
    { label: 'Menu item disabled', state: 'disabled' },
    { label: 'Menu item normal', state: 'resting' },
    { label: 'Menu item hover', state: 'hover' },
    { label: 'Menu item selected', state: 'selected' },
]

const PREFIX = {
    resting: '--color-menu-item',
    hover: '--color-menu-item-hover',
    selected: '--color-menu-item-selected',
    disabled: '--color-menu-item-disabled',
}

function itemStyle(state) {
    const p = PREFIX[state]
    return `background-color: var(${p}-bg); color: var(${p}-text); border-color: var(${p}-border);`
}

function textVar(state) {
    return `var(${PREFIX[state]}-text)`
}
</script>
