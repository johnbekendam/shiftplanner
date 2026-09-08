<template>
    <Shell v-bind="shellAttrs">
        <FormattedInput
            :model-value="model != null ? String(model) : ''"
            :disabled="!!$attrs.disabled"
            :placeholder="$attrs.placeholder ?? ''"
            :live="live"
            class="min-w-0 flex-1 px-3 py-2 text-sm"
            @update:modelValue="model = $event"
        />
    </Shell>
</template>

<script setup>
import { useAttrs, computed } from 'vue'
import Shell from './Shell.vue'
import FormattedInput from './FormattedInput.vue'

const model = defineModel({ default: '' })
defineProps({
    live: { type: Boolean, default: false },
})
defineOptions({ inheritAttrs: false })

const attrs = useAttrs()
const shellAttrs = computed(() => {
    const { placeholder, ...rest } = attrs
    return rest
})
</script>
