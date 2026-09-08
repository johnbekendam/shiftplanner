<template>
    <Shell v-bind="$attrs" @click="inputEl?.focus()">
        <FormattedInput
            ref="inputEl"
            :model-value="displayValue"
            :format="formatFn"
            :validate="validateFn"
            :placeholder="placeholder"
            :disabled="disabled"
            inputmode="numeric"
            class="min-w-0 flex-1 px-3 py-2 text-sm"
            @update:modelValue="onCommit"
        />
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
    format: { type: String, default: null }, // 'dmy' | 'ymd' — overrides user setting
})

const emit = defineEmits(['update:modelValue'])
defineOptions({ inheritAttrs: false })

// ── Format ────────────────────────────────────────────────────────────────────

const page = usePage()
const isDmy = computed(() => (props.format ?? page.props.auth?.settings?.date_format ?? 'dmy') === 'dmy')
const placeholder = computed(() => (isDmy.value ? 'DD-MM-YYYY' : 'YYYY-MM-DD'))

// ── State ─────────────────────────────────────────────────────────────────────

const inputEl = ref(null)
const localDay = ref(1)
const localMonth = ref(1)
const localYear = ref(2000)
const isEmpty = ref(!props.modelValue)

initFromIso(props.modelValue)

watch(() => props.modelValue, initFromIso)

// ── Display ───────────────────────────────────────────────────────────────────

const displayValue = computed(() => {
    if (isEmpty.value) return ''
    const dd = String(localDay.value).padStart(2, '0')
    const mm = String(localMonth.value).padStart(2, '0')
    const yyyy = String(localYear.value).padStart(4, '0')
    return isDmy.value ? `${dd}-${mm}-${yyyy}` : `${yyyy}-${mm}-${dd}`
})

// ── Format functions ──────────────────────────────────────────────────────────

// DD-MM-YYYY — auto-pads day and month when first digit implies a leading zero.
// Uses result.length as position so auto-inserted digits don't shift later checks.
function formatDmy(chars) {
    const result = []
    for (const char of chars) {
        if (!/\d/.test(char)) continue
        const digit = char
        const d = parseInt(digit)
        const pos = result.length
        if (pos === 0) {
            if (d > 3) result.push('0')
            result.push(digit)
        } else if (pos === 1) {
            if (parseInt(result[0]) === 3 && d > 1) break
            result.push(digit)
        } else if (pos === 2) {
            if (d > 1) result.push('0')
            result.push(digit)
        } else if (pos === 3) {
            if (parseInt(result[2]) === 1 && d > 2) break
            result.push(digit)
        } else if (pos < 8) {
            result.push(digit)
        } else {
            break
        }
    }
    const day = result.slice(0, 2).join('')
    const month = result.slice(2, 4).join('')
    const year = result.slice(4, 8).join('')
    if (result.length === 0) return ''
    if (result.length <= 2) return day
    if (result.length <= 4) return `${day}-${month}`
    return `${day}-${month}-${year}`
}

// YYYY-MM-DD — year is 4 free digits, then month and day with the same auto-pad rules.
function formatYmd(chars) {
    const result = []
    for (const char of chars) {
        if (!/\d/.test(char)) continue
        const digit = char
        const d = parseInt(digit)
        const pos = result.length
        if (pos < 4) {
            result.push(digit)
        } else if (pos === 4) {
            if (d > 1) result.push('0')
            result.push(digit)
        } else if (pos === 5) {
            if (parseInt(result[4]) === 1 && d > 2) break
            result.push(digit)
        } else if (pos === 6) {
            if (d > 3) result.push('0')
            result.push(digit)
        } else if (pos === 7) {
            if (parseInt(result[6]) === 3 && d > 1) break
            result.push(digit)
        } else {
            break
        }
    }
    const year = result.slice(0, 4).join('')
    const month = result.slice(4, 6).join('')
    const day = result.slice(6, 8).join('')
    if (result.length === 0) return ''
    if (result.length <= 4) return year
    if (result.length <= 6) return `${year}-${month}`
    return `${year}-${month}-${day}`
}

const formatFn = computed(() => (isDmy.value ? formatDmy : formatYmd))

// ── Validate functions ────────────────────────────────────────────────────────

function validateDmy(str) {
    const m = str.match(/^(\d{2})-(\d{2})-(\d{4})$/)
    if (!m) return null
    const day = parseInt(m[1]),
        month = parseInt(m[2])
    if (month < 1 || month > 12 || day < 1 || day > 31) return null
    return str
}

function validateYmd(str) {
    const m = str.match(/^(\d{4})-(\d{2})-(\d{2})$/)
    if (!m) return null
    const month = parseInt(m[2]),
        day = parseInt(m[3])
    if (month < 1 || month > 12 || day < 1 || day > 31) return null
    return str
}

const validateFn = computed(() => (isDmy.value ? validateDmy : validateYmd))

// ── Commit ────────────────────────────────────────────────────────────────────

function onCommit(display) {
    if (!display) {
        isEmpty.value = true
        localDay.value = 1
        localMonth.value = 1
        localYear.value = 2000
        emit('update:modelValue', '')
        return
    }

    const parts = display.split('-').map(Number)
    const [day, month, year] = isDmy.value ? parts : [parts[2], parts[1], parts[0]]

    localDay.value = Math.min(31, Math.max(1, day || 1))
    localMonth.value = Math.min(12, Math.max(1, month || 1))
    localYear.value = Math.min(2099, Math.max(1900, year || 2000))
    isEmpty.value = false
    emit('update:modelValue', toIso())
}

// ── Utilities ─────────────────────────────────────────────────────────────────

function toIso() {
    return `${String(localYear.value).padStart(4, '0')}-${String(localMonth.value).padStart(2, '0')}-${String(localDay.value).padStart(2, '0')}`
}

function initFromIso(val) {
    isEmpty.value = !val
    if (!val) {
        localDay.value = 1
        localMonth.value = 1
        localYear.value = 2000
        return
    }
    const [y, m, d] = val.split('-').map(Number)
    localYear.value = isNaN(y) ? 2000 : Math.min(2099, Math.max(1900, y))
    localMonth.value = isNaN(m) ? 1 : Math.min(12, Math.max(1, m))
    localDay.value = isNaN(d) ? 1 : Math.min(31, Math.max(1, d))
}
</script>
