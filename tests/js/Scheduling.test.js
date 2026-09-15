import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "scheduling.title": "Planning",
    "scheduling.filter_workcenters": "Workcenters",
    "scheduling.filter_shifts": "Shifts",
    "scheduling.no_schedule": "There's no schedule yet. Add one on the Schedule page.",
    "scheduling.legend_staffed": "Fully staffed",
    "scheduling.legend_open_spots": "Open spots",
    "scheduling.publish": "Publish",
    "scheduling.unpublish": "Unpublish",
    "scheduling.published_label": "Published",
    "calendar.reset": "Jump to today",
    "calendar.prev_month": "Previous month",
    "calendar.next_month": "Next month",
};

const routerGetCalls = vi.hoisted(() => []);

const { routerCalls, router } = vi.hoisted(() => {
    const routerCalls = [];
    const respond = (name) => (...args) => {
        const last = args.at(-1);
        const hasOpts = last && typeof last === "object" && (last.onSuccess || last.onError);
        const opts = hasOpts ? last : undefined;
        const rest = hasOpts ? args.slice(0, -1) : args;
        routerCalls.push([name, ...rest]);
        opts?.onSuccess?.();
    };
    return { routerCalls, router: { post: respond("post"), delete: respond("delete") } };
});

vi.mock("@inertiajs/vue3", () => ({
    router: { get: (...args) => routerGetCalls.push(args), post: router.post, delete: router.delete, on: () => () => {} },
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en, auth: { settings: { month_format: "my" } } } }),
}));

import Scheduling from "@/pages/Scheduling.vue";
import WorkcenterScheduleCard from "@/components/scheduling/WorkcenterScheduleCard.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const baseProps = {
    workcenters: [
        { id: 1, name: "Line 1" },
        { id: 2, name: "Line 2" },
    ],
    shifts: [
        { id: 9, name: "Early", start_time: "06:00", end_time: "14:00" },
        { id: 10, name: "Late", start_time: "14:00", end_time: "22:00" },
    ],
    year: 2026,
    month: 9,
    coverage: [
        { workcenter_id: 1, shift_id: 9, date: "2026-09-10", spots: 3, assigned: 3 },
        { workcenter_id: 1, shift_id: 9, date: "2026-09-11", spots: 3, assigned: 1 },
        { workcenter_id: 2, shift_id: 10, date: "2026-09-12", spots: 2, assigned: 2 },
    ],
    date: "2026-09-10",
    weekStart: "2026-09-07",
    weekPublished: false,
    publishedDays: {},
    weekCells: [
        { workcenter_id: 1, shift_id: 9, date: "2026-09-07", spots: 1, overridden: false, assignments: [] },
        { workcenter_id: 1, shift_id: 9, date: "2026-09-08", spots: 1, overridden: false, assignments: [] },
        { workcenter_id: 1, shift_id: 9, date: "2026-09-09", spots: 1, overridden: false, assignments: [] },
        {
            workcenter_id: 1,
            shift_id: 9,
            date: "2026-09-10",
            spots: 1,
            overridden: false,
            assignments: [{ id: 1, employee_id: 5, employee_name: "Anna Jansen", fixed: false }],
        },
        { workcenter_id: 1, shift_id: 9, date: "2026-09-11", spots: 0, overridden: false, assignments: [] },
        { workcenter_id: 1, shift_id: 9, date: "2026-09-12", spots: 0, overridden: false, assignments: [] },
        { workcenter_id: 1, shift_id: 9, date: "2026-09-13", spots: 0, overridden: false, assignments: [] },
    ],
};

const mountPage = (props = {}) =>
    mount(Scheduling, { props: { ...baseProps, ...props }, global: { stubs } });

function dayButton(w, day) {
    return w.findAll("button").find((b) => b.text() === String(day));
}

beforeEach(() => {
    routerGetCalls.length = 0;
    routerCalls.length = 0;
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

    it("navigates to the next month via the calendar, carrying year/month/date", async () => {
        const w = mountPage();
        await w.get('[aria-label="Next month"]').trigger("click");

        expect(routerGetCalls).toHaveLength(1);
        expect(routerGetCalls[0][0]).toBe("/scheduling");
        expect(routerGetCalls[0][1]).toEqual({ year: 2026, month: 10, date: "2026-10-01" });
    });

    it("clicking a day navigates with that day's date, same year/month", async () => {
        const w = mountPage();
        const day20 = w.findAll("button").find((b) => b.text() === "20");
        await day20.trigger("click");

        expect(routerGetCalls).toHaveLength(1);
        expect(routerGetCalls[0][1]).toEqual({ year: 2026, month: 9, date: "2026-09-20" });
    });

    it("does not navigate on initial mount", () => {
        mountPage();
        expect(routerGetCalls).toHaveLength(0);
    });

    it("shows a message pointing to the Schedule page when there are no active workcenters", () => {
        const w = mountPage({ workcenters: [], coverage: [] });
        expect(w.text()).toContain("There's no schedule yet. Add one on the Schedule page.");
    });

    it("renders a workcenter card only for a workcenter with relevant coverage that week", () => {
        const w = mountPage();
        const cards = w.findAllComponents(WorkcenterScheduleCard);

        expect(cards).toHaveLength(1);
        expect(cards[0].props("workcenter")).toEqual({ id: 1, name: "Line 1" });
        expect(w.text()).toContain("Anna Jansen");
    });

    it("unchecking the only relevant workcenter removes its schedule card", async () => {
        const w = mountPage();
        const line1Checkbox = w.findAll('input[type="checkbox"]').at(0);

        await line1Checkbox.setValue(false);

        expect(w.findAllComponents(WorkcenterScheduleCard)).toHaveLength(0);
    });

    it("shows a Publish button for an unpublished week; clicking it publishes", async () => {
        const w = mountPage({ weekPublished: false });

        expect(w.text()).toContain("Publish");
        expect(w.text()).not.toContain("Unpublish");
        expect(w.text()).not.toContain("Published");

        await w.get('[data-testid="publish-week-button"]').trigger("click");

        expect(routerCalls).toContainEqual(["post", "/scheduling/weeks/2026-09-07/publish", undefined]);
    });

    it("shows Unpublish and a Published label for a published week; clicking it unpublishes", async () => {
        const w = mountPage({ weekPublished: true });

        expect(w.text()).toContain("Unpublish");
        expect(w.text()).toContain("Published");

        await w.get('[data-testid="publish-week-button"]').trigger("click");

        expect(routerCalls).toContainEqual(["delete", "/scheduling/weeks/2026-09-07/publish"]);
    });

    it("shows the publish header even when the filter hides every workcenter card", async () => {
        const w = mountPage({ weekPublished: true });
        const line1Checkbox = w.findAll('input[type="checkbox"]').at(0);
        await line1Checkbox.setValue(false);

        expect(w.findAllComponents(WorkcenterScheduleCard)).toHaveLength(0);
        expect(w.find('[data-testid="publish-week-button"]').exists()).toBe(true);
    });

    it("passes publishedDays through to the calendar as week markers", () => {
        const w = mountPage({ publishedDays: { 10: true } });
        const day10 = w.findAll("button").find((b) => b.text() === "10");
        const row = day10.element.closest('[data-testid^="calendar-week-"]');

        expect(row.className).toContain("border-(--color-badge-custom-border)");
    });
});
