<template>
    <Shell v-bind="$attrs" @click="inputEl?.focus()">
        <FormattedInput
            ref="inputEl"
            :model-value="displayValue"
            :format="formatFn"
            :validate="validateFn"
            :placeholder="placeholder"
            :disabled="disabled"
            class="w-20 px-2 py-2 text-center text-sm"
            @update:modelValue="onCommit"
        />

        <button
            v-if="!is24h"
            type="button"
            :disabled="disabled"
            tabindex="-1"
            @click.stop="toggleAmPm"
            class="mr-2 rounded bg-[var(--color-input-border)] px-1.5 py-0.5 text-xs font-medium transition duration-100 select-none hover:bg-[var(--color-input-focus-border)] hover:text-white disabled:pointer-events-none disabled:opacity-50"
        >
            {{ ampm }}
        </button>
    </Shell>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Shell from './Shell.vue'
import FormattedInput from './FormattedInput.vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    format: { type: String, default: null }, // '24h' | '12h' — overrides user setting
    placeholder: { type: String, default: '--:--' },
})

const emit = defineEmits(['update:modelValue'])
defineOptions({ inheritAttrs: false })

// ── Format ────────────────────────────────────────────────────────────────────

const page = usePage()
const is24h = computed(() => (props.format ?? page.props.auth?.settings?.time_format ?? '24h') === '24h')

// ── State ─────────────────────────────────────────────────────────────────────

const inputEl = ref(null)
const localH = ref(0)
const localM = ref(0)
const isEmpty = ref(!props.modelValue)

initFromProp(props.modelValue)

const ampm = computed(() => (localH.value < 12 ? 'AM' : 'PM'))

watch(() => props.modelValue, initFromProp)

// ── Display ───────────────────────────────────────────────────────────────────

const displayValue = computed(() => {
    if (isEmpty.value) return ''
    const h = is24h.value ? localH.value : localH.value % 12 || 12
    return `${String(h).padStart(2, '0')}:${String(localM.value).padStart(2, '0')}`
})

// ── Format functions ──────────────────────────────────────────────────────────

function format24h(chars) {
    const digits = chars.filter((c) => /\d/.test(c))
    const h0 = digits.length > 0 ? parseInt(digits[0]) : -1
    const hLen = h0 >= 3 ? 1 : 2
    const result = []

    for (let i = 0; i < digits.length; i++) {
        if (i === 0) {
            result.push(digits[i])
            continue
        }
        if (i < hLen) {
            if (parseInt(result[0]) === 2 && parseInt(digits[i]) > 3) break
            result.push(digits[i])
            continue
        }
        const mPos = i - hLen
        if (mPos === 0 && parseInt(digits[i]) > 5) break
        if (mPos >= 2) break
        result.push(digits[i])
    }

    if (result.length === 0) return ''
    if (result.length <= hLen) return result.join('')
    return `${result.slice(0, hLen).join('')}:${result.slice(hLen).join('')}`
}

function format12h(chars) {
    const digits = chars.filter((c) => /\d/.test(c))
    // First digit 2–9 → 1-digit hour; 0–1 → 2-digit hour (max 12)
    const h0 = digits.length > 0 ? parseInt(digits[0]) : -1
    const hLen = h0 >= 2 ? 1 : 2
    const result = []

    for (let i = 0; i < digits.length; i++) {
        if (i === 0) {
            result.push(digits[i])
            continue
        }
        if (i < hLen) {
            if (parseInt(result[0]) === 1 && parseInt(digits[i]) > 2) break // 13–19 invalid
            result.push(digits[i])
            continue
        }
        const mPos = i - hLen
        if (mPos === 0 && parseInt(digits[i]) > 5) break
        if (mPos >= 2) break
        result.push(digits[i])
    }

    if (result.length === 0) return ''
    if (result.length <= hLen) return result.join('')
    return `${result.slice(0, hLen).join('')}:${result.slice(hLen).join('')}`
}

const formatFn = computed(() => (is24h.value ? format24h : format12h))

// ── Validate functions ────────────────────────────────────────────────────────

function validate24h(str) {
    const m = str.match(/^(\d{1,2}):(\d{2})$/)
    if (!m) return null
    const h = parseInt(m[1]),
        min = parseInt(m[2])
    if (h > 23 || min > 59) return null
    return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`
}

function validate12h(str) {
    const m = str.match(/^(\d{1,2}):(\d{2})$/)
    if (!m) return null
    const h = parseInt(m[1]),
        min = parseInt(m[2])
    if (h < 1 || h > 12 || min > 59) return null
    return `${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`
}

const validateFn = computed(() => (is24h.value ? validate24h : validate12h))

// ── Commit ────────────────────────────────────────────────────────────────────

function onCommit(display) {
    if (!display) {
        isEmpty.value = true
        emit('update:modelValue', '')
        return
    }

    const [hStr, mStr] = display.split(':')
    const h = parseInt(hStr),
        m = parseInt(mStr)

    if (is24h.value) {
        localH.value = h
    } else {
        localH.value = (h % 12) + (ampm.value === 'PM' ? 12 : 0)
    }
    localM.value = m
    isEmpty.value = false
    emit('update:modelValue', toHH24())
}

// ── AM/PM toggle ──────────────────────────────────────────────────────────────

function toggleAmPm() {
    if (props.disabled || isEmpty.value) return
    localH.value = (localH.value + 12) % 24
    emit('update:modelValue', toHH24())
}

// ── Utilities ─────────────────────────────────────────────────────────────────

function toHH24() {
    return `${String(localH.value).padStart(2, '0')}:${String(localM.value).padStart(2, '0')}`
}

function initFromProp(val) {
    isEmpty.value = !val
    if (!val) {
        localH.value = 0
        localM.value = 0
        return
    }
    const [h, m] = val.split(':').map(Number)
    localH.value = isNaN(h) ? 0 : Math.min(23, Math.max(0, h))
    localM.value = isNaN(m) ? 0 : Math.min(59, Math.max(0, m))
}
</script>
