import { describe, expect, it } from 'vitest'
import { calculateAvailabilityHours } from '@/utils/availabilityHours'

const shifts = [
    { id: 1, name: 'Morning', start_time: '08:00', end_time: '12:00' },
    { id: 2, name: 'Afternoon', start_time: '13:00', end_time: '17:00' },
]

describe('calculateAvailabilityHours', () => {
    it('counts available shifts as preferred hours and not-preferred shifts as available hours', () => {
        const availability = [
            { weekday: 1, shift_id: 1, level: 'not_preferred' },
            { weekday: 2, shift_id: 2, level: 'unavailable' },
        ]

        expect(calculateAvailabilityHours(shifts, availability)).toEqual({
            preferred: 32,
            available: 36,
        })
    })

    it('calculates each configured weekday independently', () => {
        const availability = [
            { weekday: 1, shift_id: 1, level: 'unavailable' },
            { weekday: 2, shift_id: 1, level: 'unavailable' },
            { weekday: 3, shift_id: 1, level: 'unavailable' },
            { weekday: 4, shift_id: 1, level: 'unavailable' },
            { weekday: 5, shift_id: 1, level: 'unavailable' },
        ]

        expect(calculateAvailabilityHours(shifts, availability)).toEqual({
            preferred: 20,
            available: 20,
        })
    })
})