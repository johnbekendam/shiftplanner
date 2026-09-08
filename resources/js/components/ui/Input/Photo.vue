<template>
    <div class="inline-flex flex-col items-center gap-1">
        <FileInput accept="image/jpeg,image/png,image/webp" @change="onPick">
            <template #default="{ trigger }">
                <button
                    type="button"
                    class="group relative block overflow-hidden rounded-full bg-[var(--color-input-bg)] outline outline-1 -outline-offset-1 outline-[var(--color-input-border)] transition duration-100 hover:outline-[var(--color-input-focus-border)] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[var(--color-input-focus-border)] disabled:cursor-not-allowed disabled:opacity-60"
                    :class="size"
                    :disabled="disabled"
                    :aria-label="label"
                    @click="trigger"
                >
                    <img v-if="displayUrl" :src="displayUrl" class="size-full object-cover" alt="">
                    <span v-else class="flex size-full items-center justify-center text-[var(--color-text-muted)]">
                        <Icon name="user" :class="iconSize" />
                    </span>

                    <span
                        v-if="!disabled"
                        class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        <Icon name="pencil-square" class="size-4 text-white" />
                    </span>
                </button>
            </template>
        </FileInput>

        <button
            v-if="displayUrl && !disabled && allowRemove"
            type="button"
            class="text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-body)]"
            @click="$emit('remove')"
        >
            {{ removeLabel }}
        </button>
    </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import FileInput from './File.vue'
import Icon from '@/components/ui/Icon.vue'

const props = defineProps({
    modelValue:  { type: String,  default: null },
    disabled:    { type: Boolean, default: false },
    allowRemove: { type: Boolean, default: true },
    size:        { type: String,  default: 'size-20' },
    iconSize:    { type: String,  default: 'size-8' },
    label:       { type: String,  default: '' },
    removeLabel: { type: String,  default: '' },
})

const emit = defineEmits(['select', 'remove'])

const localPreview = ref(null)

const displayUrl = computed(() => localPreview.value ?? props.modelValue)

function onPick(file) {
    if (!file) return

    if (localPreview.value) URL.revokeObjectURL(localPreview.value)
    localPreview.value = URL.createObjectURL(file)

    emit('select', file)
}

watch(() => props.modelValue, () => {
    if (localPreview.value) {
        URL.revokeObjectURL(localPreview.value)
        localPreview.value = null
    }
})

onBeforeUnmount(() => {
    if (localPreview.value) URL.revokeObjectURL(localPreview.value)
})
</script>
