import { reactive, ref } from 'vue'
import { groupOverrides } from '@/utils/availabilityCalendar'
import { putAsync } from '@/utils/inertiaAsync'

const EMPTY_DAY = { blocked: false, shifts: {} }

const isEmpty = (day) => !day.blocked && Object.keys(day.shifts).length === 0
const sameDay = (a, b) => JSON.stringify(a ?? EMPTY_DAY) === JSON.stringify(b ?? EMPTY_DAY)

/**
 * The date overrides of one employee page: the saved state, the current
 * state with pending edits, and a save that sends one PUT per changed
 * date. Plug `isDirty` and `save` into the page's save registry.
 *
 * rows: the page's `availabilityOverrides` prop, [{ date, shift_id, level }].
 * urlFor: date => the PUT url of that date.
 */
export function useDateOverrides(rows, urlFor) {
    // { [date]: { blocked, shifts } } — a date without overrides has no key.
    const committed = ref(groupOverrides(rows))
    const overrides = ref({ ...committed.value })
    const pending = reactive({})

    function applyDay({ date, blocked, shifts }) {
        const day = { blocked, shifts }
        const next = { ...overrides.value }
        if (isEmpty(day)) delete next[date]
        else next[date] = day
        overrides.value = next

        if (sameDay(committed.value[date], day)) delete pending[date]
        else pending[date] = day
    }

    const isDirty = () => Object.keys(pending).length > 0

    async function save() {
        let ok = true
        for (const [date, day] of Object.entries(pending)) {
            try {
                await putAsync(urlFor(date), day)
                const next = { ...committed.value }
                if (isEmpty(day)) delete next[date]
                else next[date] = day
                committed.value = next
                // An edit made while this request ran stays pending.
                if (sameDay(pending[date], day)) delete pending[date]
            } catch {
                ok = false
            }
        }
        return ok
    }

    function reset() {
        for (const date of Object.keys(pending)) delete pending[date]
        overrides.value = { ...committed.value }
    }

    return { overrides, applyDay, isDirty, save, reset }
}
