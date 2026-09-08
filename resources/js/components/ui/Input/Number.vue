<template>
    <Shell v-bind="$attrs">
        <FormattedInput
            inputmode="numeric"
            :model-value="model != null ? String(model) : ''"
            :format="format"
            :validate="validate"
            :disabled="disabled"
            :live="live"
            class="min-w-0 flex-1 px-3 py-2 text-sm"
            @update:modelValue="onCommit"
        />
        <span
            v-if="unit"
            class="pr-3 pointer-events-none select-none text-(--color-input-placeholder)"
        >{{ unit }}</span>
    </Shell>
</template>

<script setup>
import { computed } from 'vue'
import Shell from './Shell.vue'
import FormattedInput from './FormattedInput.vue'

const props = defineProps({
    min:      { type: Number, default: null },
    max:      { type: Number, default: null },
    step:     { type: Number, default: 1 },
    disabled: { type: Boolean, default: false },
    unit:     { type: String, default: null },
    // Emit update:modelValue as soon as the in-progress value is valid,
    // instead of only on commit (Enter/Tab/blur).
    live:     { type: Boolean, default: false },
})

const model = defineModel({ type: Number, default: null })
defineOptions({ inheritAttrs: false })

const allowDecimals = computed(() => !Number.isInteger(props.step))

function format(chars) {
    const result = []
    let hasDot = false
    for (const c of chars) {
        if (c === '-' && result.length === 0 && (props.min === null || props.min < 0)) {
            result.push(c)
            continue
        }
        if (allowDecimals.value && c === '.' && !hasDot) {
            hasDot = true
            result.push(c)
            continue
        }
        if (/\d/.test(c)) result.push(c)
    }
    return result.join('')
}

function validate(str) {
    const n = allowDecimals.value ? parseFloat(str) : parseInt(str, 10)
    if (isNaN(n)) return null
    if (props.min !== null && n < props.min) return null
    if (props.max !== null && n > props.max) return null
    return String(n)
}

function onCommit(str) {
    model.value = str === '' ? null : (allowDecimals.value ? parseFloat(str) : parseInt(str, 10))
}
</script>
