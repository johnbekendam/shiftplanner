import { describe, it, expect } from "vitest";
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

import { useI18n } from "@/composables/useI18n";
import { vi } from "vitest";

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key, params) => {
        let s = en[key] ?? key;
        for (const [k, v] of Object.entries(params ?? {})) s = s.replaceAll(`:${k}`, v);
        return s;
    },
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
            ...props,
        },
        global: { stubs: { teleport: true } },
    });

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

    it("opens a menu on click", async () => {
        const w = mountGrid();
        expect(w.find('[data-testid="availability-menu"]').exists()).toBe(false);

        await w.get('[data-testid="cell-4-20"]').trigger("click");

        expect(w.get('[data-testid="availability-menu"]').exists()).toBe(true);
    });

    it("updates the cell locally and emits update:availability, without any network call", async () => {
        const w = mountGrid();
        await w.get('[data-testid="cell-4-20"]').trigger("click");
        await w.get('[data-testid="availability-menu-not_preferred"]').trigger("click");

        expect(w.emitted("update:availability")).toEqual([
            [{ weekday: 4, shiftId: 20, level: "not_preferred" }],
        ]);
        expect(w.get('[data-testid="cell-4-20"]').classes().join(" ")).toContain(
            "bg-(--color-badge-warning-bg)",
        );
        expect(w.find('[data-testid="availability-menu"]').exists()).toBe(false);
    });

    it("does not emit when the picked state matches the current one", async () => {
        const w = mountGrid();
        await w.get('[data-testid="cell-2-10"]').trigger("click");
        await w.get('[data-testid="availability-menu-unavailable"]').trigger("click");

        expect(w.emitted("update:availability")).toBeUndefined();
    });

    it("does not open the menu and marks cells disabled when disabled", async () => {
        const w = mountGrid({ disabled: true });
        await w.get('[data-testid="cell-2-10"]').trigger("click");

        expect(w.find('[data-testid="availability-menu"]').exists()).toBe(false);
        expect(w.get('[data-testid="cell-2-10"]').attributes("disabled")).toBeDefined();
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
