import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "roster.title": "Roster",
    "roster.week": "Week :number",
    "roster.previous_week": "Previous week",
    "roster.next_week": "Next week",
    "roster.column.name": "Name",
    "roster.column.business_line": "Business line",
    "roster.no_business_line": "—",
    "roster.empty": "No published shifts in this week.",
    "business_line_filter.label": "Business lines",
    "business_line_filter.no_line": "No business line",
    "business_line_filter.select_all": "Select all",
    "business_line_filter.select_none": "Select none",
    "business_line_filter.aria_group": "Filter by business line",
};

const state = vi.hoisted(() => ({ user: { role: "manager" } }));
const { router } = vi.hoisted(() => ({ router: { get: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en, locale: "en", auth: { user: state.user } } }),
}));

import Roster from "@/pages/Roster.vue";

const days = ["2026-09-21", "2026-09-22", "2026-09-23", "2026-09-24", "2026-09-25", "2026-09-26", "2026-09-27"];

const rows = [
    {
        id: 1,
        name: "Anna Smit",
        business_line: "ASM",
        days: [[], [{ shift: "Early", workcenter: "Assembly" }, { shift: "Late", workcenter: "Packing" }], [], [], [], [], []],
    },
    { id: 2, name: "Bob Berg", business_line: null, days: [[], [], [{ shift: "Night", workcenter: "Assembly" }], [], [], [], []] },
];

const mountRoster = (props = {}) =>
    mount(Roster, {
        props: {
            weekStart: "2026-09-21",
            weekNumber: 39,
            today: "2026-09-23",
            days,
            rows,
            businessLines: [],
            selectedBusinessLines: ["none"],
            ...props,
        },
        global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
    });

const businessLines = [
    { id: 1, abbreviation: "ASM" },
    { id: 2, abbreviation: "PCK" },
];

beforeEach(() => {
    state.user = { role: "manager" };
    router.get.mockReset();
    window.sessionStorage.clear();
    window.history.replaceState(null, "", "/roster");
});

describe("Roster", () => {
    it("shows the week number and one column per day", () => {
        const w = mountRoster();

        expect(w.text()).toContain("Week 39");
        expect(w.findAll("thead th[data-date]").map((th) => th.attributes("data-date"))).toEqual(days);
    });

    it("shows one row per employee with the business line", () => {
        const rowEls = mountRoster().findAll("[data-testid='roster-row']");

        expect(rowEls).toHaveLength(2);
        expect(rowEls[0].text()).toContain("Anna Smit");
        expect(rowEls[0].text()).toContain("ASM");
        expect(rowEls[1].text()).toContain("Bob Berg");
        expect(rowEls[1].text()).toContain("—");
    });

    it("lists each assignment of a day with shift and workcenter", () => {
        const cell = mountRoster().get("[data-testid='roster-cell-1-2026-09-22']");
        const items = cell.findAll("[data-testid='roster-assignment']");

        expect(items).toHaveLength(2);
        expect(items[0].text()).toContain("Early");
        expect(items[0].text()).toContain("Assembly");
        expect(items[1].text()).toContain("Late");
        expect(items[1].text()).toContain("Packing");
    });

    it("shows a dash on a day with no assignment", () => {
        const cell = mountRoster().get("[data-testid='roster-cell-1-2026-09-21']");

        expect(cell.findAll("[data-testid='roster-assignment']")).toHaveLength(0);
        expect(cell.text()).toBe("—");
    });

    it("marks the column of today", () => {
        const w = mountRoster();

        expect(w.get("thead th[data-date='2026-09-23']").attributes("data-today")).toBe("true");
        expect(w.get("thead th[data-date='2026-09-22']").attributes("data-today")).toBe("false");
        expect(w.get("[data-testid='roster-cell-2-2026-09-23']").attributes("data-today")).toBe("true");
    });

    it("shows the empty state when the week has no rows", () => {
        const w = mountRoster({ rows: [] });

        expect(w.text()).toContain("No published shifts in this week.");
        expect(w.find("table").exists()).toBe(false);
    });

    it("moves one week back and forward", async () => {
        const w = mountRoster();

        await w.get("[aria-label='Previous week']").trigger("click");
        expect(router.get).toHaveBeenLastCalledWith("/roster", { week: "2026-09-14" }, expect.any(Object));

        await w.get("[aria-label='Next week']").trigger("click");
        expect(router.get).toHaveBeenLastCalledWith("/roster", { week: "2026-09-28" }, expect.any(Object));
    });

    it("keeps a week step exact across a daylight saving change", async () => {
        const w = mountRoster({ weekStart: "2026-10-19" });

        await w.get("[aria-label='Next week']").trigger("click");
        expect(router.get).toHaveBeenLastCalledWith("/roster", { week: "2026-10-26" }, expect.any(Object));
    });

    describe("business line filter", () => {
        const openMenu = (w) => w.get('[data-testid="business-lines-menu-trigger"]').trigger("click");

        it("lists the business lines plus No business line", async () => {
            const w = mountRoster({ businessLines, selectedBusinessLines: [1, 2, "none"] });
            await openMenu(w);

            expect(w.get('[data-testid="business-lines-menu"]').findAll("label").map((l) => l.text()))
                .toEqual(["ASM", "PCK", "No business line"]);
        });

        it("reloads the same week with the new selection", async () => {
            const w = mountRoster({ businessLines, selectedBusinessLines: [1, 2, "none"] });
            await openMenu(w);

            await w.get('[data-testid="business-lines-menu"]').findAll('input[type="checkbox"]')[1].setValue(false);

            expect(router.get).toHaveBeenLastCalledWith(
                "/roster",
                { week: "2026-09-21", business_lines: [1, "none"] },
                expect.objectContaining({ preserveState: true }),
            );
        });

        it("keeps the selection when the week changes", async () => {
            const w = mountRoster({ businessLines, selectedBusinessLines: [2] });

            await w.get("[aria-label='Next week']").trigger("click");

            expect(router.get).toHaveBeenLastCalledWith(
                "/roster",
                { week: "2026-09-28", business_lines: [2] },
                expect.any(Object),
            );
        });

        it("defaults to the signed-in user's business line once per tab", () => {
            state.user = { role: "manager", business_line_id: 2 };

            mountRoster({ businessLines, selectedBusinessLines: [1, 2, "none"] });

            expect(router.get).toHaveBeenCalledWith(
                "/roster",
                { week: "2026-09-21", business_lines: [2] },
                expect.objectContaining({ replace: true }),
            );
            expect(window.sessionStorage.getItem("roster.businessLineDefaultApplied")).toBe("1");
        });

        it("does not apply the default again in the same tab", () => {
            state.user = { role: "manager", business_line_id: 2 };
            window.sessionStorage.setItem("roster.businessLineDefaultApplied", "1");

            mountRoster({ businessLines, selectedBusinessLines: [1, 2, "none"] });

            expect(router.get).not.toHaveBeenCalled();
        });
    });
});
