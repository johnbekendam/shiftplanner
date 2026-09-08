<template>
    <Shell v-bind="$attrs">
        <FormattedInput
            inputmode="tel"
            autocomplete="tel"
            :model-value="model"
            :format="format"
            :validate="validate"
            class="min-w-0 flex-1 px-3 py-2 text-sm"
            @update:modelValue="model = $event"
        />
    </Shell>
</template>

<script setup>
import Shell from './Shell.vue'
import FormattedInput from './FormattedInput.vue'

const model = defineModel({ type: String, default: '' })
defineOptions({ inheritAttrs: false })

const ALLOWED = /[0-9+\-(). ]/
const IS_SEP = /[,;:/\\]/

function format(chars) {
    const segments = [[]]

    for (const c of chars) {
        if (IS_SEP.test(c)) {
            if (segments[segments.length - 1].length > 0) segments.push([])
        } else if (ALLOWED.test(c)) {
            segments[segments.length - 1].push(c)
        }
    }

    // Trim each segment; drop a leading empty segment (separator typed first)
    const cleaned = segments.map((seg) => seg.join('').trim())
    while (cleaned.length > 1 && cleaned[0] === '') cleaned.shift()

    return cleaned.join(' , ')
}

function validate(str) {
    const phones = str
        .split(' , ')
        .map((s) => s.trim())
        .filter(Boolean)
    if (phones.length === 0) return null
    const allValid = phones.every((p) => {
        const digits = p.replace(/\D/g, '')
        return digits.length >= 7 && digits.length <= 15
    })
    return allValid ? phones.join(', ') : null
}
</script>
