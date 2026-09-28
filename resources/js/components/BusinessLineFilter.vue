<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { CheckboxInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'
import { useAuth } from '@/composables/useAuth'

/**
 * The business line filter menu, with the once-per-tab default to the
 * signed-in user's business line. Emits `change` with the `business_lines`
 * query value: an array, `['__empty__']` for none, or undefined for all.
 * `default` is emitted once, for the default, so the page can replace
 * the history entry.
 */
const __ = useI18n()
const { user: currentUser } = useAuth()

const props = defineProps({
    businessLines: { type: Array, default: () => [] }, // { id, abbreviation }
    selected: { type: Array, default: () => [] }, // ids and 'none'
    // The sessionStorage key that marks the default as applied in this tab;
    // null turns the default off.
    defaultSessionKey: { type: String, default: null },
    // The trigger edge the menu lines up with; 'right' opens it toward the left.
    align: { type: String, default: 'left' },
})

const emit = defineEmits(['change', 'default'])

const options = computed(() => [
    ...props.businessLines.map((line) => ({ value: line.id, label: line.abbreviation })),
    { value: 'none', label: __('business_line_filter.no_line') },
])

function defaultApplied() {
    return window.sessionStorage.getItem(props.defaultSessionKey) === '1'
}

function markDefaultApplied() {
    window.sessionStorage.setItem(props.defaultSessionKey, '1')
}

function hasExplicitQuery() {
    const params = new URLSearchParams(window.location.search)
    return [...params.keys()].some((key) => key === 'business_lines' || key.startsWith('business_lines['))
}

onMounted(() => {
    const businessLineId = currentUser.value?.business_line_id
    if (!props.defaultSessionKey || !businessLineId || defaultApplied()) return

    if (hasExplicitQuery()) {
        markDefaultApplied()
        return
    }

    if (!props.businessLines.some((line) => line.id === businessLineId)) return

    markDefaultApplied()
    emit('default', [businessLineId])
})

const open = ref(false)
const triggerEl = ref(null)
const menuRef = ref(null)

function toggle(event) {
    open.value = !open.value
    triggerEl.value = event.currentTarget
}

function onClickOutside(e) {
    if (!open.value) return
    if (triggerEl.value?.contains(e.target)) return
    if (menuRef.value?.contains(e.target)) return
    open.value = false
}

document.addEventListener('mousedown', onClickOutside)
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))

function toggleLine(value, checked) {
    const next = new Set(props.selected)
    if (checked) next.add(value)
    else next.delete(value)

    const allValues = options.value.map((option) => option.value)
    const isAllSelected = allValues.every((v) => next.has(v))
    emit('change', isAllSelected ? undefined : allValues.filter((v) => next.has(v)))
}

function selectAll() {
    emit('change', options.value.map((option) => option.value))
}

function selectNone() {
    emit('change', ['__empty__'])
}
</script>

<template>
    <div class="relative">
        <ButtonSecondary
            type="button"
            data-testid="business-lines-menu-trigger"
            :aria-expanded="open"
            @click="toggle"
        >
            {{ __('business_line_filter.label') }}
        </ButtonSecondary>

        <div
            v-if="open"
            ref="menuRef"
            data-testid="business-lines-menu"
            role="group"
            :aria-label="__('business_line_filter.aria_group')"
            :class="align === 'right' ? 'right-0' : 'left-0'"
            class="absolute z-50 mt-1 w-max min-w-48 rounded-md border border-(--color-dropdown-panel-border) bg-(--color-dropdown-panel-bg) p-2 shadow-lg"
        >
            <div class="flex flex-col gap-1">
                <CheckboxInput
                    v-for="option in options"
                    :key="option.value"
                    :model-value="selected.includes(option.value)"
                    class="py-1"
                    @update:model-value="(checked) => toggleLine(option.value, checked)"
                >
                    {{ option.label }}
                </CheckboxInput>
            </div>
            <div role="separator" class="my-2 border-t border-(--color-dropdown-panel-border)"></div>
            <div class="flex items-center gap-1">
                <ButtonSecondary
                    type="button"
                    class="w-full min-w-0 px-2 py-1 text-sm"
                    @click="selectAll"
                >
                    {{ __('business_line_filter.select_all') }}
                </ButtonSecondary>
                <ButtonSecondary
                    type="button"
                    class="w-full min-w-0 px-2 py-1 text-sm"
                    @click="selectNone"
                >
                    {{ __('business_line_filter.select_none') }}
                </ButtonSecondary>
            </div>
        </div>
    </div>
</template>
