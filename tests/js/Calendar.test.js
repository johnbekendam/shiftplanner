import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "calendar.reset": "Jump to today",
    "calendar.prev_month": "Previous month",
    "calendar.next_month": "Next month",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en, auth: { settings: { month_format: "my" } } } }),
}));

import Calendar from "@/components/ui/Calendar.vue";

describe("Calendar", () => {
    it("renders the month title and a button per day of the month", () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });

        expect(w.text()).toContain("September 2026");
        // September 2026 has 30 days.
        expect(w.findAll("button").filter((b) => /^\d+$/.test(b.text()))).toHaveLength(30);
    });

    it("emits change with the year/month/day on mount", () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9, initialDay: 15 } });

        const events = w.emitted("change");
        expect(events).toBeTruthy();
        expect(events[events.length - 1][0]).toMatchObject({ year: 2026, month: 9, day: 15 });
    });

    it("selecting a day updates the selection and emits change", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });
        const day10 = w.findAll("button").find((b) => b.text() === "10");

        await day10.trigger("click");

        expect(day10.classes().join(" ")).toContain("border-(--color-tab-active-border)");
        expect(w.emitted("change").at(-1)[0]).toMatchObject({ day: 10 });
    });

    it("selects the whole week when a day is clicked", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });
        const day10 = w.findAll("button").find((b) => b.text() === "10");

        await day10.trigger("click");

        expect(w.findAll("button").filter((b) => /^(7|8|9|10|11|12|13)$/.test(b.text())).every((b) =>
            b.classes().join(" ").includes("border-(--color-tab-active-border)"),
        )).toBe(true);
    });

    it("does not select a day when enableDaySelection is false", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9, enableDaySelection: false } });
        const day10 = w.findAll("button").find((b) => b.text() === "10");

        await day10.trigger("click");

        expect(w.emitted("change")).toHaveLength(1); // only the initial mount emit
    });

    it("disables days outside the given date range", () => {
        const w = mount(Calendar, {
            props: { year: 2026, month: 9, dateRangeStart: "2026-09-05", dateRangeEnd: "2026-09-20" },
        });
        const day1 = w.findAll("button").find((b) => b.text() === "1");
        const day10 = w.findAll("button").find((b) => b.text() === "10");
        const day25 = w.findAll("button").find((b) => b.text() === "25");

        expect(day1.attributes("disabled")).toBeDefined();
        expect(day10.attributes("disabled")).toBeUndefined();
        expect(day25.attributes("disabled")).toBeDefined();
    });

    it("applies a dayStates color as a badge token class", () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9, dayStates: { 12: "success" } } });
        const day12 = w.findAll("button").find((b) => b.text() === "12");

        expect(day12.classes().join(" ")).toContain("bg-(--color-badge-success-bg)");
    });

    it("navigating to the next month rolls over into the next year", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 12 } });

        await w.get('[aria-label="Next month"]').trigger("click");
        expect(w.emitted("change").at(-1)[0]).toMatchObject({ year: 2027, month: 1 });
    });

    it("navigating to the previous month rolls back into the previous year", async () => {
        const w = mount(Calendar, { props: { year: 2027, month: 1 } });

        await w.get('[aria-label="Previous month"]').trigger("click");
        expect(w.emitted("change").at(-1)[0]).toMatchObject({ year: 2026, month: 12 });
    });

    it("renders a legend entry per non-empty legenda color", () => {
        const w = mount(Calendar, {
            props: { year: 2026, month: 9, legenda: { success: "Open", error: "" } },
        });

        expect(w.text()).toContain("Open");
    });

    it("marks the row of a week with a marked day, using the default weekMarkerColor", () => {
        // 2026-09-10 is a Thursday, in the Mon 7 - Sun 13 row.
        const w = mount(Calendar, { props: { year: 2026, month: 9, weekMarkerDays: { 10: true } } });
        const day10 = w.findAll("button").find((b) => b.text() === "10");
        const row = day10.element.closest('[data-testid^="calendar-week-"]');

        expect(row).not.toBeNull();
        expect(row.className).toContain("border-(--color-badge-custom-border)");
    });

    it("does not mark a week with no marked days", () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9, weekMarkerDays: { 10: true } } });
        const day20 = w.findAll("button").find((b) => b.text() === "20");
        const row = day20.element.closest('[data-testid^="calendar-week-"]');

        expect(row.className).not.toContain("border-(--color-badge-custom-border)");
    });

    it("supports a custom weekMarkerColor", () => {
        const w = mount(Calendar, {
            props: { year: 2026, month: 9, weekMarkerDays: { 10: true }, weekMarkerColor: "warning" },
        });
        const day10 = w.findAll("button").find((b) => b.text() === "10");
        const row = day10.element.closest('[data-testid^="calendar-week-"]');

        expect(row.className).toContain("border-(--color-btn-danger-bg)");
    });
});
