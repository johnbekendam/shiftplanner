<template>
    <Shell v-bind="$attrs">
        <input
            :type="visible ? 'text' : 'password'"
            :value="model"
            :placeholder="$attrs.placeholder"
            :autocomplete="$attrs.autocomplete"
            :disabled="$attrs.disabled"
            class="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm text-[var(--color-input-text)] outline-none placeholder:text-[var(--color-input-placeholder)] disabled:cursor-not-allowed disabled:text-[var(--color-input-disabled-text)]"
            @input="model = $event.target.value"
        />
        <button
            type="button"
            tabindex="-1"
            class="pr-3 text-[var(--color-input-placeholder)] transition hover:text-[var(--color-input-text)]"
            @click="visible = !visible"
        >
            <Icon :name="visible ? 'eye-slash' : 'eye'" class="size-4" />
        </button>
    </Shell>
</template>

<script setup>
import { ref } from 'vue'
import Shell from './Shell.vue'
import Icon from '@/components/ui/Icon.vue'

const model = defineModel({ default: '' })
defineOptions({ inheritAttrs: false })

const visible = ref(false)
</script>
