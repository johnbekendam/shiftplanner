import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, DOMWrapper } from "@vue/test-utils";

const bodyWrapper = () => new DOMWrapper(document.body);

const en = {
    "scheduling.title": "Planning",
    "scheduling.filter_workcenters": "Workcenters",
    "scheduling.filter_shifts": "Shifts",
    "scheduling.no_schedule": "There's no schedule yet. Add one on the Schedule page.",
    "scheduling.legend_staffed": "Fully staffed",
    "scheduling.legend_open_spots": "Open spots",
    "scheduling.publish": "Publish",
    "scheduling.unpublish": "Unpublish",
    "planning.generate": "Generate Planning",
    "planning.generating": "Generating…",
    "planning.generate_again": "Generate again",
    "planning.clear": "Clear Planning",
    "planning.clear_outside_range": "Clear Planning only covers :range. Select a week in that range.",
    "planning.generate_dialog.title": "Generate Planning?",
    "planning.generate_dialog.body": "This creates or updates the draft schedule for every unpublished week in the planning period. Assignments may be moved or replaced, except any marked fixed or already published.",
    "planning.generate_dialog.period": "Period: :range",
    "planning.clear_dialog.title": "Clear Planning?",
    "planning.clear_dialog.body": "This deletes every assignment that is not fixed and not published, across the whole planning period. This cannot be undone.",
    "planning.send": "Send planning",
    "planning.send_dialog.title": "Send planning?",
    "planning.send_dialog.body": "This emails every employee who has published shifts they were not told about yet.",
    "planning.send_dialog.count": "Employees to email: :count",
    "app.cancel": "Cancel",
    "planning.generation_failed": "Generation failed: :error",
    "planning.generation_failed_more": " (+:count more)",
    "planning.change_summary.added": ":count added",
    "planning.change_summary.moved": ":count moved",
    "planning.change_summary.removed": ":count removed",
    "planning.change_summary.dismiss": "Dismiss",
    "planning.change_summary.moved_line": ":employee moved from :from to :to",
    "planning.change_summary.added_line": ":employee added to :cell",
    "planning.change_summary.removed_line": ":employee removed from :cell",
    "calendar.reset": "Jump to today",
    "calendar.prev_month": "Previous month",
    "calendar.next_month": "Next month",
};

const routerGetCalls = vi.hoisted(() => []);
const routerReloadCalls = vi.hoisted(() => []);

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
    router: {
        get: (...args) => routerGetCalls.push(args),
        post: router.post,
        delete: router.delete,
        reload: (...args) => {
            routerReloadCalls.push(args);
            args[0]?.onFinish?.();
        },
        on: () => () => {},
    },
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en, auth: { settings: { month_format: "my" } } } }),
}));

import Scheduling from "@/pages/Scheduling.vue";
import WorkcenterScheduleCard from "@/components/scheduling/WorkcenterScheduleCard.vue";
import GenerationChangeSummary from "@/components/scheduling/GenerationChangeSummary.vue";
import ConfirmDialog from "@/components/ui/ConfirmDialog.vue";
import ButtonPrimary from "@/components/ui/ButtonPrimary.vue";
import ButtonSecondary from "@/components/ui/ButtonSecondary.vue";

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
    publishedWorkcenterWeeks: [],
    cycleStart: null,
    generationRun: null,
    planningPeriod: null,
    generationStatus: null,
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

// Two ConfirmDialog instances render at once (Generate, Clear) — select by
// title rather than relying on template order.
function generateDialog(w) {
    return w.findAllComponents(ConfirmDialog).find((d) => d.props("title") === "Generate Planning?");
}

function clearDialog(w) {
    return w.findAllComponents(ConfirmDialog).find((d) => d.props("title") === "Clear Planning?");
}

function sendDialog(w) {
    return w.findAllComponents(ConfirmDialog).find((d) => d.props("title") === "Send planning?");
}

