<template>
    <div class="inline-flex items-center gap-1.5">
        <button
            ref="triggerRef"
            type="button"
            :title="isBrand ? `${displayValue} (branding)` : (displayValue || 'unset')"
            class="relative block size-7 rounded-md border border-[var(--color-card-body-border)]"
            :class="[isOpen ? 'ring-2 ring-blue-500' : '', isInherited ? 'border-dashed' : '']"
            :style="swatchStyle"
            @click.stop="toggleOpen"
        >
            <span
                v-if="isBrand"
                class="absolute -top-1 -right-1 flex size-3 items-center justify-center rounded-full bg-white text-[7px] leading-none font-bold text-zinc-900 ring-1 ring-zinc-300 dark:bg-zinc-900 dark:text-white dark:ring-zinc-600"
            >B</span>
        </button>

        <button
            v-if="resettable && modelValue != null"
            type="button"
            class="text-xs text-[var(--color-text-secondary)] underline transition-colors hover:text-[var(--color-text-body)]"
            @click.stop="$emit('update:modelValue', null)"
        >
            {{ resetLabel }}
        </button>

        <Teleport to="body">
            <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>

            <div
                v-if="isOpen"
                :style="`top: ${pickerTop}px; left: ${pickerLeft}px`"
                class="fixed z-50 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                @click.stop
            >
                <!-- Shade column headers -->
                <div class="flex items-center gap-px border-b border-zinc-100 px-2 py-1.5 dark:border-zinc-800">
                    <div class="w-[52px]"></div>
                    <div
                        v-for="shade in colorShades"
                        :key="shade"
                        class="flex size-3.5 items-center justify-center text-[8px] text-zinc-400 dark:text-zinc-600"
                    >
                        {{ shade === '50' ? '50' : shade.charAt(0) }}
                    </div>
                </div>

                <!-- Color matrix -->
                <div class="max-h-72 overflow-y-auto px-2 py-1.5">
                    <template v-for="(group, gi) in colorFamilyGroups" :key="gi">
                        <div v-if="gi > 0" class="my-1 border-t border-zinc-100 dark:border-zinc-800"></div>
                        <div v-for="family in group" :key="family" class="flex items-center gap-px py-px">
                            <div
                                class="w-[52px] pr-1.5 text-right font-mono text-[10px] text-zinc-400 dark:text-zinc-500"
                            >
                                {{ family }}
                            </div>
                            <button
                                v-for="shade in colorShades"
                                :key="shade"
                                :class="
                                    isCurrentColor(`${family}-${shade}`)
                                        ? 'relative z-10 scale-125 ring-2 ring-blue-500 ring-offset-1'
                                        : 'hover:relative hover:z-10 hover:scale-125'
                                "
                                class="size-3.5 rounded-sm transition-transform"
                                :style="`background-color: var(--color-${family}-${shade});`"
                                :title="`${family}-${shade}`"
                                @click.stop="selectColor(`${family}-${shade}`)"
                            ></button>
                        </div>
                    </template>

                    <!-- Branding family -->
                    <div class="mt-1 flex items-center gap-px border-t border-zinc-100 py-1 dark:border-zinc-800">
                        <div class="w-[52px] pr-1.5 text-right font-mono text-[10px] text-zinc-400 dark:text-zinc-500">
                            brand
                        </div>
                        <button
                            v-for="shade in colorShades"
                            :key="shade"
                            :class="
                                isCurrentColor(`brand-${shade}`)
                                    ? 'relative z-10 scale-125 ring-2 ring-blue-500 ring-offset-1'
                                    : 'hover:relative hover:z-10 hover:scale-125'
                            "
                            class="size-3.5 rounded-sm transition-transform"
                            :style="`background-color: var(--color-brand-${shade});`"
                            :title="`brand-${shade}`"
                            @click.stop="selectColor(`brand-${shade}`)"
                        ></button>
                    </div>

                    <!-- Special values -->
                    <div class="mt-1 flex items-center gap-2 border-t border-zinc-100 py-1.5 dark:border-zinc-800">
                        <div class="w-[52px] pr-1.5 text-right font-mono text-[10px] text-zinc-400 dark:text-zinc-500">
                            special
                        </div>
                        <button
                            :class="isCurrentColor('white') ? 'ring-2 ring-blue-500' : 'hover:scale-125'"
                            class="size-3.5 rounded-sm border border-zinc-300 transition-transform dark:border-zinc-600"
                            style="background-color: white"
                            title="white"
                            @click.stop="selectColor('white')"
                        ></button>
                        <button
                            :class="isCurrentColor('black') ? 'ring-2 ring-blue-500' : 'hover:scale-125'"
                            class="size-3.5 rounded-sm border border-zinc-300 transition-transform dark:border-zinc-600"
                            style="background-color: black"
                            title="black"
                            @click.stop="selectColor('black')"
                        ></button>
                        <button
                            :class="isCurrentColor('transparent') ? 'ring-2 ring-blue-500' : 'hover:scale-125'"
                            class="size-3.5 rounded-sm border border-zinc-300 transition-transform dark:border-zinc-600"
                            style="
                                background-image:
                                    linear-gradient(45deg, #d1d5db 25%, transparent 25%, transparent 75%, #d1d5db 75%),
                                    linear-gradient(45deg, #d1d5db 25%, transparent 25%, transparent 75%, #d1d5db 75%);
                                background-size: 6px 6px;
                                background-position:
                                    0 0,
                                    3px 3px;
                            "
                            title="transparent"
                            @click.stop="selectColor('transparent')"
                        ></button>
                    </div>
                </div>

                <!-- Opacity slider -->
                <div class="flex items-center gap-2 border-t border-zinc-200 px-3 py-2 dark:border-zinc-700">
                    <div
                        class="w-[52px] shrink-0 pr-1.5 text-right font-mono text-[10px] text-zinc-400 dark:text-zinc-500"
                    >
                        opacity
                    </div>
                    <input
                        type="range"
                        min="10"
                        max="100"
                        step="10"
                        :value="currentOpacity"
                        class="h-1.5 flex-1 cursor-pointer accent-blue-500"
                        @click.stop
                        @input.stop="setOpacity($event.target.value)"
                    />
                    <span class="w-8 text-right font-mono text-[10px] text-zinc-500 dark:text-zinc-400">
                        {{ currentOpacity === 100 ? '—' : currentOpacity + '%' }}
                    </span>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue'
import { colorFamilyGroups, colorShades, SPECIALS, parseColor, cssVal } from '@/utils/colorToken.js'

const props = defineProps({
    /** A token string like 'indigo-800' or 'white/50', or null/'' to mean "unset / inherit default". */
    modelValue: { type: String, default: null },
    /** Display-only fallback shown when modelValue is null, so the swatch never looks blank. */
    fallback:   { type: String, default: null },
    /** Show a "reset to default" link when modelValue is currently set. */
    resettable: { type: Boolean, default: false },
    resetLabel: { type: String, default: 'Reset' },
})

const emit = defineEmits(['update:modelValue'])

const triggerRef = ref(null)
const isOpen     = ref(false)
const pickerTop  = ref(0)
const pickerLeft = ref(0)

const isInherited = computed(() => props.modelValue == null && props.fallback != null)
const displayValue = computed(() => props.modelValue ?? props.fallback ?? null)
const isBrand = computed(() => (displayValue.value ?? '').split('/')[0].startsWith('brand-'))

const swatchStyle = computed(() => {
    const val = displayValue.value
    if (!val || val === 'transparent') {
        return 'background-image: linear-gradient(45deg, #d1d5db 25%, transparent 25%, transparent 75%, #d1d5db 75%), linear-gradient(45deg, #d1d5db 25%, transparent 25%, transparent 75%, #d1d5db 75%); background-size: 8px 8px; background-position: 0 0, 4px 4px;'
    }
    return `background-color: ${cssVal(val)}`
})

const currentOpacity = computed(() => {
    const val = displayValue.value ?? ''
    const parts = val.split('/')
    return parts[1] !== undefined ? parseInt(parts[1]) : 100
})

function toggleOpen() {
    if (isOpen.value) {
        isOpen.value = false
        return
    }
    isOpen.value = true
    nextTick(() => {
        const rect = triggerRef.value.getBoundingClientRect()
        const pw = 252, ph = 370
        let left = rect.left
        if (left + pw > window.innerWidth - 8) left = window.innerWidth - pw - 8
        let top = rect.bottom + 4
        if (top + ph > window.innerHeight - 8) top = rect.top - ph - 4
        pickerLeft.value = Math.max(8, left)
        pickerTop.value = Math.max(8, top)
    })
}

function selectColor(val) {
    const currentOp = (displayValue.value || '').split('/')[1]
    const newVal = currentOp && parseInt(currentOp) < 100 ? `${val}/${currentOp}` : val
    emit('update:modelValue', newVal)
}

function isCurrentColor(val) {
    const current = displayValue.value ?? ''
    return current.split('/')[0] === val
}

function setOpacity(opacity) {
    const base = (displayValue.value || 'white').split('/')[0]
    const pct = Math.max(0, Math.min(100, parseInt(opacity) || 0))
    const newVal = pct < 100 ? `${base}/${pct}` : base
    emit('update:modelValue', newVal)
}
</script>
