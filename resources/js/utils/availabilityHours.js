const WEEKDAYS = [1, 2, 3, 4, 5]

function hoursBetween(startTime, endTime) {
    const [startHour, startMinute] = startTime.split(':').map(Number)
    const [endHour, endMinute] = endTime.split(':').map(Number)

    return ((endHour * 60 + endMinute) - (startHour * 60 + startMinute)) / 60
}

export function calculateAvailabilityHours(shifts, availability) {
    const levels = new Map(availability.map(({ weekday, shift_id: shiftId, level }) => [
        `${weekday}-${shiftId}`,
        level,
    ]))

    return shifts.reduce((totals, shift) => {
        const hours = hoursBetween(shift.start_time, shift.end_time)

        for (const weekday of WEEKDAYS) {
            const level = levels.get(`${weekday}-${shift.id}`) ?? 'available'

            if (level !== 'unavailable') totals.available += hours
            if (level === 'available') totals.preferred += hours
        }

        return totals
    }, { preferred: 0, available: 0 })
}