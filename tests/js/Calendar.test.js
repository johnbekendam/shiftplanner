import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "calendar.week_abbr": "WK",
    "calendar.reset": "Jump to today",
    "calendar.prev_month": "Previous month",
    "calendar.next_month": "Next month",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en, auth: { settings: { month_format: "my" } } } }),
}));

import Calendar from "@/components/ui/Calendar.vue";

const weekRow = (button) => button.element.closest('[data-testid^="calendar-week-"]');

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

        expect(weekRow(day10).className).toContain("border-(--color-tab-active-border)");
        expect(w.emitted("change").at(-1)[0]).toMatchObject({ day: 10 });
    });

    it("selects the whole week when a day is clicked", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });
        const day10 = w.findAll("button").find((b) => b.text() === "10");

        await day10.trigger("click");

        const week = w.findAll("button").filter((b) => /^(7|8|9|10|11|12|13)$/.test(b.text()));
        expect(week).toHaveLength(7);
        expect(new Set(week.map((b) => weekRow(b))).size).toBe(1);
        expect(weekRow(week[0]).className).toContain("border-(--color-tab-active-border)");
    });

    it("draws one border around the selected week and none around the other weeks", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });
        await w.findAll("button").find((b) => b.text() === "10").trigger("click");

        const framed = w.findAll('[data-testid^="calendar-week-"]:not([data-testid^="calendar-week-number-"])')
            .filter((row) => row.classes().join(" ").includes("border-(--color-tab-active-border)"));

        expect(framed).toHaveLength(1);
        expect(framed[0].text()).toContain("37"); // the week number is inside the frame
    });

    it("gives the days of the selected week no border of their own", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });
        await w.findAll("button").find((b) => b.text() === "10").trigger("click");

        const days = w.findAll("button").filter((b) => /^\d+$/.test(b.text()));

        expect(days.some((b) => b.classes().join(" ").includes("border-(--color-tab-active-border)"))).toBe(false);
    });

    it("gives no day a hover border of its own, the week row carries it", () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });

        const days = w.findAll("button").filter((b) => /^\d+$/.test(b.text()));

        expect(days.some((b) => b.classes().join(" ").includes("hover:border-(--color-tab-hover-border)"))).toBe(false);
    });

    it("shows a hover border on a week row that is not selected, and keeps the active border on the selected one", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });
        await w.findAll("button").find((b) => b.text() === "10").trigger("click");

        const selected = weekRow(w.findAll("button").find((b) => b.text() === "10")).className;
        const other = weekRow(w.findAll("button").find((b) => b.text() === "20")).className;

        expect(other).toContain("hover:border-(--color-tab-hover-border)");
        expect(selected).toContain("border-(--color-tab-active-border)");
        expect(selected).not.toContain("hover:border-(--color-tab-hover-border)");
    });

    // ── Week selection area ─────────────────────────────────────────────

    it("selects a week when its week number is clicked, using the first day of the row", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });

        await w.get('[data-testid="calendar-week-number-1"]').trigger("click"); // week 37: Mon 7 - Sun 13

        expect(w.emitted("change").at(-1)[0]).toMatchObject({ year: 2026, month: 9, day: 7 });
        expect(weekRow(w.findAll("button").find((b) => b.text() === "10")).className)
            .toContain("border-(--color-tab-active-border)");
    });

    it("uses the first day that is in the month when the clicked week starts in the previous month", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });

        await w.get('[data-testid="calendar-week-number-0"]').trigger("click"); // Tue 1 - Sun 6

        expect(w.emitted("change").at(-1)[0]).toMatchObject({ day: 1 });
    });

    it("skips disabled days when a week is selected from its number", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9, dateRangeStart: "2026-09-03" } });

        await w.get('[data-testid="calendar-week-number-0"]').trigger("click");

        expect(w.emitted("change").at(-1)[0]).toMatchObject({ day: 3 });
    });

    it("selects a week when the blank space in its row is clicked", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });

        await w.get('[data-testid="calendar-week-2"]').trigger("click"); // week 38: Mon 14 - Sun 20

        expect(w.emitted("change").at(-1)[0]).toMatchObject({ day: 14 });
    });

    it("still selects the clicked day itself, not the first day of the row", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });

        await w.findAll("button").find((b) => b.text() === "10").trigger("click");

        expect(w.emitted("change").at(-1)[0]).toMatchObject({ day: 10 });
        expect(w.emitted("change")).toHaveLength(2); // mount + this click, no second emit from the row
    });

    it("does not select a week from its row when enableDaySelection is false", async () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9, enableDaySelection: false } });

        await w.get('[data-testid="calendar-week-number-1"]').trigger("click");

        expect(w.emitted("change")).toHaveLength(1);
        expect(w.get('[data-testid="calendar-week-1"]').element.className).not.toContain("hover:border-(--color-tab-hover-border)");
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

    const numberCell = (w, day) => {
        const button = w.findAll("button").find((b) => b.text() === String(day));

        return weekRow(button).querySelector('[data-testid^="calendar-week-number-"]');
    };

    it("colors the week number of a marked week, using the default weekMarkerColor", () => {
        // 2026-09-10 is a Thursday, in the Mon 7 - Sun 13 row.
        const w = mount(Calendar, { props: { year: 2026, month: 9, weekMarkerDays: { 10: true } } });

        expect(numberCell(w, 10).className).toContain("bg-(--color-badge-custom-bg)");
        expect(numberCell(w, 10).className).toContain("text-(--color-badge-custom-text)");
    });

    it("does not color the week number of a week with no marked days", () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9, weekMarkerDays: { 10: true } } });

        expect(numberCell(w, 20).className).not.toContain("bg-(--color-badge-custom-bg)");
        expect(numberCell(w, 20).className).toContain("text-(--color-text-muted)");
    });

    it("supports a custom weekMarkerColor", () => {
        const w = mount(Calendar, {
            props: { year: 2026, month: 9, weekMarkerDays: { 10: true }, weekMarkerColor: "warning" },
        });

        expect(numberCell(w, 10).className).toContain("bg-(--color-badge-warning-bg)");
    });

    it("no longer draws a bar on the left of a marked week row", () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9, weekMarkerDays: { 10: true } } });
        const row = weekRow(w.findAll("button").find((b) => b.text() === "10"));

        expect(row.className).not.toContain("border-l-4");
    });

    // ── Week numbers ────────────────────────────────────────────────────

    const weekNumbers = (w) =>
        w.findAll('[data-testid^="calendar-week-number-"]').map((cell) => cell.text());

    it("shows the ISO week number in front of every week row", () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });

        expect(weekNumbers(w)).toEqual(["36", "37", "38", "39", "40"]);
    });

    it("counts a week that starts in the previous year as week 53", () => {
        // 1 Jan 2027 is a Friday, so the first row belongs to ISO week 53 of 2026.
        const w = mount(Calendar, { props: { year: 2027, month: 1 } });

        expect(weekNumbers(w)).toEqual(["53", "1", "2", "3", "4"]);
    });

    it("counts a week that ends in the next year as week 1", () => {
        // Mon 29 Dec 2025 starts ISO week 1 of 2026.
        const w = mount(Calendar, { props: { year: 2025, month: 12 } });

        expect(weekNumbers(w).at(-1)).toBe("1");
    });

    it("shows the same number for a week that crosses a month boundary in both months", () => {
        const september = mount(Calendar, { props: { year: 2026, month: 9 } });
        const october = mount(Calendar, { props: { year: 2026, month: 10 } });

        expect(weekNumbers(september).at(-1)).toBe("40");
        expect(weekNumbers(october)[0]).toBe("40");
    });

    it("shows the week number as a label, not a button", () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });
        const cell = w.get('[data-testid="calendar-week-number-0"]');

        expect(cell.element.tagName).not.toBe("BUTTON");
        expect(cell.element.closest("button")).toBeNull();
    });

    it("puts a WK label above the week numbers, in the same color as the numbers", () => {
        const w = mount(Calendar, { props: { year: 2026, month: 9 } });
        const header = w.get('[data-testid="calendar-weekday-header"]');
        const label = w.get('[data-testid="calendar-week-label"]');

        expect(header.element.children).toHaveLength(8);
        expect(header.element.children[0]).toBe(label.element);
        expect(label.text()).toBe("WK");
        expect(label.classes()).toContain("text-(--color-text-muted)");
        expect(w.get('[data-testid="calendar-week-number-0"]').classes()).toContain("text-(--color-text-muted)");
        expect(header.findAll("button")).toHaveLength(7);
    });
});
