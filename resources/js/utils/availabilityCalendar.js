// Client-side mirror of App\Services\EmployeeAvailability for the
// availability calendar. The order is the same: before the start date,
// holiday, whole-day block, shift override, weekly default. A missing
// default is unavailable.

const pad = (n) => String(n).padStart(2, '0')

export function dateString(year, month, day) {
    return `${year}-${pad(month)}-${pad(day)}`
}

// ISO weekday (Mon=1 … Sun=7) of a 'Y-m-d' string, in local time.
export function isoWeekday(date) {
    const [y, m, d] = date.split('-').map(Number)
    return ((new Date(y, m - 1, d).getDay() + 6) % 7) + 1
}

// Override rows [{ date, shift_id, level }] → { [date]: { blocked, shifts: { [shiftId]: level } } }.
export function groupOverrides(rows) {
    const byDate = {}
    for (const row of rows) {
        byDate[row.date] ??= { blocked: false, shifts: {} }
        if (row.shift_id === null) byDate[row.date].blocked = true
        else byDate[row.date].shifts[row.shift_id] = row.level
    }
    return byDate
}

/**
 * The state of one date.
 *
 * ctx: { shifts, availableFrom, holidays: [{ start_date, end_date }],
 *        defaults: [{ weekday, shift_id, level }], overrides: groupOverrides() result }
 */
export function dayAvailability(date, ctx) {
    const weekday = isoWeekday(date)
    const notStarted = !!ctx.availableFrom && date < ctx.availableFrom
    const holiday = ctx.holidays.some((h) => h.start_date <= date && h.end_date >= date)
    const override = ctx.overrides[date] ?? { blocked: false, shifts: {} }
    const changed = override.blocked || Object.keys(override.shifts).length > 0

    const shifts = ctx.shifts
        .filter((shift) => (shift.weekdays ?? []).includes(weekday))
        .map((shift) => {
            const defaultLevel = ctx.defaults.find((r) => r.weekday === weekday && r.shift_id === shift.id)?.level ?? 'not_set'
            const shiftOverride = override.shifts[shift.id] ?? null
            let status = shiftOverride ?? (defaultLevel === 'not_set' ? 'unavailable' : defaultLevel)
            if (override.blocked) status = 'unavailable'
            if (holiday) status = 'holiday'
            if (notStarted) status = 'not_started'
            return { shift, defaultLevel, override: shiftOverride, status }
        })

    const fill = shifts.some((s) => s.status === 'available')
        ? 'success'
        : shifts.some((s) => s.status === 'not_preferred') ? 'warning' : 'error'

    let border = null
    if (!notStarted) border = holiday ? 'dashed' : changed ? 'solid' : null

    return { date, notStarted, holiday, blocked: override.blocked, changed, shifts, fill, border }
}

// Calendar.vue's dayStates and dayBorders for one month, keyed by day number.
export function monthStates(year, month, ctx) {
    const dayStates = {}
    const dayBorders = {}
    const days = new Date(year, month, 0).getDate()

    for (let day = 1; day <= days; day++) {
        const state = dayAvailability(dateString(year, month, day), ctx)
        dayStates[day] = state.fill
        if (state.border) dayBorders[day] = state.border
    }

    return { dayStates, dayBorders }
}
