import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "live.week": "Week :number",
    "live.not_published": "This week is not published yet.",
    "live.no_shifts": "No shifts are set up for this workcenter.",
    "live.shift": "Shift",
    "live.open": "Open",
    "live.updated": "Updated :time",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en, locale: "en", appName: "ShiftPlanner" } }),
    Head: { name: "Head", render: () => null },
}));

import Live from "@/pages/Live.vue";

const days = ["2026-09-21", "2026-09-22", "2026-09-23", "2026-09-24", "2026-09-25"];

const cell = (date, spots, names = []) => ({ date, spots, names, open: Math.max(0, spots - names.length) });

const publishedWeek = (overrides = {}) => ({
    weekStart: "2026-09-21",
    weekNumber: 39,
    published: true,
    days,
    shifts: [
        {
            id: 1, name: "Early", start_time: "06:00", end_time: "14:00",
            cells: [
                cell("2026-09-21", 2, ["Ann Able", "Bob Baker"]),
                cell("2026-09-22", 2, ["Ann Able"]),
                cell("2026-09-23", 0),
                cell("2026-09-24", 1),
                cell("2026-09-25", 0, ["Cy Cook"]),
            ],
        },
    ],
    ...overrides,
});

const unpublishedWeek = { weekStart: "2026-09-28", weekNumber: 40, published: false, days: days.map((d) => d.replace("-21", "-28")), shifts: [] };

const mountLive = (props = {}) =>
    mount(Live, {
        props: {
            workcenter: { name: "Assembly" },
            today: "2026-09-23",
            generatedAt: "2026-09-23T10:32:00.000Z",
            weeks: [publishedWeek(), unpublishedWeek],
            ...props,
        },
    });

describe("Live", () => {
    it("shows the workcenter name", () => {
        expect(mountLive().text()).toContain("Assembly");
    });

    it("shows one block for each week, with the ISO week number", () => {
        const w = mountLive();
        const blocks = w.findAll('[data-testid="live-week"]');

        expect(blocks).toHaveLength(2);
        expect(blocks[0].text()).toContain("Week 39");
        expect(blocks[1].text()).toContain("Week 40");
    });

    it("shows a not-published message and no grid for an unpublished week", () => {
        const block = mountLive().findAll('[data-testid="live-week"]')[1];

        expect(block.text()).toContain("This week is not published yet.");
        expect(block.find("table").exists()).toBe(false);
    });

    it("shows a message for a published week with no shifts", () => {
        const w = mountLive({ weeks: [publishedWeek({ shifts: [] })] });

        expect(w.text()).toContain("No shifts are set up for this workcenter.");
        expect(w.find("table").exists()).toBe(false);
    });

    it("shows a row for each shift with its times, and a column for each day", () => {
        const table = mountLive().get("table");

        expect(table.findAll("thead th")).toHaveLength(6);
        expect(table.findAll("tbody tr")).toHaveLength(1);
        expect(table.get("tbody tr").text()).toContain("Early");
        expect(table.get("tbody tr").text()).toContain("06:00 - 14:00");
    });

    it("lists the names of a cell, one for each line", () => {
        const names = mountLive().get('[data-testid="live-cell-1-2026-09-21"]').findAll('[data-testid="live-name"]');

        expect(names.map((n) => n.text())).toEqual(["Ann Able", "Bob Baker"]);
    });

    it("shows one open placeholder for each unfilled spot", () => {
        const w = mountLive();

        expect(w.get('[data-testid="live-cell-1-2026-09-22"]').findAll('[data-testid="live-open"]')).toHaveLength(1);
        expect(w.get('[data-testid="live-cell-1-2026-09-21"]').findAll('[data-testid="live-open"]')).toHaveLength(0);
    });

    it("shows a dash for a cell with no capacity and no names", () => {
        const c = mountLive().get('[data-testid="live-cell-1-2026-09-23"]');

        expect(c.text()).toBe("—");
        expect(c.find('[data-testid="live-open"]').exists()).toBe(false);
    });

    it("still shows a name on a day with no capacity", () => {
        const c = mountLive().get('[data-testid="live-cell-1-2026-09-25"]');

        expect(c.text()).toContain("Cy Cook");
        expect(c.text()).not.toContain("—");
    });

    it("highlights only the column of today", () => {
        const w = mountLive();
        const todayCells = w.findAll('[data-today="true"]');

        // The header cell plus the one shift cell.
        expect(todayCells).toHaveLength(2);
        expect(todayCells.every((c) => c.attributes("data-date") === "2026-09-23")).toBe(true);
    });

    it("highlights no column when today is outside the shown days", () => {
        expect(mountLive({ today: "2026-10-30" }).findAll('[data-today="true"]')).toHaveLength(0);
    });

    it("shows when the data was generated", () => {
        const expected = new Date("2026-09-23T10:32:00.000Z").toLocaleTimeString("en", { hour: "2-digit", minute: "2-digit" });

        expect(mountLive().get('[data-testid="live-updated"]').text()).toBe(`Updated ${expected}`);
    });
});
