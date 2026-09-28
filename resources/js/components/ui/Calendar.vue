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
            <div
                data-testid="calendar-weekday-header"
                :class="['mb-4 grid justify-items-center gap-x-0 gap-y-1 border-b border-(--color-card-border)', GRID_COLUMNS]"
            >
                <span data-testid="calendar-week-label" class="self-center text-xs text-(--color-text-muted)">
                    {{ __('calendar.week_abbr') }}
                </span>
                <button
                    v-for="(letter, i) in weekdayLetters"
                    :key="'wd-' + i"
                    :class="
                        dayClass(
                            weekDayStates[i] ?? null,
                            isWeekdayHighlighted(i),
                            false,
                            false,
                            enableWeekDaySelection,
                            null,
                            enableWeekDaySelection,
                        )
                    "
                    :disabled="!enableWeekDaySelection"
                    @click="enableWeekDaySelection && selectWeekday(i)"
                >
                    {{ letter }}
                </button>
            </div>

            <!-- Day grid: one row per week. The selected week gets one border around the
                 week number and its days; a published week colors its week number. -->
            <div class="flex flex-col gap-y-1">
                <div
                    v-for="(week, wi) in weeks"
                    :key="'week-' + wi"
                    :data-testid="'calendar-week-' + wi"
                    class="grid items-center justify-items-center gap-x-0 rounded-md border-2"
                    :class="[GRID_COLUMNS, weekRowClass(week)]"
                    @click="onWeekRowClick($event, week)"
                >
                    <span
                        :data-testid="'calendar-week-number-' + wi"
                        class="rounded px-1 text-xs"
                        :class="isWeekMarked(week) ? weekMarkerNumberClass : 'text-(--color-text-muted)'"
                    >
                        {{ weekNumber(week) }}
                    </span>
                    <template v-for="(cell, ci) in week" :key="ci">
                        <div v-if="cell.type === 'pad'"></div>
                        <button
                            v-else
                            :class="[
                                dayClass(colorForDay(cell.day), false, isToday(cell.day), isDisabled(cell.day), !highlightSelection && enableDaySelection, dayBorders[cell.day]),
                                cell.day === ringDay ? 'ring-2 ring-(--color-input-focus-border)' : '',
                            ]"
                            :disabled="isDisabled(cell.day)"
                            @click="onDayClick(cell.day)"
                        >
                            {{ cell.day }}
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Footer: legenda + optional actions slot -->
        <template v-if="hasLegend || $slots.footer" #footer>
            <!-- w-0 min-w-full: the legend wraps to the calendar's width instead of
                 widening it, so a w-fit calendar stays as wide as its day grid. -->
            <CalendarLegend
                v-if="hasLegend"
                :legenda="legenda"
                :border-legenda="borderLegenda"
                class="w-0 min-w-full px-3 py-2"
            />
            <slot name="footer" />
        </template>
    </Card>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Card from '@/components/ui/Card.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import CalendarLegend from '@/components/ui/CalendarLegend.vue'
import { BORDER_COLOR_CLASS, BORDER_STYLES, COLOR_CLASS } from '@/components/ui/calendarClasses'
import { useI18n } from '@/composables/useI18n'

const page = usePage()
const __ = useI18n()

// ── Color class maps (full strings so Tailwind includes them) ─────────────────
// A narrow week-number column, then the seven days.
const GRID_COLUMNS = 'grid-cols-[1.75rem_repeat(7,minmax(0,1fr))]'

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
    // { [day]: true } — marks the whole week-row containing that day.
    weekMarkerDays: { type: Object, default: () => ({}) },
    weekMarkerColor: { type: String, default: 'custom' },
    // { [day]: 'solid' } — a border on that day in its own color's border token.
    dayBorders: { type: Object, default: () => ({}) },
    // { solid?: text } — the legend entry for the day border.
    borderLegenda: { type: Object, default: () => ({}) },
    // False: selecting a day still emits change, but draws no week border.
    highlightSelection: { type: Boolean, default: true },
    // A day of this month to mark with a ring (a selected date), or null.
    ringDay: { type: Number, default: null },
})

const emit = defineEmits(['change', 'day-click', 'weekday-click'])

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
const selectedWeekStart = ref(dateString(weekStartForDay(selectedDay.value)))

watch(
    () => [props.year, props.month, props.initialDay],
    ([year, month, initialDay]) => {
        const day = initialDay ?? (year === todayY && month === todayM ? todayD : 1)
        selectedDay.value = day
        selectedDayOfWeek.value = isoWeekday(day, year, month)
        selectedWeekStart.value = dateString(weekStartForDay(day, year, month))
    },
)

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

// Only non-empty entries render, as in CalendarLegend.
const hasLegend = computed(() =>
    Object.values(props.legenda).some(Boolean)
    || Object.entries(props.borderLegenda).some(([style, text]) => text && BORDER_STYLES.includes(style)),
)

// Day cells chunked into week-rows (7 per row), the first row's leading
// slots padded so day 1 lands under its actual weekday.
const weeks = computed(() => {
    const cells = [
        ...Array.from({ length: firstDayOffset.value }, () => ({ type: 'pad' })),
        ...Array.from({ length: daysInMonth.value }, (_, i) => ({ type: 'day', day: i + 1 })),
    ]
    const rows = []
    for (let i = 0; i < cells.length; i += 7) {
        rows.push(cells.slice(i, i + 7))
    }
    return rows
})

