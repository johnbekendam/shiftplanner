import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "availability.grid.cell": ":shift, :day: :state",
    "availability.grid.no_shifts": "No shifts are defined yet.",
    "availability.grid.no_shifts_manager": "No shifts are defined yet. Add them on the Settings page.",
    "availability.weekday.1": "Mon",
    "availability.weekday.2": "Tue",
    "availability.weekday.3": "Wed",
    "availability.weekday.4": "Thu",
    "availability.weekday.5": "Fri",
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

const shifts = [
    { id: 10, name: "Early", start_time: "06:00", end_time: "14:00" },
    { id: 20, name: "Late", start_time: "14:00", end_time: "22:00" },
];

const mountGrid = (props = {}) =>
    mount(AvailabilityGrid, {
        props: {
            shifts,
            availability: [{ weekday: 2, shift_id: 10, level: "unavailable" }],
            endpoint: "/employees/7/availability",
            ...props,
        },
    });

beforeEach(() => router.put.mockReset());

describe("AvailabilityGrid", () => {
    it("renders one row per shift and a cell for every weekday (Mon–Fri)", () => {
        const w = mountGrid();
        expect(w.findAll('[data-testid^="cell-"]')).toHaveLength(10);
        expect(w.text()).toContain("Early");
        expect(w.text()).toContain("Late");
        expect(w.text()).toContain("06:00 – 14:00");
        expect(w.text()).not.toContain("Sat");
        expect(w.text()).not.toContain("Sun");
    });

    it("colours a stored cell by its level", () => {
        const w = mountGrid();
        const cls = w.get('[data-testid="cell-2-10"]').classes().join(" ");
        expect(cls).toContain("bg-(--color-badge-error-bg)");
    });

    it("leaves cells with no row as available (success tokens)", () => {
        const w = mountGrid();
        const cls = w.get('[data-testid="cell-4-20"]').classes().join(" ");
        expect(cls).toContain("bg-(--color-badge-success-bg)");
    });

    it("cycles available -> not_preferred and writes to the shift cell endpoint", async () => {
        const w = mountGrid();
        await w.get('[data-testid="cell-4-20"]').trigger("click");

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, payload, opts] = router.put.mock.calls[0];
        expect(url).toBe("/employees/7/availability/4/20");
        expect(payload).toEqual({ level: "not_preferred" });
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });

        expect(w.get('[data-testid="cell-4-20"]').classes().join(" ")).toContain(
            "bg-(--color-badge-warning-bg)",
        );
    });

    it("cycles unavailable -> available", async () => {
        const w = mountGrid();
        await w.get('[data-testid="cell-2-10"]').trigger("click");

        expect(router.put.mock.calls[0][0]).toBe("/employees/7/availability/2/10");
        expect(router.put.mock.calls[0][1]).toEqual({ level: "available" });
    });

    it("shows the plain empty state when no shift is defined", () => {
        const w = mountGrid({ shifts: [] });
        expect(w.text()).toContain("No shifts are defined yet.");
        expect(w.text()).not.toContain("Settings page");
        expect(w.findAll('[data-testid^="cell-"]')).toHaveLength(0);
    });

    it("adds the Settings hint to the empty state on the manager surface", () => {
        const w = mountGrid({ shifts: [], showAddHint: true });
        expect(w.text()).toContain("Add them on the Settings page.");
    });
});
