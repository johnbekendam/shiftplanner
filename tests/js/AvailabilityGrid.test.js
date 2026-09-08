import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "availability.daypart.morning": "Morning",
    "availability.daypart.afternoon": "Afternoon",
    "availability.daypart.evening": "Evening",
    "availability.weekday.1": "Mon",
    "availability.weekday.2": "Tue",
    "availability.weekday.3": "Wed",
    "availability.weekday.4": "Thu",
    "availability.weekday.5": "Fri",
    "availability.weekday.6": "Sat",
    "availability.weekday.7": "Sun",
    "availability.state.available": "Available",
    "availability.state.not_preferred": "Not preferred",
    "availability.state.unavailable": "Unavailable",
};

const { router } = vi.hoisted(() => ({ router: { put: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    usePage: () => ({ props: { translations: en } }),
}));

import AvailabilityGrid from "@/components/AvailabilityGrid.vue";

const mountGrid = (props = {}) =>
    mount(AvailabilityGrid, {
        props: {
            availability: [{ weekday: 2, daypart: "morning", level: "unavailable" }],
            endpoint: "/employees/7/availability",
            ...props,
        },
    });

beforeEach(() => router.put.mockReset());

describe("AvailabilityGrid", () => {
    it("renders a cell for every daypart and weekday", () => {
        const w = mountGrid();
        expect(w.findAll('[data-testid^="cell-"]')).toHaveLength(21);
    });

    it("colours a stored cell by its level", () => {
        const w = mountGrid();
        const cls = w.get('[data-testid="cell-2-morning"]').classes().join(" ");
        expect(cls).toContain("bg-(--color-badge-error-bg)");
    });

    it("leaves cells with no row as available", () => {
        const w = mountGrid();
        const cls = w.get('[data-testid="cell-4-evening"]').classes().join(" ");
        expect(cls).toContain("bg-(--color-badge-standard-bg)");
    });

    it("cycles available -> not_preferred and writes to the cell endpoint", async () => {
        const w = mountGrid();
        await w.get('[data-testid="cell-4-evening"]').trigger("click");

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, payload, opts] = router.put.mock.calls[0];
        expect(url).toBe("/employees/7/availability/4/evening");
        expect(payload).toEqual({ level: "not_preferred" });
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });

        expect(w.get('[data-testid="cell-4-evening"]').classes().join(" ")).toContain(
            "bg-(--color-badge-warning-bg)",
        );
    });

    it("cycles unavailable -> available", async () => {
        const w = mountGrid();
        await w.get('[data-testid="cell-2-morning"]').trigger("click");

        expect(router.put.mock.calls[0][0]).toBe("/employees/7/availability/2/morning");
        expect(router.put.mock.calls[0][1]).toEqual({ level: "available" });
    });
});
