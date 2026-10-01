import { dateString, isoWeekday } from '@/utils/availabilityCalendar'

// Override rows [{ shift_id, date, spots }] as { [date]: { [shift_id]: spots } }.
export function groupDemandOverrides(rows) {
    const grouped = {}
    for (const row of rows) {
        grouped[row.date] = { ...grouped[row.date], [row.shift_id]: row.spots }
    }
    return grouped
}

// Assigned rows [{ shift_id, date, count }] as { [date]: { [shift_id]: count } }.
export function groupAssigned(rows) {
    const grouped = {}
    for (const row of rows) {
        grouped[row.date] = { ...grouped[row.date], [row.shift_id]: row.count }
    }
    return grouped
}

// The demand of one workcenter on a date. ctx: { defaults: [{ shift_id, spots[7] }],
// overrides: groupDemandOverrides() shape, assigned: groupAssigned() shape }.
// fill: success when every slot is filled, warning with open slots, muted without slots.
export function dayDemand(date, ctx) {
    const weekday = isoWeekday(date)
    const overrides = ctx.overrides[date] ?? {}
    const assigned = ctx.assigned[date] ?? {}

    const shifts = ctx.defaults.map((row) => {
        const override = overrides[row.shift_id] ?? null
        const defaultSpots = row.spots[weekday - 1]
        return {
            shift_id: row.shift_id,
            defaultSpots,
            override,
            spots: override ?? defaultSpots,
            assigned: assigned[row.shift_id] ?? 0,
        }
    })

    const open = shifts.filter((s) => s.spots > 0)
    let fill = 'muted'
    if (open.length) fill = open.every((s) => s.assigned >= s.spots) ? 'success' : 'warning'

    return {
        date,
        shifts,
        fill,
        border: shifts.some((s) => s.override !== null) ? 'solid' : null,
    }
}

export function monthDemandStates(year, month, ctx) {
    const dayStates = {}
    const dayBorders = {}
    const days = new Date(year, month, 0).getDate()

    for (let day = 1; day <= days; day++) {
        const state = dayDemand(dateString(year, month, day), ctx)
        dayStates[day] = state.fill
        if (state.border) dayBorders[day] = state.border
    }

    return { dayStates, dayBorders }
}
