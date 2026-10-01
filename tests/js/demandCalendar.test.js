import { describe, it, expect } from "vitest";
import { dayDemand, groupDemandOverrides, groupAssigned, monthDemandStates } from "@/utils/demandCalendar";

// Early: 2 slots Monday, 1 on Tuesday. Late: 1 slot Monday only.
const context = (extra = {}) => ({
    defaults: [
        { shift_id: 10, spots: [2, 1, 0, 0, 0, 0, 0] },
        { shift_id: 20, spots: [1, 0, 0, 0, 0, 0, 0] },
    ],
    overrides: {},
    assigned: {},
    ...extra,
});

// 2026-10-05 is a Monday, 2026-10-06 a Tuesday, 2026-10-07 a Wednesday.
describe("demandCalendar", () => {
    it("groups override rows by date and shift", () => {
        expect(
            groupDemandOverrides([
                { shift_id: 10, date: "2026-10-05", spots: 3 },
                { shift_id: 20, date: "2026-10-05", spots: 0 },
                { shift_id: 10, date: "2026-10-06", spots: 1 },
            ]),
        ).toEqual({ "2026-10-05": { 10: 3, 20: 0 }, "2026-10-06": { 10: 1 } });
    });

    it("groups assigned counts by date and shift", () => {
        expect(groupAssigned([{ shift_id: 10, date: "2026-10-05", count: 2 }])).toEqual({ "2026-10-05": { 10: 2 } });
    });

    it("lists every shift with its weekday default, override, effective slots and assigned count", () => {
        const day = dayDemand("2026-10-05", context({
            overrides: { "2026-10-05": { 20: 3 } },
            assigned: { "2026-10-05": { 10: 1 } },
        }));

        expect(day.shifts).toEqual([
            { shift_id: 10, defaultSpots: 2, override: null, spots: 2, assigned: 1 },
            { shift_id: 20, defaultSpots: 1, override: 3, spots: 3, assigned: 0 },
        ]);
    });

    it("fills success when every slot is filled, warning with open slots, muted without slots", () => {
        const full = { "2026-10-05": { 10: 2, 20: 1 }, "2026-10-06": { 10: 1 } };
        expect(dayDemand("2026-10-05", context({ assigned: full })).fill).toBe("success");
        expect(dayDemand("2026-10-05", context({ assigned: { "2026-10-05": { 10: 2 } } })).fill).toBe("warning");
        expect(dayDemand("2026-10-07", context()).fill).toBe("muted");
    });

    it("lets an override open a day without default slots", () => {
        expect(dayDemand("2026-10-07", context({ overrides: { "2026-10-07": { 10: 1 } } })).fill).toBe("warning");
    });

    it("marks only a day with an override with a solid border", () => {
        expect(dayDemand("2026-10-05", context({ overrides: { "2026-10-05": { 10: 2 } } })).border).toBe("solid");
        expect(dayDemand("2026-10-06", context()).border).toBeNull();
    });

    it("builds the states and borders of a whole month", () => {
        const { dayStates, dayBorders } = monthDemandStates(2026, 10, context({ overrides: { "2026-10-07": { 10: 1 } } }));

        expect(Object.keys(dayStates)).toHaveLength(31);
        expect(dayStates[5]).toBe("warning");
        expect(dayStates[7]).toBe("warning");
        expect(dayStates[8]).toBe("muted");
        expect(dayBorders).toEqual({ 7: "solid" });
    });
});
