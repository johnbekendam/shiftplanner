<template>
    <Shell v-bind="$attrs" class="relative">
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <Icon name="search" class="size-4 text-[var(--color-input-text)] opacity-50" />
        </div>
        <input
            v-bind="$attrs"
            :value="model"
            @input="model = $event.target.value"
            type="search"
            class="block w-full bg-transparent py-2 pr-3 pl-9 text-sm text-[var(--color-input-text)] outline-none placeholder:text-[var(--color-input-placeholder)]"
        />
    </Shell>
</template>

<script setup>
import Shell from './Shell.vue'
import Icon from '@/components/ui/Icon.vue'

const model = defineModel()
defineOptions({ inheritAttrs: false })
</script>