beforeEach(() => {
    routerGetCalls.length = 0;
    routerReloadCalls.length = 0;
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
        expect(routerGetCalls[0][0]).toBe("/planning");
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

    it("passes the visible workcenter card its own published state and weekStart", () => {
        const w = mountPage({ publishedWorkcenterWeeks: [{ workcenter_id: 1, week_start: "2026-09-07" }] });
        const card = w.findComponent(WorkcenterScheduleCard);

        expect(card.props("published")).toBe(true);
        expect(card.props("weekStart")).toBe("2026-09-07");
    });

    it("a card's published state is false when only a different workcenter is published that week", () => {
        const w = mountPage({ publishedWorkcenterWeeks: [{ workcenter_id: 2, week_start: "2026-09-07" }] });
        const card = w.findComponent(WorkcenterScheduleCard);

        expect(card.props("published")).toBe(false);
    });

    it("passes the viewed cycle's unfulfilled list down once the run is done", () => {
        const unfulfilled = [{ workcenter_id: 1, shift_id: 9, workcenter_name: "Line 1", shift_name: "Early", date: "2026-09-08", reason: "no_eligible_employee" }];
        const w = mountPage({ generationRun: { id: 1, status: "done", error: null, changes: [], unfulfilled } });

        expect(w.findComponent(WorkcenterScheduleCard).props("unfulfilled")).toEqual(unfulfilled);
    });

    it("does not pass an unfulfilled list down while the run is not done", () => {
        const unfulfilled = [{ workcenter_id: 1, shift_id: 9, workcenter_name: "Line 1", shift_name: "Early", date: "2026-09-08", reason: "no_eligible_employee" }];
        const w = mountPage({ generationRun: { id: 1, status: "running", error: null, changes: [], unfulfilled } });

        expect(w.findComponent(WorkcenterScheduleCard).props("unfulfilled")).toEqual([]);
    });

    it("does not show the change summary when there is no run", () => {
        const w = mountPage({ generationRun: null });
        expect(w.findComponent(GenerationChangeSummary).exists()).toBe(false);
    });

    it("does not show the change summary while the run is not done", () => {
        const w = mountPage({ generationRun: { id: 1, status: "running", error: null, changes: [], unfulfilled: [] } });
        expect(w.findComponent(GenerationChangeSummary).exists()).toBe(false);
    });

    it("does not show the change summary when the run is done but changed nothing", () => {
        const w = mountPage({ generationRun: { id: 1, status: "done", error: null, changes: [], unfulfilled: [] } });
        expect(w.findComponent(GenerationChangeSummary).exists()).toBe(false);
    });

    it("shows the change summary with the run's changes once done with changes", () => {
        const changes = [{ type: "added", employee_id: 5, employee_name: "Anna Jansen", workcenter_name: "Line 1", shift_name: "Early", date: "2026-09-08" }];
        const w = mountPage({ generationRun: { id: 7, status: "done", error: null, changes, unfulfilled: [] } });

        const summary = w.findComponent(GenerationChangeSummary);
        expect(summary.exists()).toBe(true);
        expect(summary.props("changes")).toEqual(changes);
    });

    it("clicking a workcenter card's publish button posts to that workcenter's own publish endpoint", async () => {
        const w = mountPage();

        await w.get('[data-testid="publish-workcenter-button"]').trigger("click");

        expect(routerCalls).toContainEqual(["post", "/planning/weeks/2026-09-07/workcenters/1/publish", undefined]);
    });

    // The week number cell of the calendar row that holds the given day.
    const weekNumberCell = (w, day) => w.findAll("button").find((b) => b.text() === String(day)).element
        .closest('[data-testid^="calendar-week-"]')
        .querySelector('[data-testid^="calendar-week-number-"]');

    it("marks a week's calendar row only once every relevant workcenter is published", () => {
        // Week 2026-09-07..13 has coverage from both workcenter 1 (Sep 10-11) and
        // workcenter 2 (Sep 12), so both must be published for the marker to show.
        const onlyOne = mountPage({ publishedWorkcenterWeeks: [{ workcenter_id: 1, week_start: "2026-09-07" }] });
        expect(weekNumberCell(onlyOne, 10).className).not.toContain("bg-(--color-badge-warning-bg)");

        const both = mountPage({
            publishedWorkcenterWeeks: [
                { workcenter_id: 1, week_start: "2026-09-07" },
                { workcenter_id: 2, week_start: "2026-09-07" },
            ],
        });
        expect(weekNumberCell(both, 10).className).toContain("bg-(--color-badge-warning-bg)");
    });

    it("does not mark a week with no relevant workcenter, even with an unrelated publish row", () => {
        const w = mountPage({ publishedWorkcenterWeeks: [{ workcenter_id: 1, week_start: "2026-09-21" }] });

        expect(weekNumberCell(w, 21).className).not.toContain("bg-(--color-badge-warning-bg)");
    });

    it("tells the workcenter card whether the autoplanner is allowed for the shown week", () => {
        const open = mountPage({
            publishedWorkcenterWeeks: [{ workcenter_id: 1, week_start: "2026-09-07", planner_open: true }],
        });
        const frozen = mountPage({
            publishedWorkcenterWeeks: [{ workcenter_id: 1, week_start: "2026-09-07", planner_open: false }],
        });
        const otherWeek = mountPage({
            publishedWorkcenterWeeks: [{ workcenter_id: 1, week_start: "2026-09-14", planner_open: true }],
        });

        expect(open.findComponent(WorkcenterScheduleCard).props("plannerOpen")).toBe(true);
        expect(frozen.findComponent(WorkcenterScheduleCard).props("plannerOpen")).toBe(false);
        expect(otherWeek.findComponent(WorkcenterScheduleCard).props("plannerOpen")).toBe(false);
    });

    it("hides the Generate button when the planning period is not configured", () => {
        const w = mountPage({ planningPeriod: null });
        expect(w.find('[data-testid="generate-plan-button"]').exists()).toBe(false);
    });

    it("shows a Generate Planning button that opens the confirm dialog instead of posting directly", async () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: false, failedCount: 0, firstError: null },
        });
        const button = w.get('[data-testid="generate-plan-button"]');
        expect(button.text()).toBe("Generate Planning");
        expect(button.element.disabled).toBe(false);
        expect(generateDialog(w).props("open")).toBe(false);

        await button.trigger("click");

        expect(generateDialog(w).props("open")).toBe(true);
        expect(routerCalls).toHaveLength(0);
    });

    it("shows what Generate does and the period's range in the dialog, then posts and closes on confirm", async () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: false, failedCount: 0, firstError: null },
        });
        await w.get('[data-testid="generate-plan-button"]').trigger("click");
        const dialog = generateDialog(w);

        expect(dialog.props("title")).toBe("Generate Planning?");
        expect(bodyWrapper().text()).toContain("draft schedule for every unpublished week");
        expect(bodyWrapper().text()).toContain("Period: Sep 7 – Oct 18");
        expect(dialog.props("variant")).toBe("primary");

        await dialog.vm.$emit("confirm");

        expect(routerCalls).toContainEqual(["post", "/planning/generate", undefined]);
        expect(dialog.props("open")).toBe(false);
    });

    it("closes the generate dialog without posting on cancel", async () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: false, failedCount: 0, firstError: null },
        });
        await w.get('[data-testid="generate-plan-button"]').trigger("click");
        const dialog = generateDialog(w);

        await dialog.vm.$emit("cancel");

        expect(dialog.props("open")).toBe(false);
        expect(routerCalls).toHaveLength(0);
    });

    it("shows Generate as a normal (secondary) button and Send planning as the primary one", () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            uninformedCount: 2,
        });

        const isType = (testid, component) => w.findAllComponents(component)
            .some((c) => c.attributes("data-testid") === testid);

        expect(isType("generate-plan-button", ButtonSecondary)).toBe(true);
        expect(isType("generate-plan-button", ButtonPrimary)).toBe(false);
        expect(isType("send-plan-button", ButtonPrimary)).toBe(true);
        expect(isType("send-plan-button", ButtonSecondary)).toBe(false);
    });

    it("disables Send planning when every employee has been informed", () => {
        const w = mountPage({ uninformedCount: 0 });

        expect(w.get('[data-testid="send-plan-button"]').text()).toBe("Send planning");
        expect(w.get('[data-testid="send-plan-button"]').element.disabled).toBe(true);
    });

    it("enables Send planning when there are uninformed employees, and asks to confirm before posting", async () => {
        const w = mountPage({ uninformedCount: 3 });
        const button = w.get('[data-testid="send-plan-button"]');
        expect(button.element.disabled).toBe(false);
        expect(sendDialog(w).props("open")).toBe(false);

        await button.trigger("click");

        expect(sendDialog(w).props("open")).toBe(true);
        expect(sendDialog(w).props("variant")).toBe("primary");
        expect(bodyWrapper().text()).toContain("Employees to email: 3");
        expect(routerCalls).toHaveLength(0);
    });

    it("posts to /planning/send and closes the dialog on confirm", async () => {
        const w = mountPage({ uninformedCount: 3 });
        await w.get('[data-testid="send-plan-button"]').trigger("click");
        const dialog = sendDialog(w);

        await dialog.vm.$emit("confirm");

        expect(routerCalls).toContainEqual(["post", "/planning/send", undefined]);
        expect(dialog.props("open")).toBe(false);
    });

    it("does not post when the send dialog is cancelled", async () => {
        const w = mountPage({ uninformedCount: 3 });
        await w.get('[data-testid="send-plan-button"]').trigger("click");
        const dialog = sendDialog(w);

        await dialog.vm.$emit("cancel");

        expect(dialog.props("open")).toBe(false);
        expect(routerCalls).toHaveLength(0);
    });

    it("shows Send planning even when the planning period is not configured", () => {
        const w = mountPage({ planningPeriod: null, uninformedCount: 1 });

        expect(w.find('[data-testid="generate-plan-button"]').exists()).toBe(false);
        expect(w.get('[data-testid="send-plan-button"]').element.disabled).toBe(false);
    });

    it("disables the button and shows a Generating label while any cycle in the period is active", () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: true, failedCount: 0, firstError: null },
        });
        expect(w.get('[data-testid="generate-plan-button"]').text()).toBe("Generating…");
        expect(w.get('[data-testid="generate-plan-button"]').element.disabled).toBe(true);
    });

    it("shows the error and a Generate again label once a cycle failed and nothing is still active", () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: false, failedCount: 1, firstError: "no eligible employees" },
        });
        const button = w.get('[data-testid="generate-plan-button"]');
        expect(button.text()).toBe("Generate again");
        expect(button.element.disabled).toBe(false);
        expect(w.get('[data-testid="generation-error"]').text()).toBe("Generation failed: no eligible employees");
    });

    it("mentions how many more cycles failed beyond the first", () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: false, failedCount: 3, firstError: "no eligible employees" },
        });
        expect(w.get('[data-testid="generation-error"]').text()).toBe("Generation failed: no eligible employees (+2 more)");
    });

    it("hides the error message once nothing has failed", () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: false, failedCount: 0, firstError: null },
        });
        expect(w.find('[data-testid="generation-error"]').exists()).toBe(false);
    });

    it("polls for updates while any cycle is active, and stops once none are", async () => {
        vi.useFakeTimers();
        try {
            const w = mountPage({
                planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
                generationStatus: { active: true, failedCount: 0, firstError: null },
            });

            expect(routerReloadCalls).toHaveLength(0);
            await vi.advanceTimersByTimeAsync(3000);
            expect(routerReloadCalls).toHaveLength(1);
            expect(routerReloadCalls[0][0]).toMatchObject({
                only: ["generationStatus", "weekCells", "coverage", "publishedWorkcenterWeeks", "uninformedCount"],
            });

            await vi.advanceTimersByTimeAsync(3000);
            expect(routerReloadCalls).toHaveLength(2);

            await w.setProps({ generationStatus: { active: false, failedCount: 0, firstError: null } });
            routerReloadCalls.length = 0;
            await vi.advanceTimersByTimeAsync(10000);
            expect(routerReloadCalls).toHaveLength(0);
        } finally {
            vi.useRealTimers();
        }
    });

    it("does not poll when mounted with nothing active", async () => {
        vi.useFakeTimers();
        try {
            mountPage({
                planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
                generationStatus: { active: false, failedCount: 0, firstError: null },
            });
            await vi.advanceTimersByTimeAsync(10000);
            expect(routerReloadCalls).toHaveLength(0);
        } finally {
            vi.useRealTimers();
        }
    });

    it("stops polling once the component unmounts", async () => {
        vi.useFakeTimers();
        try {
            const w = mountPage({
                planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
                generationStatus: { active: true, failedCount: 0, firstError: null },
            });
            w.unmount();
            await vi.advanceTimersByTimeAsync(10000);
            expect(routerReloadCalls).toHaveLength(0);
        } finally {
            vi.useRealTimers();
        }
    });

    it("hides the Clear Planning button when the planning period is not configured", () => {
        const w = mountPage({ planningPeriod: null });
        expect(w.find('[data-testid="clear-plan-button"]').exists()).toBe(false);
    });

    it("shows a Clear Planning button that opens the confirm dialog instead of deleting directly", async () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: false, failedCount: 0, firstError: null },
        });
        const button = w.get('[data-testid="clear-plan-button"]');
        expect(button.text()).toBe("Clear Planning");
        expect(clearDialog(w).props("open")).toBe(false);

        await button.trigger("click");

        expect(clearDialog(w).props("open")).toBe(true);
        expect(routerCalls).toHaveLength(0);
    });

    it("shows what Clear does in the dialog as a danger action, then deletes and closes on confirm", async () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: false, failedCount: 0, firstError: null },
        });
        await w.get('[data-testid="clear-plan-button"]').trigger("click");
        const dialog = clearDialog(w);

        expect(dialog.props("title")).toBe("Clear Planning?");
        expect(bodyWrapper().text()).toContain("not fixed and not published");
        expect(dialog.props("variant")).toBe("danger");

        await dialog.vm.$emit("confirm");

        expect(routerCalls).toContainEqual(["delete", "/planning/clear"]);
        expect(dialog.props("open")).toBe(false);
    });

    it("closes the clear dialog without deleting on cancel", async () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: false, failedCount: 0, firstError: null },
        });
        await w.get('[data-testid="clear-plan-button"]').trigger("click");
        const dialog = clearDialog(w);

        await dialog.vm.$emit("cancel");

        expect(dialog.props("open")).toBe(false);
        expect(routerCalls).toHaveLength(0);
    });

    const clearProps = (clearRange) => ({
        planningPeriod: { start: "2026-10-01", end: "2026-12-31" },
        generationStatus: { active: false, failedCount: 0, firstError: null },
        clearRange,
    });

    it("disables Clear Planning when the shown week is before the range it covers, and says why", async () => {
        // The shown week is 2026-09-07; Clear covers 2026-09-28 and later.
        const w = mountPage(clearProps({ start: "2026-09-28", end: "2027-01-03" }));
        const button = w.get('[data-testid="clear-plan-button"]');

        expect(button.element.disabled).toBe(true);
        expect(button.attributes("title")).toBe("Clear Planning only covers Sep 28 – Jan 3. Select a week in that range.");

        await button.trigger("click");
        expect(clearDialog(w).props("open")).toBe(false);
    });

    it("disables Clear Planning when the shown week is after the range it covers", () => {
        const w = mountPage(clearProps({ start: "2026-08-03", end: "2026-08-30" }));

        expect(w.get('[data-testid="clear-plan-button"]').element.disabled).toBe(true);
    });

    it("keeps Clear Planning enabled, without a tooltip, when the shown week is inside the range", () => {
        const w = mountPage(clearProps({ start: "2026-09-07", end: "2026-09-20" }));
        const button = w.get('[data-testid="clear-plan-button"]');

        expect(button.element.disabled).toBe(false);
        expect(button.attributes("title")).toBeUndefined();
    });

    it("disables the Clear Planning button while any cycle in the period is active", () => {
        const w = mountPage({
            planningPeriod: { start: "2026-09-07", end: "2026-10-18" },
            generationStatus: { active: true, failedCount: 0, firstError: null },
        });
        expect(w.get('[data-testid="clear-plan-button"]').element.disabled).toBe(true);
    });
});
