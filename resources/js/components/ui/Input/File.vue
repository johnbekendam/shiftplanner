<template>
    <span v-bind="$attrs">
        <slot :trigger="trigger" />
        <input
            ref="inputEl"
            type="file"
            class="sr-only"
            :accept="accept"
            :capture="capture || undefined"
            :multiple="multiple"
            @change="onChange"
            @click.stop
        >
    </span>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
    accept:   { type: String,  default: 'image/*' },
    multiple: { type: Boolean, default: false },
    capture:  { type: String,  default: null },
})

const emit = defineEmits(['change'])
defineOptions({ inheritAttrs: false })

const inputEl = ref(null)

function trigger() {
    inputEl.value?.click()
}

function onChange(event) {
    const files = Array.from(event.target.files || [])
    emit('change', props.multiple ? files : (files[0] ?? null))
    event.target.value = ''
}

defineExpose({ trigger })
</script>
