<template>
    <input
        ref="inputRef"
        v-bind="$attrs"
        :value="draft"
        :placeholder="placeholder"
        :disabled="disabled"
        :data-invalid="invalid || undefined"
        :class="[
            'bg-transparent transition duration-100 outline-none',
            invalid ? 'text-[var(--color-input-invalid-text)]' : 'text-[var(--color-input-text)]',
            'disabled:cursor-not-allowed',
            'disabled:text-[var(--color-input-disabled-text)]',
            'placeholder:text-[var(--color-input-placeholder)]',
        ]"
        @input="onInput"
        @keydown="onKeydown"
        @blur="onBlur"
    />
</template>

<script setup>
import { ref, watch, nextTick, onUnmounted } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    // Converts the accepted-char array to a display string with any delimiters.
    // Silently drops invalid chars by not pushing them to the result.
    // Optional — without it the input behaves as a plain text field.
    format: { type: Function, default: null },
    // Commit-time validator — called on Enter/Tab with the trimmed display string.
    // Return a normalized string to accept, or null to reject (triggers red flash).
    validate: { type: Function, default: null },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    // Emit update:modelValue on every keystroke instead of only on commit
    // (Enter/Tab/blur). For formatted fields this only emits once the
    // in-progress draft passes `validate` (silent otherwise — no flash).
    live: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
defineOptions({ inheritAttrs: false })

// ── Helpers ───────────────────────────────────────────────────────────────────

// Given a string, find which characters the format function treats as user input
// by feeding them one-by-one and keeping those that advance the display.
// This is format-agnostic: digits for date/time, email chars for email, etc.
function extractChars(str) {
    if (!props.format) return (str ?? '').split('')
    const accepted = []
    let display = ''
    for (const c of (str ?? '').split('')) {
        const test = props.format([...accepted, c])
        if (test.length > display.length) {
            accepted.push(c)
            display = test
        }
    }
    return accepted
}

// Call format with the given chars, write the result to draft and DOM.
function applyFormat(chars) {
    const display = props.format(chars)
    draft.value = display
    nextTick(() => {
        if (!inputRef.value) return
        inputRef.value.value = display
        inputRef.value.setSelectionRange(display.length, display.length)
    })
}

// ── State ─────────────────────────────────────────────────────────────────────

function toDisplay(val) {
    const str = val ?? ''
    return props.format ? props.format(extractChars(str)) : str
}

const inputRef = ref(null)
const committed = ref(toDisplay(props.modelValue))
const draft = ref(toDisplay(props.modelValue))
const invalid = ref(false)
let invalidTimer = null

watch(
    () => props.modelValue,
    (val) => {
        const display = toDisplay(val)
        const userHasTyped = draft.value !== committed.value
        committed.value = display
        if (document.activeElement !== inputRef.value || !userHasTyped) {
            draft.value = display
            nextTick(() => {
                if (!inputRef.value) return
                inputRef.value.value = display
                if (document.activeElement === inputRef.value) inputRef.value.select()
            })
        }
    },
)

// ── Event handlers ────────────────────────────────────────────────────────────

function onInput(e) {
    if (props.format) {
        applyFormat(extractChars(e.target.value))
        if (props.live) emitLiveFormatted()
        return
    }
    draft.value = e.target.value
    if (props.live) {
        emit('update:modelValue', draft.value)
    }
}

// Live preview for formatted fields: emit as soon as the in-progress draft
// is itself a valid value, without touching `committed` (so blur/Enter still
// runs the normal commit/flash flow for anything left unfinished).
function emitLiveFormatted() {
    const trimmed = draft.value.trim()
    if (trimmed === '') return
    const normalized = props.validate ? props.validate(trimmed) : trimmed
    if (normalized !== null) {
        emit('update:modelValue', normalized)
    }
}

function onKeydown(e) {
    if (props.format) {
        // Backspace / Delete: operate on the char array so the cursor never gets
        // stuck on an auto-inserted delimiter.
        if (e.key === 'Backspace' || e.key === 'Delete') {
            e.preventDefault()
            const len = draft.value.length
            const pos = inputRef.value.selectionStart ?? len
            const end = inputRef.value.selectionEnd ?? len
            const all = extractChars(draft.value)
            let next
            if (pos !== end) {
                const a = extractChars(draft.value.slice(0, pos)).length
                const b = extractChars(draft.value.slice(0, end)).length
                next = all.filter((_, i) => i < a || i >= b)
            } else if (e.key === 'Backspace') {
                const a = extractChars(draft.value.slice(0, pos)).length
                next = a > 0 ? [...all.slice(0, a - 1), ...all.slice(a)] : all.slice()
            } else {
                const a = extractChars(draft.value.slice(0, pos)).length
                next = a < all.length ? [...all.slice(0, a), ...all.slice(a + 1)] : all.slice()
            }
            applyFormat(next)
            return
        }

        if (e.key.length === 1 && !e.metaKey && !e.ctrlKey) {
            // Speculative test: ask the format function if it accepts this character
            // in context. If the display doesn't grow, the char was silently dropped
            // → block the keystroke. Respects any active selection (replaced chars
            // are excluded from the "before" array).
            const len = draft.value.length
            const pos = inputRef.value.selectionStart ?? len
            const end = inputRef.value.selectionEnd ?? len
            const all = extractChars(draft.value)
            const before =
                pos !== end
                    ? (() => {
                          const a = extractChars(draft.value.slice(0, pos)).length
                          const b = extractChars(draft.value.slice(0, end)).length
                          return all.filter((_, i) => i < a || i >= b)
                      })()
                    : all
            const baseDisplay = props.format(before)
            const testDisplay = props.format([...before, e.key])
            if (testDisplay.length <= baseDisplay.length) {
                e.preventDefault()
                return
            }
        }
    }

    if (e.key === 'Enter') {
        e.preventDefault()
        if (commit()) focusNext()
    } else if (e.key === 'Tab') {
        commit()
    }
}

function onBlur() {
    if (draft.value === committed.value) return
    if (!commit(false)) draft.value = committed.value
}

// ── Commit ────────────────────────────────────────────────────────────────────

function commit(flash = true) {
    const trimmed = draft.value.trim()
    if (trimmed === '') {
        committed.value = ''
        draft.value = ''
        emit('update:modelValue', '')
        return true
    }
    const normalized = props.validate ? props.validate(trimmed) : trimmed
    if (normalized !== null) {
        committed.value = normalized
        draft.value = normalized
        emit('update:modelValue', normalized)
        return true
    }
    if (flash) flashInvalid()
    return false
}

function flashInvalid() {
    invalid.value = true
    clearTimeout(invalidTimer)
    invalidTimer = setTimeout(() => {
        invalid.value = false
    }, 600)
}

function focusNext() {
    const sel =
        'input:not([disabled]),button:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'
    const all = [...document.querySelectorAll(sel)]
    const idx = all.indexOf(inputRef.value)
    if (idx !== -1 && idx + 1 < all.length) nextTick(() => all[idx + 1].focus())
}

onUnmounted(() => clearTimeout(invalidTimer))

defineExpose({
    focus: () => inputRef.value?.focus(),
    clear() {
        committed.value = ''
        draft.value = ''
        nextTick(() => {
            if (inputRef.value) inputRef.value.value = ''
        })
    },
})
</script>

<style scoped>
/* Safari respects disabled text color only via this property */
input:disabled {
    -webkit-text-fill-color: var(--color-input-disabled-text);
}
</style>
