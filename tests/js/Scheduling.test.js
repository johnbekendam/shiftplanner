import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "scheduling.title": "Scheduling",
    "scheduling.filter_workcenters": "Workcenters",
    "scheduling.filter_shifts": "Shifts",
    "scheduling.no_workcenters": "No active workcenters yet. Add one on the Settings page.",
    "scheduling.legend_staffed": "Fully staffed",
    "scheduling.legend_open_spots": "Open spots",
    "calendar.reset": "Jump to today",
    "calendar.prev_month": "Previous month",
    "calendar.next_month": "Next month",
};

const routerGetCalls = vi.hoisted(() => []);

vi.mock("@inertiajs/vue3", () => ({
    router: { get: (...args) => routerGetCalls.push(args) },
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en, auth: { settings: { month_format: "my" } } } }),
}));

import Scheduling from "@/pages/Scheduling.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const baseProps = {
    workcenters: [
        { id: 1, name: "Line 1" },
        { id: 2, name: "Line 2" },
    ],
    shifts: [
        { id: 9, name: "Early" },
        { id: 10, name: "Late" },
    ],
    year: 2026,
    month: 9,
    coverage: [
        { workcenter_id: 1, shift_id: 9, date: "2026-09-10", spots: 3, assigned: 3 },
        { workcenter_id: 1, shift_id: 9, date: "2026-09-11", spots: 3, assigned: 1 },
        { workcenter_id: 2, shift_id: 10, date: "2026-09-12", spots: 2, assigned: 2 },
    ],
};

const mountPage = (props = {}) =>
    mount(Scheduling, { props: { ...baseProps, ...props }, global: { stubs } });

function dayButton(w, day) {
    return w.findAll("button").find((b) => b.text() === String(day));
}

beforeEach(() => {
    routerGetCalls.length = 0;
});

describe("Scheduling", () => {
    it("renders a checklist per workcenter and per shift, all checked by default", () => {
        const w = mountPage();
        const checkboxes = w.findAll('input[type="checkbox"]');

        expect(checkboxes).toHaveLength(4); // 2 workcenters + 2 shifts
        checkboxes.forEach((c) => expect(c.element.checked).toBe(true));
        expect(w.text()).toContain("Line 1");
        expect(w.text()).toContain("Line 2");
        expect(w.text()).toContain("Early");
        expect(w.text()).toContain("Late");
    });

    it("renders the workcenters and shifts lists as two separate cards", () => {
        const w = mountPage();
        const headers = w.findAll("h1, h2, h3, div").filter((el) => el.text() === "Workcenters" || el.text() === "Shifts");

        expect(headers.length).toBeGreaterThanOrEqual(2);
    });

    it("renders the calendar for the given year and month", () => {
        const w = mountPage();
        expect(w.text()).toContain("September 2026");
    });

    it("colors a fully-staffed day green", () => {
        const w = mountPage();
        expect(dayButton(w, 10).classes().join(" ")).toContain("bg-(--color-badge-success-bg)");
    });

    it("colors a short-staffed day warning", () => {
        const w = mountPage();
        expect(dayButton(w, 11).classes().join(" ")).toContain("bg-(--color-badge-warning-bg)");
    });

    it("colors a day with no relevant coverage muted", () => {
        const w = mountPage();
        expect(dayButton(w, 15).classes().join(" ")).toContain("bg-(--color-badge-muted-bg)");
    });

    it("unchecking a workcenter recolors days that only had coverage from it, with no navigation", async () => {
        const w = mountPage();
        const line2Checkbox = w.findAll('input[type="checkbox"]').at(1); // Line 2

        await line2Checkbox.setValue(false);

        expect(dayButton(w, 12).classes().join(" ")).toContain("bg-(--color-badge-muted-bg)");
        expect(routerGetCalls).toHaveLength(0);
    });

    it("navigates to the next month via the calendar, carrying year/month", async () => {
        const w = mountPage();
        await w.get('[aria-label="Next month"]').trigger("click");

        expect(routerGetCalls).toHaveLength(1);
        expect(routerGetCalls[0][0]).toBe("/scheduling");
        expect(routerGetCalls[0][1]).toEqual({ year: 2026, month: 10 });
    });

    it("does not navigate on initial mount", () => {
        mountPage();
        expect(routerGetCalls).toHaveLength(0);
    });

    it("shows a message when there are no active workcenters", () => {
        const w = mountPage({ workcenters: [], coverage: [] });
        expect(w.text()).toContain("No active workcenters yet. Add one on the Settings page.");
    });
});
