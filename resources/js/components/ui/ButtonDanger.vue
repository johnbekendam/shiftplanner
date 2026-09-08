<template>
    <button v-bind="$attrs" :class="[base, stateClasses]">
        <Icon v-if="icon" :name="icon" :class="iconClass" class="shrink-0" />
        <slot />
        <Icon v-if="trailingIcon" :name="trailingIcon" :class="trailingIconClass" class="shrink-0" />
    </button>
</template>

<script setup>
import { computed, useAttrs } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
    icon:              { type: String, default: null },
    trailingIcon:      { type: String, default: null },
    iconClass:         { type: String, default: 'size-4' },
    trailingIconClass: { type: String, default: 'size-4' },
    // 'normal' | 'hover' | 'disabled' — forces the visual state (for previews).
    state:             { type: String, default: 'normal' },
})
defineOptions({ inheritAttrs: false })

const attrs = useAttrs()

const base =
    'inline-flex w-min items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-1 px-4 py-2'

const NORMAL =
    'border-[var(--color-btn-danger-border)] bg-[var(--color-btn-danger-bg)] text-[var(--color-btn-danger-text)] ' +
    'hover:border-[var(--color-btn-danger-hover-border)] hover:bg-[var(--color-btn-danger-hover-bg)] hover:text-[var(--color-btn-danger-hover-text)]'
const HOVER =
    'border-[var(--color-btn-danger-hover-border)] bg-[var(--color-btn-danger-hover-bg)] text-[var(--color-btn-danger-hover-text)]'
const DISABLED =
    'pointer-events-none cursor-not-allowed border-[var(--color-btn-danger-disabled-border)] bg-[var(--color-btn-danger-disabled-bg)] text-[var(--color-btn-danger-disabled-text)]'

const stateClasses = computed(() => {
    const s = props.state === 'disabled' || attrs.disabled != null ? 'disabled' : props.state
    return s === 'disabled' ? DISABLED : s === 'hover' ? HOVER : NORMAL
})
</script>
