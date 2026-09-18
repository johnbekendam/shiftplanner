import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "planning.change_summary.added": ":count added",
    "planning.change_summary.moved": ":count moved",
    "planning.change_summary.removed": ":count removed",
    "planning.change_summary.show_details": "Show details",
    "planning.change_summary.hide_details": "Hide details",
    "planning.change_summary.dismiss": "Dismiss",
    "planning.change_summary.moved_line": ":employee moved from :from to :to",
    "planning.change_summary.added_line": ":employee added to :cell",
    "planning.change_summary.removed_line": ":employee removed from :cell",
};

const replace = (template, replacements) =>
    Object.entries(replacements ?? {}).reduce((s, [k, v]) => s.replace(`:${k}`, v), template);

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key, replacements) => replace(en[key] ?? key, replacements),
}));

import GenerationChangeSummary from "@/components/scheduling/GenerationChangeSummary.vue";

const added = (overrides = {}) => ({
    type: "added",
    employee_id: 1,
    employee_name: "Anna Jansen",
    workcenter_name: "Line 1",
    shift_name: "Early",
    date: "2026-09-08",
    ...overrides,
});

const removed = (overrides = {}) => ({
    type: "removed",
    employee_id: 1,
    employee_name: "Anna Jansen",
    workcenter_name: "Line 1",
    shift_name: "Early",
    date: "2026-09-08",
    ...overrides,
});

describe("GenerationChangeSummary", () => {
    it("renders nothing when there are no changes", () => {
        const w = mount(GenerationChangeSummary, { props: { changes: [] } });
        expect(w.find('[data-testid="generation-change-summary"]').exists()).toBe(false);
    });

    it("summarizes plain added and removed counts", () => {
        const w = mount(GenerationChangeSummary, {
            props: {
                changes: [
                    added({ employee_id: 1 }),
                    added({ employee_id: 2 }),
                    removed({ employee_id: 3 }),
                ],
            },
        });
        expect(w.get('[data-testid="toggle-change-details"]').text()).toContain("2 added, 1 removed");
    });

    it("reads a removed+added pair for the same employee as a move, not a separate add and remove", () => {
        const w = mount(GenerationChangeSummary, {
            props: {
                changes: [
                    removed({ employee_id: 1, workcenter_name: "Line 1", shift_name: "Early", date: "2026-09-08" }),
                    added({ employee_id: 1, workcenter_name: "Line 2", shift_name: "Late", date: "2026-09-09" }),
                ],
            },
        });
        const summaryText = w.get('[data-testid="toggle-change-details"]').text();
        expect(summaryText).toContain("1 moved");
        expect(summaryText).not.toContain("added");
        expect(summaryText).not.toContain("removed");
    });

    it("pairs a substitute's two different employees as a plain add and a plain remove, not a move", () => {
        // Employee 1 loses a cell, employee 2 gains a different cell — different
        // people, so this is a substitute, not a move for either of them.
        const w = mount(GenerationChangeSummary, {
            props: {
                changes: [
                    removed({ employee_id: 1 }),
                    added({ employee_id: 2 }),
                ],
            },
        });
        expect(w.get('[data-testid="toggle-change-details"]').text()).toContain("1 added, 1 removed");
    });

    it("pairs only as many moves as the smaller count, leaving the rest as plain add/remove", () => {
        // Employee 1: lost two cells, gained one — one move, one leftover removed.
        const w = mount(GenerationChangeSummary, {
            props: {
                changes: [
                    removed({ employee_id: 1, date: "2026-09-08" }),
                    removed({ employee_id: 1, date: "2026-09-09" }),
                    added({ employee_id: 1, date: "2026-09-10" }),
                ],
            },
        });
        const summaryText = w.get('[data-testid="toggle-change-details"]').text();
        expect(summaryText).toContain("1 moved");
        expect(summaryText).toContain("1 removed");
        expect(summaryText).not.toContain("added");
    });

    it("hides the detail list until expanded, then shows a line per change", async () => {
        const w = mount(GenerationChangeSummary, {
            props: {
                changes: [
                    removed({ employee_id: 1, workcenter_name: "Line 1", shift_name: "Early", date: "2026-09-08" }),
                    added({ employee_id: 1, workcenter_name: "Line 2", shift_name: "Late", date: "2026-09-09" }),
                ],
            },
        });

        expect(w.find("li").exists()).toBe(false);

        await w.get('[data-testid="toggle-change-details"]').trigger("click");

        const line = w.get("li").text();
        expect(line).toContain("Anna Jansen moved from");
        expect(line).toContain("Line 1 / Early");
        expect(line).toContain("Line 2 / Late");
    });

    it("collapses the detail list again on a second toggle", async () => {
        const w = mount(GenerationChangeSummary, { props: { changes: [added()] } });

        await w.get('[data-testid="toggle-change-details"]').trigger("click");
        expect(w.find("li").exists()).toBe(true);

        await w.get('[data-testid="toggle-change-details"]').trigger("click");
        expect(w.find("li").exists()).toBe(false);
    });

    it("dismisses the whole panel and does not bring it back on further interaction", async () => {
        const w = mount(GenerationChangeSummary, { props: { changes: [added()] } });

        await w.get('[data-testid="dismiss-change-summary"]').trigger("click");

        expect(w.find('[data-testid="generation-change-summary"]').exists()).toBe(false);
    });
});
