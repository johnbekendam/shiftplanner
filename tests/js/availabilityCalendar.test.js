import { describe, it, expect } from "vitest";
import { dayAvailability, dayOverrides, groupOverrides, monthStates, isoWeekday } from "@/utils/availabilityCalendar";

const early = { id: 10, name: "Early", start_time: "06:00", end_time: "14:00", weekdays: [1, 2, 3, 4, 5] };
const late = { id: 20, name: "Late", start_time: "14:00", end_time: "22:00", weekdays: [1, 6] };

const context = (overrides = {}) => ({
    shifts: [early, late],
    availableFrom: null,
    holidays: [],
    defaults: [
        { weekday: 1, shift_id: 10, level: "available" },
        { weekday: 1, shift_id: 20, level: "not_preferred" },
        { weekday: 2, shift_id: 10, level: "not_preferred" },
    ],
    overrides: {},
    ...overrides,
});

// 2026-10-05 is a Monday, 2026-10-06 a Tuesday, 2026-10-07 a Wednesday, 2026-10-10 a Saturday.
describe("availabilityCalendar", () => {
    it("gives the ISO weekday of a date string", () => {
        expect(isoWeekday("2026-10-05")).toBe(1);
        expect(isoWeekday("2026-10-11")).toBe(7);
    });

    it("groups override rows by date into a block flag and shift levels", () => {
        expect(
            groupOverrides([
                { date: "2026-10-05", shift_id: null, level: "unavailable" },
                { date: "2026-10-05", shift_id: 10, level: "available" },
                { date: "2026-10-06", shift_id: 20, level: "not_preferred" },
            ]),
        ).toEqual({
            "2026-10-05": { blocked: true, shifts: { 10: "available" } },
            "2026-10-06": { blocked: false, shifts: { 20: "not_preferred" } },
        });
    });

    it("lists only the shifts that run that day, with their default and effective level", () => {
        const day = dayAvailability("2026-10-05", context());

        expect(day.shifts.map((s) => [s.shift.id, s.defaultLevel, s.override, s.status])).toEqual([
            [10, "available", null, "available"],
            [20, "not_preferred", null, "not_preferred"],
        ]);
        expect(dayAvailability("2026-10-06", context()).shifts.map((s) => s.shift.id)).toEqual([10]);
    });

    it("fills green with one available shift, yellow with only not preferred, red otherwise", () => {
        expect(dayAvailability("2026-10-05", context()).fill).toBe("success");
        expect(dayAvailability("2026-10-06", context()).fill).toBe("warning");
        // Wednesday: Early runs but has no default row, so it is unavailable.
        expect(dayAvailability("2026-10-07", context()).fill).toBe("error");
        // Sunday: no shift runs.
        expect(dayAvailability("2026-10-11", context()).fill).toBe("error");
    });

    it("lets a shift override replace the default and marks the day as changed", () => {
        const day = dayAvailability("2026-10-07", context({ overrides: { "2026-10-07": { blocked: false, shifts: { 10: "available" } } } }));

        expect(day.shifts[0]).toMatchObject({ defaultLevel: "not_set", override: "available", status: "available" });
        expect(day.fill).toBe("success");
        expect(day.changed).toBe(true);
        expect(day.border).toBe("solid");
    });

    it("makes every shift unavailable on a blocked day, also a day without shifts", () => {
        const blocked = context({
            overrides: {
                "2026-10-05": { blocked: true, shifts: { 10: "available" } },
                "2026-10-11": { blocked: true, shifts: {} },
            },
        });

        expect(dayAvailability("2026-10-05", blocked).shifts.map((s) => s.status)).toEqual(["unavailable", "unavailable"]);
        expect(dayAvailability("2026-10-05", blocked).fill).toBe("error");
        expect(dayAvailability("2026-10-11", blocked)).toMatchObject({ blocked: true, changed: true, border: "solid" });
    });

    it("fills a holiday with the custom color and no border, whatever its overrides", () => {
        const day = dayAvailability(
            "2026-10-05",
            context({
                holidays: [{ start_date: "2026-10-05", end_date: "2026-10-06" }],
                overrides: { "2026-10-05": { blocked: false, shifts: { 10: "available" } } },
            }),
        );

        expect(day).toMatchObject({ holiday: true, changed: true, fill: "custom", border: null });
        expect(day.shifts.every((s) => s.status === "holiday")).toBe(true);
    });

    it("marks a day before the start date as not started", () => {
        const ctx = context({ availableFrom: "2026-10-06" });

        expect(dayAvailability("2026-10-05", ctx)).toMatchObject({ notStarted: true, border: null });
        expect(dayAvailability("2026-10-06", ctx).notStarted).toBe(false);
    });

    it("builds the fill and border maps for a month, keyed by day number", () => {
        const { dayStates, dayBorders } = monthStates(
            2026,
            10,
            context({
                holidays: [{ start_date: "2026-10-12", end_date: "2026-10-12" }],
                overrides: { "2026-10-07": { blocked: false, shifts: { 10: "available" } } },
            }),
        );

        expect(Object.keys(dayStates)).toHaveLength(31);
        expect(dayStates[5]).toBe("success");
        expect(dayStates[7]).toBe("success");
        expect(dayStates[12]).toBe("custom");
        expect(dayBorders).toEqual({ 7: "solid" });
    });

    it("keeps overrides of shifts that do not run that day apart, and not as a change", () => {
        // 2026-10-06 is a Tuesday: Late (20) does not run.
        const day = dayAvailability("2026-10-06", context({ overrides: { "2026-10-06": { blocked: false, shifts: { 20: "available" } } } }));

        expect(day.shifts.map((s) => s.shift.id)).toEqual([10]);
        expect(day.hiddenOverrides).toEqual({ 20: "available" });
        expect(day.changed).toBe(false);
        expect(day.border).toBeNull();
        expect(dayOverrides(day)).toEqual({ 20: "available" });
    });

    it("merges visible and hidden overrides in dayOverrides", () => {
        const day = dayAvailability("2026-10-06", context({ overrides: { "2026-10-06": { blocked: false, shifts: { 10: "unavailable", 20: "available" } } } }));

        expect(day.changed).toBe(true);
        expect(dayOverrides(day)).toEqual({ 10: "unavailable", 20: "available" });
    });

    it("lets open and closed dates of a shift overrule its weekdays", () => {
        const shifts = [
            { ...early, open_dates: ["2026-10-10"], closed_dates: ["2026-10-05"] },
            late,
        ];
        const ctx = context({ shifts });

        expect(dayAvailability("2026-10-10", ctx).shifts.map((s) => s.shift.id)).toEqual([10, 20]);
        expect(dayAvailability("2026-10-05", ctx).shifts.map((s) => s.shift.id)).toEqual([20]);
        expect(dayAvailability("2026-10-12", ctx).shifts.map((s) => s.shift.id)).toEqual([10, 20]);
    });
});
