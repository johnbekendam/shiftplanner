<template>
    <label
        class="inline-flex items-center gap-2 select-none"
        :class="disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'"
    >
        <div class="relative flex shrink-0 items-center justify-center">
            <input
                v-bind="$attrs"
                type="checkbox"
                :checked="isChecked"
                :disabled="disabled"
                @change="onChange"
                class="peer size-4 cursor-pointer appearance-none rounded border transition-colors focus:ring-2 focus:ring-[var(--color-input-focus-border)] focus:ring-offset-1 focus:outline-none disabled:cursor-not-allowed"
                :class="variant === 'danger-cross'
                    ? 'border-[var(--color-input-border)] bg-[var(--color-input-bg)] checked:border-[var(--color-btn-danger-bg)] checked:bg-[var(--color-btn-danger-bg)]'
                    : 'border-[var(--color-input-border)] bg-[var(--color-input-bg)] checked:border-[var(--color-brand-bg)] checked:bg-[var(--color-brand-bg)]'"
            />
            <svg
                v-if="variant === 'danger-cross'"
                class="pointer-events-none absolute hidden text-white peer-checked:block"
                width="10"
                height="10"
                viewBox="0 0 10 10"
                fill="none"
            >
                <path
                    d="M1.5 1.5l7 7M8.5 1.5l-7 7"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
            <svg
                v-else
                class="pointer-events-none absolute hidden text-white peer-checked:block"
                width="10"
                height="10"
                viewBox="0 0 10 10"
                fill="none"
            >
                <path
                    d="M1.5 5l2.5 2.5 4.5-4.5"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </div>
        <span v-if="$slots.default" class="text-sm text-[var(--color-input-text)]">
            <slot />
        </span>
    </label>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    disabled: { type: Boolean, default: false },
    value: { default: undefined },
    variant: { type: String, default: 'default' },
})

const model = defineModel({ default: false })
defineOptions({ inheritAttrs: false })

const isChecked = computed(() => {
    if (props.value !== undefined) {
        return Array.isArray(model.value) && model.value.includes(props.value)
    }
    return model.value
})

function onChange(e) {
    if (props.value !== undefined) {
        const arr = Array.isArray(model.value) ? [...model.value] : []
        model.value = e.target.checked ? [...arr, props.value] : arr.filter((v) => v !== props.value)
    } else {
        model.value = e.target.checked
    }
}
</script>
