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
    'border-[var(--color-btn-secondary-border)] bg-[var(--color-btn-secondary-bg)] text-[var(--color-btn-secondary-text)] ' +
    'hover:border-[var(--color-btn-secondary-hover-border)] hover:bg-[var(--color-btn-secondary-hover-bg)] hover:text-[var(--color-btn-secondary-hover-text)]'
const HOVER =
    'border-[var(--color-btn-secondary-hover-border)] bg-[var(--color-btn-secondary-hover-bg)] text-[var(--color-btn-secondary-hover-text)]'
const DISABLED =
    'pointer-events-none cursor-not-allowed border-[var(--color-btn-secondary-disabled-border)] bg-[var(--color-btn-secondary-disabled-bg)] text-[var(--color-btn-secondary-disabled-text)]'

const stateClasses = computed(() => {
    const s = props.state === 'disabled' || attrs.disabled != null ? 'disabled' : props.state
    return s === 'disabled' ? DISABLED : s === 'hover' ? HOVER : NORMAL
})
</script>
