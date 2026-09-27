import { describe, it, expect, vi, beforeEach } from "vitest";

const { puts, failing } = vi.hoisted(() => ({ puts: [], failing: new Set() }));

vi.mock("@/utils/inertiaAsync", () => ({
    putAsync: (url, data) => {
        puts.push([url, data]);
        return failing.has(url) ? Promise.reject(new Error("fail")) : Promise.resolve();
    },
}));

import { useDateOverrides } from "@/composables/useDateOverrides";

const url = (date) => `/dates/${date}`;

beforeEach(() => {
    puts.length = 0;
    failing.clear();
});

describe("useDateOverrides", () => {
    it("groups the saved rows and starts clean", () => {
        const dates = useDateOverrides([{ date: "2026-10-05", shift_id: null, level: "unavailable" }], url);

        expect(dates.overrides.value).toEqual({ "2026-10-05": { blocked: true, shifts: {} } });
        expect(dates.isDirty()).toBe(false);
    });

    it("applies a day, drops an empty day, and is clean again when back at the saved state", () => {
        const dates = useDateOverrides([{ date: "2026-10-05", shift_id: null, level: "unavailable" }], url);

        dates.applyDay({ date: "2026-10-05", blocked: false, shifts: {} });
        expect(dates.overrides.value).toEqual({});
        expect(dates.isDirty()).toBe(true);

        dates.applyDay({ date: "2026-10-05", blocked: true, shifts: {} });
        expect(dates.isDirty()).toBe(false);
    });

    it("saves one PUT per changed date and keeps a failed date pending", async () => {
        const dates = useDateOverrides([], url);
        dates.applyDay({ date: "2026-10-05", blocked: true, shifts: {} });
        dates.applyDay({ date: "2026-10-06", blocked: false, shifts: { 1: "available" } });
        failing.add("/dates/2026-10-06");

        expect(await dates.save()).toBe(false);

        expect(puts).toEqual([
            ["/dates/2026-10-05", { blocked: true, shifts: {} }],
            ["/dates/2026-10-06", { blocked: false, shifts: { 1: "available" } }],
        ]);
        failing.clear();
        puts.length = 0;
        expect(await dates.save()).toBe(true);
        expect(puts).toEqual([["/dates/2026-10-06", { blocked: false, shifts: { 1: "available" } }]]);
        expect(dates.isDirty()).toBe(false);
    });

    it("resets to the last saved state", async () => {
        const dates = useDateOverrides([], url);
        dates.applyDay({ date: "2026-10-05", blocked: true, shifts: {} });
        await dates.save();
        dates.applyDay({ date: "2026-10-06", blocked: true, shifts: {} });

        dates.reset();

        expect(dates.overrides.value).toEqual({ "2026-10-05": { blocked: true, shifts: {} } });
        expect(dates.isDirty()).toBe(false);
    });
});
