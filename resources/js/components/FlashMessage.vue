<script setup>
import { usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const page = usePage()
const visible = ref(false)
let timer = null

// Only surface problems. A successful action passes without a banner.
const message = computed(() => page.props.flash?.error || null)

watch(message, (val) => {
    if (val) {
        visible.value = true
        clearTimeout(timer)
        timer = setTimeout(() => { visible.value = false }, 4000)
    }
}, { immediate: true })

function dismiss() {
    visible.value = false
    clearTimeout(timer)
}
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
    >
        <div
            v-if="visible && message"
            class="flex items-center justify-between bg-(--color-badge-error-bg) px-4 py-3 text-sm font-medium text-(--color-badge-error-text)"
        >
            <span>{{ message }}</span>
            <button @click="dismiss" class="ml-4 text-lg leading-none opacity-60 hover:opacity-100">&times;</button>
        </div>
    </Transition>
</template>
