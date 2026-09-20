import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "planning.table.week": "Week",
    "planning.table.date": "Date",
    "planning.table.day": "Day",
    "planning.table.shift": "Shift",
    "planning.table.workcenter": "Workcenter",
    "planning.table.responsible": "Contact",
    "planning.details.title": "Shift details",
    "planning.details.hours": "Working hours",
    "planning.details.close": "Close",
    "planning.add_to_calendar": "Add to calendar",
    "planning.calendar_contact": "Contact",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

const { downloadIcs } = vi.hoisted(() => ({ downloadIcs: vi.fn() }));
vi.mock("@/utils/shiftIcs", async (importOriginal) => ({ ...(await importOriginal()), downloadIcs }));

import PlanningTable from "@/components/PlanningTable.vue";

const assignments = [
    {
        date: "2026-09-15", workcenter_name: "Line 2", responsible: null, shift_name: "Late",
        start_time: "14:00:00", end_time: "22:00:00",
    },
    {
        date: "2026-09-08", workcenter_name: "Line 1", responsible: "Jane Doe", shift_name: "Early",
        start_time: "06:00:00", end_time: "14:00:00",
    },
];

const mountTable = (props = {}) =>
    mount(PlanningTable, {
        props: { assignments, emptyText: "Nothing", ...props },
        global: { stubs: { teleport: true } },
    });

beforeEach(() => downloadIcs.mockClear());

describe("PlanningTable", () => {
    it("has no tooltip and no calendar column", () => {
        const w = mountTable({ calendarExport: true });

        expect(w.find("tbody tr").attributes("title")).toBeUndefined();
        expect(w.findAll("thead th")).toHaveLength(6);
        expect(w.find("tbody button").exists()).toBe(false);
    });

    it("shows no card until a row is clicked", () => {
        expect(mountTable().find('[role="dialog"]').exists()).toBe(false);
    });

    it("opens a card with all shift details when a row is clicked", async () => {
        const w = mountTable();

        await w.findAll("tbody tr")[0].trigger("click");

        const dialog = w.get('[role="dialog"]');
        expect(dialog.text()).toContain("Shift details");
        expect(dialog.text()).toContain("37");
        expect(dialog.text()).toContain("08-09-2026");
        expect(dialog.text()).toContain("Tuesday");
        expect(dialog.text()).toContain("Early");
        expect(dialog.text()).toContain("06:00–14:00");
        expect(dialog.text()).toContain("Line 1");
        expect(dialog.text()).toContain("Jane Doe");
    });

    it("opens the card from the keyboard", async () => {
        const w = mountTable();

        await w.findAll("tbody tr")[1].trigger("keydown.enter");

        expect(w.find('[role="dialog"]').exists()).toBe(true);
    });

    it("shows a dash for a missing contact in the card", async () => {
        const w = mountTable();

        await w.findAll("tbody tr")[1].trigger("click");

        expect(w.get('[data-testid="shift-detail-contact"]').text()).toBe("-");
    });

    it("closes the card with the Close button", async () => {
        const w = mountTable();
        await w.findAll("tbody tr")[0].trigger("click");

        await w.findAll('[role="dialog"] button').find((b) => b.text() === "Close").trigger("click");

        expect(w.find('[role="dialog"]').exists()).toBe(false);
    });

    it("has no Add to calendar button in the card unless calendar export is on", async () => {
        const w = mountTable();
        await w.findAll("tbody tr")[0].trigger("click");

        expect(w.findAll('[role="dialog"] button').some((b) => b.text() === "Add to calendar")).toBe(false);
    });

    it("downloads the clicked shift from the Add to calendar button in the card", async () => {
        const w = mountTable({ calendarExport: true });
        await w.findAll("tbody tr")[0].trigger("click");

        await w.findAll('[role="dialog"] button').find((b) => b.text() === "Add to calendar").trigger("click");

        expect(downloadIcs).toHaveBeenCalledTimes(1);
        const [filename, content] = downloadIcs.mock.calls[0];
        expect(filename).toBe("shift-08-09-2026.ics");
        expect(content).toContain("SUMMARY:Early – Line 1");
        expect(content).toContain("DESCRIPTION:Contact: Jane Doe");
    });
});
