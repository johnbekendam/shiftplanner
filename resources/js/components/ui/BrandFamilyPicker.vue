<template>
    <div class="inline-flex items-center gap-2">
        <button
            ref="triggerRef"
            type="button"
            :title="modelValue"
            class="block rounded-md border border-[var(--color-card-body-border)] p-1 text-sm"
            @click.stop="toggleOpen"
        >
            <span class="block size-6 rounded" :style="`background-color: var(--color-${modelValue}-500)`"></span>
        </button>

        <Teleport to="body">
            <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>
            <div
                v-if="isOpen"
                :style="`top: ${top}px; left: ${left}px`"
                class="fixed z-50 grid grid-cols-4 gap-1 rounded-lg border border-zinc-200 bg-white p-2 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                @click.stop
            >
                <button
                    v-for="family in families"
                    :key="family"
                    type="button"
                    class="flex flex-col items-center gap-1 rounded p-1 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    :class="family === modelValue ? 'ring-2 ring-blue-500' : ''"
                    @click.stop="select(family)"
                >
                    <span class="block size-7 rounded" :style="`background-color: var(--color-${family}-500)`"></span>
                    <span class="font-mono text-[10px] text-zinc-500 dark:text-zinc-400">{{ family }}</span>
                </button>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { nextTick, ref } from 'vue'
import { colorFamilyGroups } from '@/utils/colorToken.js'

defineProps({
    modelValue: { type: String, required: true },
})
const emit = defineEmits(['update:modelValue'])

const families = colorFamilyGroups.flat()

const isOpen = ref(false)
const triggerRef = ref(null)
const top = ref(0)
const left = ref(0)

function toggleOpen() {
    if (isOpen.value) {
        isOpen.value = false
        return
    }
    const r = triggerRef.value.getBoundingClientRect()
    top.value = r.bottom + 4
    left.value = r.left
    isOpen.value = true
    nextTick()
}

function select(family) {
    emit('update:modelValue', family)
    isOpen.value = false
}
</script>
