import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "scheduling.title": "Scheduling",
    "scheduling.select_workcenter": "Select a workcenter",
    "scheduling.prev_week": "Previous week",
    "scheduling.next_week": "Next week",
    "scheduling.this_week": "This week",
    "scheduling.no_workcenters": "No active workcenters yet.",
    "scheduling.no_shifts": "This workcenter has no shifts attached yet.",
};

const routerGetCalls = vi.hoisted(() => []);

vi.mock("@inertiajs/vue3", () => ({
    router: { get: (...args) => routerGetCalls.push(args) },
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en } }),
}));

import Scheduling from "@/pages/Scheduling.vue";
import { SelectInput } from "@/components/ui/Input";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const baseProps = {
    workcenters: [{ id: 1, name: "Line 1" }],
    workcenterId: 1,
    weekStart: "2026-09-14",
    days: ["2026-09-14", "2026-09-15", "2026-09-16", "2026-09-17", "2026-09-18", "2026-09-19", "2026-09-20"],
    shifts: [{ id: 9, name: "Early", start_time: "06:00", end_time: "14:00" }],
    cells: [
        { shift_id: 9, date: "2026-09-14", spots: 4, assignments: [{ id: 1, employee_id: 5, employee_name: "Anna Jansen", fixed: true }] },
        { shift_id: 9, date: "2026-09-15", spots: 4, assignments: [] },
        { shift_id: 9, date: "2026-09-16", spots: 4, assignments: [] },
        { shift_id: 9, date: "2026-09-17", spots: 4, assignments: [] },
        { shift_id: 9, date: "2026-09-18", spots: 4, assignments: [] },
        { shift_id: 9, date: "2026-09-19", spots: 0, assignments: [] },
        { shift_id: 9, date: "2026-09-20", spots: 0, assignments: [] },
    ],
};

const mountPage = (props = {}) =>
    mount(Scheduling, { props: { ...baseProps, ...props }, global: { stubs } });

beforeEach(() => {
    routerGetCalls.length = 0;
});

describe("Scheduling", () => {
    it("renders a row per shift and a cell per day, with spots and assignee names", () => {
        const w = mountPage();
        const rows = w.findAll('[data-testid="scheduling-shift-row"]');
        expect(rows).toHaveLength(1);
        expect(rows[0].text()).toContain("Early");

        const cell = w.get('[data-testid="scheduling-cell-9-2026-09-14"]');
        expect(cell.text()).toContain("1/4");
        expect(cell.text()).toContain("Anna Jansen");
    });

    it("switching the workcenter select navigates with the new workcenter_id", async () => {
        const w = mountPage({ workcenters: [{ id: 1, name: "Line 1" }, { id: 2, name: "Line 2" }] });
        w.findComponent(SelectInput).vm.$emit("update:modelValue", 2);
        await w.vm.$nextTick();

        expect(routerGetCalls).toHaveLength(1);
        expect(routerGetCalls[0][0]).toBe("/scheduling");
        expect(routerGetCalls[0][1]).toMatchObject({ workcenter_id: 2, week_start: "2026-09-14" });
    });

    it("the next-week button navigates with week_start shifted forward 7 days", async () => {
        const w = mountPage();
        await w.get('[aria-label="Next week"]').trigger("click");

        expect(routerGetCalls[0][1]).toMatchObject({ workcenter_id: 1, week_start: "2026-09-21" });
    });

    it("the previous-week button navigates with week_start shifted back 7 days", async () => {
        const w = mountPage();
        await w.get('[aria-label="Previous week"]').trigger("click");

        expect(routerGetCalls[0][1]).toMatchObject({ workcenter_id: 1, week_start: "2026-09-07" });
    });

    it("the this-week button navigates without a week_start, letting the server default", async () => {
        const w = mountPage();
        await w.get("button", { text: "This week" });
        const button = w.findAll("button").find((b) => b.text() === "This week");
        await button.trigger("click");

        expect(routerGetCalls[0][1]).toEqual({ workcenter_id: 1 });
    });

    it("shows a message when there are no active workcenters", () => {
        const w = mountPage({ workcenters: [], workcenterId: null, shifts: [], cells: [] });
        expect(w.text()).toContain("No active workcenters yet.");
    });

    it("shows a message when the selected workcenter has no shifts", () => {
        const w = mountPage({ shifts: [], cells: [] });
        expect(w.text()).toContain("This workcenter has no shifts attached yet.");
    });
});