const weekMarkerNumberClass = computed(() => COLOR_CLASS[props.weekMarkerColor] ?? '')

// ISO 8601 week number of a week row: the week containing its Thursday.
function weekNumber(week) {
    const first = week.find((cell) => cell.type === 'day')
    const date = new Date(props.year, props.month - 1, first.day)
    date.setDate(date.getDate() + 3 - ((date.getDay() + 6) % 7))
    const firstThursday = new Date(date.getFullYear(), 0, 4)
    return 1 + Math.round(((date - firstThursday) / 86400000 - 3 + ((firstThursday.getDay() + 6) % 7)) / 7)
}

function isWeekMarked(week) {
    return week.some((cell) => cell.type === 'day' && props.weekMarkerDays[cell.day])
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function isToday(day) {
    return props.year === todayY && props.month === todayM && day === todayD
}

function weekStartForDay(day, y = props.year, m = props.month) {
    const date = new Date(y, m - 1, day)
    date.setDate(date.getDate() - isoWeekday(day, y, m))
    return date
}

function dateString(date) {
    const pad = (n) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

// A week is selected when a day was picked (a weekday header selects no week).
function isWeekSelected(week) {
    if (!selectedWeekStart.value) return false
    const first = week.find((cell) => cell.type === 'day')
    return dateString(weekStartForDay(first.day)) === selectedWeekStart.value
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

// clickable: false draws a plain label (no pointer cursor), e.g. a weekday
// letter without weekday selection.
function dayClass(color, selected, today, disabled, hoverable = true, borderStyle = null, clickable = true) {
    const base = `border-2 text-center rounded-md text-sm font-semibold m-1 h-8 w-8 ${clickable ? 'cursor-pointer' : 'cursor-default'}`
    const border = selected
        ? 'border-(--color-tab-active-border)'
        : borderStyle && BORDER_COLOR_CLASS[color]
          ? BORDER_COLOR_CLASS[color]
          : hoverable
          ? 'border-transparent hover:border-(--color-tab-hover-border)'
          : 'border-transparent'

    if (disabled) {
        return `${base} ${border} bg-transparent text-(--color-text-muted) cursor-not-allowed`
    }

    const colorCls = COLOR_CLASS[color] ?? ''
    const todayCls = today ? 'text-(--color-badge-error-text)' : ''
    return `${base} ${border} ${colorCls} ${todayCls}`
}

// The border of a week row: active when selected, hoverable when a click can select it.
// Without selection highlighting, the week row has no border or hover:
// each day carries its own hover border instead.
function weekRowClass(week) {
    if (!props.highlightSelection) return 'border-transparent'
    if (isWeekSelected(week)) return 'border-(--color-tab-active-border)'
    return props.enableDaySelection
        ? 'border-transparent hover:border-(--color-tab-hover-border) cursor-pointer'
        : 'border-transparent'
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
    selectedWeekStart.value = dateString(weekStartForDay(todayD, todayY, todayM))
    emitChange(todayY, todayM)
}

function onPrev() {
    const d = new Date(props.year, props.month - 2, 1)
    const newYear = d.getFullYear()
    const newMonth = d.getMonth() + 1
    selectedDay.value = 1
    selectedDayOfWeek.value = isoWeekday(1, newYear, newMonth)
    selectedWeekStart.value = dateString(weekStartForDay(1, newYear, newMonth))
    emitChange(newYear, newMonth)
}

function onNext() {
    const d = new Date(props.year, props.month, 1)
    const newYear = d.getFullYear()
    const newMonth = d.getMonth() + 1
    selectedDay.value = 1
    selectedDayOfWeek.value = isoWeekday(1, newYear, newMonth)
    selectedWeekStart.value = dateString(weekStartForDay(1, newYear, newMonth))
    emitChange(newYear, newMonth)
}

function selectWeekday(i) {
    selectedDay.value = null
    selectedDayOfWeek.value = i
    selectedWeekStart.value = null
    emitChange()
    emit('weekday-click', { weekday: i + 1 })
}

// i: Mon=0 … Sun=6
function isWeekdayHighlighted(i) {
    return selectedDay.value === null && selectedDayOfWeek.value === i
}

// Anywhere in a week row that is not a day button (the week number, the gaps, the
// blank cells) selects the week, using its first selectable day.
function onWeekRowClick(event, week) {
    if (!props.enableDaySelection || event.target.closest('button')) return
    const first = week.find((cell) => cell.type === 'day' && !isDisabled(cell.day))
    if (first) selectDay(first.day)
}

// A click on a day button (not a week row or navigation) also emits day-click.
function onDayClick(day) {
    if (isDisabled(day) || !props.enableDaySelection) return
    selectDay(day)
    emit('day-click', { year: props.year, month: props.month, day })
}

function selectDay(day) {
    const dow = isoWeekday(day)
    selectedDay.value = day
    selectedDayOfWeek.value = dow
    selectedWeekStart.value = dateString(weekStartForDay(day))
    emitChange()
}

onMounted(() => emitChange())
</script>
