import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "schedule.title": "Schedule",
    "schedule.week": "Week :number",
    "schedule.previous_week": "Previous week",
    "schedule.next_week": "Next week",
    "schedule.column.name": "Name",
    "schedule.column.business_line": "Business line",
    "schedule.no_business_line": "—",
    "schedule.empty": "No published shifts in this week.",
    "business_line_filter.label": "Business lines",
    "business_line_filter.no_line": "No business line",
    "business_line_filter.select_all": "Select all",
    "business_line_filter.select_none": "Select none",
    "business_line_filter.aria_group": "Filter by business line",
};

const { router } = vi.hoisted(() => ({ router: { get: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    usePage: () => ({
        props: { translations: en, locale: "en", auth: { user: null } },
        url: "/schedule/secret-token?week=2026-09-21",
    }),
}));

import SchedulePublic from "@/pages/SchedulePublic.vue";

const days = ["2026-09-21", "2026-09-22", "2026-09-23", "2026-09-24", "2026-09-25", "2026-09-26", "2026-09-27"];
const businessLines = [
    { id: 1, abbreviation: "ASM" },
    { id: 2, abbreviation: "PCK" },
];

const mountPage = (props = {}) =>
    mount(SchedulePublic, {
        props: {
            weekStart: "2026-09-21",
            weekNumber: 39,
            today: "2026-09-23",
            days,
            rows: [{ id: 1, name: "Anna Smit", business_line: "ASM", days: [[], [{ shift: "Early", workcenter: "Assembly" }], [], [], [], [], []] }],
            businessLines,
            selectedBusinessLines: [1, 2, "none"],
            ...props,
        },
        global: { stubs: { AppLogo: true } },
    });

beforeEach(() => {
    router.get.mockReset();
    window.sessionStorage.clear();
});

describe("SchedulePublic", () => {
    it("shows the schedule without the app sidebar", () => {
        const w = mountPage();

        expect(w.find("header h1").text()).toBe("Schedule");
        expect(w.find("nav").exists()).toBe(false);
        expect(w.get("[data-testid='schedule-cell-1-2026-09-22']").text()).toContain("Early");
    });

    it("moves between weeks on the secret link", async () => {
        const w = mountPage();

        await w.get("[aria-label='Next week']").trigger("click");

        expect(router.get).toHaveBeenLastCalledWith("/schedule/secret-token", { week: "2026-09-28" }, expect.any(Object));
    });

    it("filters by business line on the secret link", async () => {
        const w = mountPage();
        await w.get('[data-testid="business-lines-menu-trigger"]').trigger("click");

        await w.get('[data-testid="business-lines-menu"]').findAll('input[type="checkbox"]')[0].setValue(false);

        expect(router.get).toHaveBeenLastCalledWith(
            "/schedule/secret-token",
            { week: "2026-09-21", business_lines: [2, "none"] },
            expect.any(Object),
        );
    });
});
