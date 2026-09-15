<template>
    <Card class="min-w-xs">
        <!-- Header: reset + title + prev/next -->
        <template #header>
            <div class="flex h-12 items-center justify-between px-2 py-2">
                <ButtonSecondary type="button" icon="arrow-path" :aria-label="__('calendar.reset')" @click="onReset" />
                <span class="text-md font-semibold">{{ title }}</span>
                <div class="flex items-center gap-1">
                    <ButtonSecondary type="button" icon="chevron-left" :aria-label="__('calendar.prev_month')" @click="onPrev" />
                    <ButtonSecondary type="button" icon="chevron-right" :aria-label="__('calendar.next_month')" @click="onNext" />
                </div>
            </div>
        </template>

        <!-- Body -->
        <div class="p-4">
            <!-- Weekday headers -->
            <div class="mb-4 grid grid-cols-7 justify-items-center gap-x-0 gap-y-1 border-b border-(--color-card-border)">
                <button
                    v-for="(letter, i) in weekdayLetters"
                    :key="'wd-' + i"
                    :class="
                        dayClass(
                            weekDayStates[i] ?? null,
                            selectedDay === null && selectedDayOfWeek === i,
                            false,
                            false,
                        )
                    "
                    :disabled="!enableWeekDaySelection"
                    @click="enableWeekDaySelection && selectWeekday(i)"
                >
                    {{ letter }}
                </button>
            </div>

            <!-- Day grid -->
            <div class="grid grid-cols-7 justify-items-center gap-x-0 gap-y-1">
                <!-- Offset padding for first weekday -->
                <div v-for="n in firstDayOffset" :key="'pad-' + n"></div>

                <button
                    v-for="day in daysInMonth"
                    :key="'day-' + day"
                    :class="dayClass(colorForDay(day), selectedDay === day, isToday(day), isDisabled(day))"
                    :disabled="isDisabled(day)"
                    @click="!isDisabled(day) && enableDaySelection && selectDay(day)"
                >
                    {{ day }}
                </button>
            </div>
        </div>

        <!-- Footer: legenda + optional actions slot -->
        <template v-if="legendaEntries.length || $slots.footer" #footer>
            <div v-if="legendaEntries.length" class="flex flex-wrap items-center gap-4 px-3 py-2">
                <div v-for="entry in legendaEntries" :key="entry.color" class="flex items-center gap-2">
                    <div
                        :class="[
                            'm-1 h-6 w-6 shrink-0 rounded-md border-2 border-transparent text-center text-sm font-semibold',
                            colorBg(entry.color),
                        ]"
                    >
                        x
                    </div>
                    <span class="text-xs text-(--color-text-muted)">{{ entry.text }}</span>
                </div>
            </div>
            <slot name="footer" />
        </template>
    </Card>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from '@/components/ui/Card.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'

const page = usePage()
const __ = useI18n()

// ── Color class maps (full strings so Tailwind includes them) ─────────────────
// Each family aliases the existing badge tokens (ThemeTokens::COLOR_DEFAULTS).
// There is no badge hover-state token in this app's theme system, so unlike
// the source component, hover feedback here comes only from the border.
const COLOR_CLASS = {
    success: 'bg-(--color-badge-success-bg) text-(--color-badge-success-text)',
    custom: 'bg-(--color-badge-custom-bg) text-(--color-badge-custom-text)',
    error: 'bg-(--color-badge-error-bg) text-(--color-badge-error-text)',
    warning: 'bg-(--color-badge-warning-bg) text-(--color-badge-warning-text)',
    standard: 'bg-(--color-badge-standard-bg) text-(--color-badge-standard-text)',
    muted: 'bg-(--color-badge-muted-bg) text-(--color-badge-muted-text)',
}

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    dayStates: { type: Object, default: () => ({}) },
    weekDayStates: { type: Object, default: () => ({}) },
    legenda: { type: Object, default: () => ({}) },
    enableWeekDaySelection: { type: Boolean, default: true },
    enableDaySelection: { type: Boolean, default: true },
    dateRangeStart: { type: String, default: null },
    dateRangeEnd: { type: String, default: null },
    initialDay: { type: Number, default: null },
})

const emit = defineEmits(['change'])

// ── Today ─────────────────────────────────────────────────────────────────────

const todayDate = new Date()
const todayY = todayDate.getFullYear()
const todayM = todayDate.getMonth() + 1
const todayD = todayDate.getDate()
const todayDow = (todayDate.getDay() + 6) % 7 // Mon=0 … Sun=6

// ── Internal state (year/month are controlled via props) ──────────────────────

