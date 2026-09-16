<template>
    <Shell v-bind="$attrs">
        <FormattedInput
            inputmode="email"
            autocomplete="email"
            :model-value="model"
            :format="format"
            :disabled="!!$attrs.disabled"
            :live="live"
            class="min-w-0 flex-1 px-3 py-2 text-sm"
            @update:modelValue="model = $event"
        />
    </Shell>
</template>

<script setup>
import Shell from './Shell.vue'
import FormattedInput from './FormattedInput.vue'

const model = defineModel({ type: String, default: '' })
defineProps({
    // Emit update:modelValue on every keystroke instead of only on commit
    // (Enter/Tab/blur) — see FormattedInput.
    live: { type: Boolean, default: false },
})
defineOptions({ inheritAttrs: false })

const ALLOWED = /[a-zA-Z0-9!#$%&'*+/=?^_`{|}~.\-@]/

function format(chars) {
    const result = []
    for (const c of chars) {
        if (!ALLOWED.test(c)) continue
        if (c === '@' && result.includes('@')) continue
        result.push(c)
    }
    return result.join('')
}

</script>
