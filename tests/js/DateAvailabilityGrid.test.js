import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "availability.grid.shift_column": "Shift",
    "availability.grid.cell": ":shift, :day: :state",
    "availability.grid.no_shifts_on_day": "No shift runs on this day.",
    "availability.day.block": "Block the whole day",
    "availability.day.default": "Default (:level)",
    "availability.day.holiday_notice": "This day is a holiday.",
    "availability.day.reset": "Reset to default",
    "availability.weekday.1": "Mon",
    "availability.state.available": "Available",
    "availability.state.not_set": "Not set",
    "availability.state.not_preferred": "Not preferred",
    "availability.state.unavailable": "Unavailable",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key, params) => {
        let s = en[key] ?? key;
        for (const [k, v] of Object.entries(params ?? {})) s = s.replaceAll(`:${k}`, v);
        return s;
    },
}));

import DateAvailabilityGrid from "@/components/DateAvailabilityGrid.vue";
import { CheckboxInput } from "@/components/ui/Input";
import CardSeparator from "@/components/ui/CardSeparator.vue";

const early = { id: 10, name: "Early", start_time: "06:00", end_time: "14:00" };
const late = { id: 20, name: "Late", start_time: "14:00", end_time: "22:00" };

const day = (overrides = {}) => ({
    date: "2026-10-05",
    holiday: false,
    blocked: false,
    changed: true,
    shifts: [
        { shift: early, defaultLevel: "available", override: null, status: "available" },
        { shift: late, defaultLevel: "not_set", override: "not_preferred", status: "not_preferred" },
    ],
    ...overrides,
});

const mountGrid = (props = {}) =>
    mount(DateAvailabilityGrid, {
        props: { day: day(), ...props },
        global: { stubs: { teleport: true, transition: false } },
    });

const cell = (w, shiftId) => w.get(`[data-testid="date-cell-${shiftId}"]`);

describe("DateAvailabilityGrid", () => {
    it("shows one row per running shift with its effective level, marking overrides", () => {
        const w = mountGrid();

        expect(w.findAll("thead th").map((th) => th.text())).toEqual(["Shift", "Mon"]);
        expect(w.text()).toContain("Early");
        expect(cell(w, 10).classes()).toContain("bg-(--color-badge-success-bg)");
        expect(cell(w, 10).classes()).not.toContain("border-2");
        expect(cell(w, 20).classes()).toContain("bg-(--color-badge-warning-bg)");
        expect(cell(w, 20).classes()).toEqual(expect.arrayContaining(["border-2", "border-(--color-badge-warning-border)"]));
        expect(cell(w, 20).classes()).not.toContain("border-(--color-tab-active-border)");
    });

    it("offers the default as the first menu entry and emits the whole day on a choice", async () => {
        const w = mountGrid();

        await cell(w, 10).trigger("click");
        expect(w.get('[data-testid="availability-menu-default"]').text()).toBe("Default (Available)");
        await w.get('[data-testid="availability-menu-unavailable"]').trigger("click");

        expect(w.emitted("apply-day")[0][0]).toEqual({
            date: "2026-10-05",
            blocked: false,
            shifts: { 10: "unavailable", 20: "not_preferred" },
        });
    });

    it("removes a shift override when Default is chosen", async () => {
        const w = mountGrid();

        await cell(w, 20).trigger("click");
        await w.get('[data-testid="availability-menu-default"]').trigger("click");

        expect(w.emitted("apply-day")[0][0]).toEqual({ date: "2026-10-05", blocked: false, shifts: {} });
    });

    it("shows every shift unavailable and locks the cells on a blocked day", () => {
        const w = mountGrid({ day: day({ blocked: true }) });

        expect(cell(w, 10).classes()).toContain("bg-(--color-badge-error-bg)");
        expect(cell(w, 10).attributes("disabled")).toBeDefined();
    });


    it("shows only the holiday notice on a holiday", () => {
        const w = mountGrid({ day: day({ holiday: true }) });

        expect(w.text()).toBe("This day is a holiday.");
        expect(w.find("table").exists()).toBe(false);
    });

    it("says so on a day without shifts", () => {
        const w = mountGrid({ day: day({ shifts: [] }) });

        expect(w.text()).toContain("No shift runs on this day.");
    });



    it("is read-only when disabled", () => {
        const w = mountGrid({ disabled: true });

        expect(cell(w, 10).attributes("disabled")).toBeDefined();
    });

    it("has no block checkbox, reset button or separator of its own; the date card holds them", () => {
        const w = mountGrid();

        expect(w.findComponent(CheckboxInput).exists()).toBe(false);
        expect(w.findAll("button").some((b) => b.text() === "Reset to default")).toBe(false);
        expect(w.find("hr").exists()).toBe(false);
    });


    it("keeps hidden overrides when a shift changes", async () => {
        const w = mountGrid({ day: day({ hiddenOverrides: { 30: "available" } }) });

        await cell(w, 10).trigger("click");
        await w.get('[data-testid="availability-menu-unavailable"]').trigger("click");

        expect(w.emitted("apply-day")).toEqual([
            [{ date: "2026-10-05", blocked: false, shifts: { 30: "available", 10: "unavailable", 20: "not_preferred" } }],
        ]);
    });
});