const onToday = props.year === todayY && props.month === todayM
const selectedDay = ref(props.initialDay ?? (onToday ? todayD : 1))
const selectedDayOfWeek = ref(onToday ? todayDow : isoWeekday(1))

// ── Computed ──────────────────────────────────────────────────────────────────

const title = computed(() => {
    const lang = document.documentElement.lang || 'en'
    const d = new Date(props.year, props.month - 1, 1)
    const monthFormat = page.props.auth?.settings?.month_format ?? 'my'
    const monthName = new Intl.DateTimeFormat(lang, { month: 'long' }).format(d)
    const capitalized = monthName.replace(/\b\w/, (c) => c.toUpperCase())
    return monthFormat === 'ym' ? `${props.year} ${capitalized}` : `${capitalized} ${props.year}`
})

const weekdayLetters = computed(() => {
    const lang = document.documentElement.lang || 'en'
    return Array.from({ length: 7 }, (_, i) => {
        // Jan 1 2024 = Monday, so i=0 → Monday, i=6 → Sunday
        const d = new Date(2024, 0, 1 + i)
        return d.toLocaleString(lang, { weekday: 'narrow' }).toUpperCase()
    })
})

const daysInMonth = computed(() => new Date(props.year, props.month, 0).getDate())

// ISO day: Mon=0 … Sun=6
const firstDayOffset = computed(() => {
    const dow = new Date(props.year, props.month - 1, 1).getDay()
    return (dow + 6) % 7 // JS getDay: Sun=0, Mon=1 → convert to Mon=0
})

const legendaEntries = computed(() =>
    Object.entries(props.legenda)
        .filter(([, text]) => text)
        .map(([color, text]) => ({ color, text })),
)

// ── Helpers ───────────────────────────────────────────────────────────────────

function isToday(day) {
    return props.year === todayY && props.month === todayM && day === todayD
}

// Returns ISO weekday (Mon=0 … Sun=6) for a day in the given year/month (defaults to current props)
function isoWeekday(day, y = props.year, m = props.month) {
    const dow = new Date(y, m - 1, day).getDay()
    return (dow + 6) % 7
}

function colorForDay(day) {
    const dow = isoWeekday(day)
    let color = props.weekDayStates[dow] ?? null
    if (props.dayStates[day] !== undefined) color = props.dayStates[day]
    return color
}

function isDisabled(day) {
    const pad = (n) => String(n).padStart(2, '0')
    const dayStr = `${props.year}-${pad(props.month)}-${pad(day)}`
    if (props.dateRangeStart && dayStr < props.dateRangeStart) return true
    if (props.dateRangeEnd && dayStr > props.dateRangeEnd) return true
    return false
}

function dayClass(color, selected, today, disabled) {
    const base = 'border-2 text-center rounded-md text-sm font-semibold m-1 h-8 w-8 cursor-pointer'
    const border = selected
        ? 'border-(--color-tab-active-border)'
        : 'border-transparent hover:border-(--color-tab-hover-border)'

    if (disabled) {
        return `${base} ${border} bg-transparent text-(--color-text-muted) cursor-not-allowed`
    }

    const colorCls = COLOR_CLASS[color] ?? ''
    const todayCls = today ? 'text-(--color-badge-error-text)' : ''
    return `${base} ${border} ${colorCls} ${todayCls}`
}

function colorBg(color) {
    return COLOR_CLASS[color] ?? ''
}

// ── Actions ───────────────────────────────────────────────────────────────────

function emitChange(year = props.year, month = props.month) {
    emit('change', {
        year,
        month,
        day: selectedDay.value,
        dayOfWeek: selectedDayOfWeek.value,
    })
}

function onReset() {
    selectedDay.value = todayD
    selectedDayOfWeek.value = todayDow
    emitChange(todayY, todayM)
}

function onPrev() {
    const d = new Date(props.year, props.month - 2, 1)
    const newYear = d.getFullYear()
    const newMonth = d.getMonth() + 1
    selectedDay.value = 1
    selectedDayOfWeek.value = isoWeekday(1, newYear, newMonth)
    emitChange(newYear, newMonth)
}

function onNext() {
    const d = new Date(props.year, props.month, 1)
    const newYear = d.getFullYear()
    const newMonth = d.getMonth() + 1
    selectedDay.value = 1
    selectedDayOfWeek.value = isoWeekday(1, newYear, newMonth)
    emitChange(newYear, newMonth)
}

function selectWeekday(i) {
    selectedDay.value = null
    selectedDayOfWeek.value = i
    emitChange()
}

function selectDay(day) {
    const dow = isoWeekday(day)
    selectedDay.value = day
    selectedDayOfWeek.value = dow
    emitChange()
}

onMounted(() => emitChange())
</script>
