import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "availability.default_week.heading": "Default availability",
    "availability.specific.heading": "Specific availability",
    "availability.specific.hint": "Click a date to change its availability.",
    "availability.day.title": ":weekday :date",
    "availability.weekday_long.1": "Monday",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en, auth: { settings: { month_format: "my" } } } }),
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key, params) => {
        let s = en[key] ?? key;
        for (const [k, v] of Object.entries(params ?? {})) s = s.replaceAll(`:${k}`, v);
        return s;
    },
}));

import AvailabilityCalendarSection from "@/components/AvailabilityCalendarSection.vue";
import AvailabilityCalendar from "@/components/AvailabilityCalendar.vue";
import AvailabilityGrid from "@/components/AvailabilityGrid.vue";
import DateAvailabilityGrid from "@/components/DateAvailabilityGrid.vue";
import ShiftNote from "@/components/ShiftNote.vue";
import DayResetButton from "@/components/DayResetButton.vue";

const day = { id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] };

const mountSection = (props = {}) =>
    mount(AvailabilityCalendarSection, {
        props: { shifts: [day], defaults: [{ weekday: 1, shift_id: 1, level: "available" }], ...props },
    });

const selectDate = async (w, date) => {
    w.getComponent(AvailabilityCalendar).vm.$emit("update:selectedDate", date);
    await w.vm.$nextTick();
};

describe("AvailabilityCalendarSection", () => {
    it("always shows the default week in its own card above the calendar", async () => {
        const w = mountSection();
        const card = w.get('[data-testid="default-week-card"]');

        expect(card.get('[data-testid="default-week-card-header"]').text()).toBe("Default availability");
        expect(card.findComponent(AvailabilityGrid).exists()).toBe(true);
        expect(w.html().indexOf("default-week-card")).toBeLessThan(w.html().indexOf("availability-calendar-section"));

        await selectDate(w, "2026-10-05");
        expect(w.get('[data-testid="default-week-card"]').findComponent(AvailabilityGrid).exists()).toBe(true);
    });

    it("passes default grid edits up", () => {
        const w = mountSection();

        w.getComponent(AvailabilityGrid).vm.$emit("update:availability", { weekday: 1, shiftId: 1, level: "unavailable" });

        expect(w.emitted("update:availability")).toEqual([[{ weekday: 1, shiftId: 1, level: "unavailable" }]]);
    });

    it("shows the schedule note at the bottom of the default card, below a separator", () => {
        const w = mountSection({ scheduleNoteHtml: "<p>Early starts at six.</p>" });
        const html = w.get('[data-testid="default-week-card"]').html();

        expect(html.indexOf("<hr")).toBeGreaterThan(-1);
        expect(html.indexOf("<hr")).toBeLessThan(html.indexOf("Early starts at six."));
        expect(w.findAllComponents(ShiftNote)).toHaveLength(1);
        expect(w.get('[data-testid="date-card"]').text()).not.toContain("Early starts at six.");
    });

    it("shows the date hint until a date is selected, then that date's schedule", async () => {
        const w = mountSection();
        const card = () => w.get('[data-testid="date-card"]');
        expect(card().find('[data-testid="date-hint"]').exists()).toBe(true);
        expect(card().findComponent(DateAvailabilityGrid).exists()).toBe(false);

        await selectDate(w, "2026-10-05");

        expect(card().find('[data-testid="date-hint"]').exists()).toBe(false);
        expect(card().getComponent(DateAvailabilityGrid).props("day")).toMatchObject({ date: "2026-10-05" });
        expect(card().get('[data-testid="date-card-header"]').text()).toContain("Monday 05-10-2026");
    });

    it("puts the date card to the right of the calendar, stacked on narrow screens", () => {
        const w = mountSection();
        const row = w.get('[data-testid="availability-calendar-section"]');

        expect(row.classes()).toEqual(expect.arrayContaining(["flex", "flex-col", "sm:flex-row", "sm:items-start"]));
        expect(row.element.children[0].dataset.testid).not.toBe("date-card");
        expect(row.findComponent(AvailabilityCalendar).exists()).toBe(true);
        expect(row.get('[data-testid="date-card"]').classes()).toEqual(expect.arrayContaining(["min-w-0", "flex-1"]));
    });

    it("shows the Specific availability header and the date hint with no date selected", () => {
        const w = mountSection();
        const card = w.get('[data-testid="date-card"]');

        expect(card.get('[data-testid="date-card-header"]').text()).toBe("Specific availability");
        expect(card.get('[data-testid="date-hint"]').text()).toBe("Click a date to change its availability.");
    });

    it("puts Reset to default in the date card footer only for a changed date", async () => {
        const footer = (w) => w.get('[data-testid="date-card"]').find('[data-testid="date-card-footer"]');
        const w = mountSection({
            overrides: { "2026-10-05": { blocked: true, shifts: {} } },
            holidays: [{ start_date: "2026-10-07", end_date: "2026-10-07" }],
        });
        expect(footer(w).exists()).toBe(false);

        await selectDate(w, "2026-10-06");
        expect(footer(w).exists()).toBe(false);

        await selectDate(w, "2026-10-05");
        expect(footer(w).classes()).toEqual(expect.arrayContaining(["flex", "justify-end"]));
        footer(w).getComponent(DayResetButton).vm.$emit("apply-day", { date: "2026-10-05", blocked: false, shifts: {} });
        expect(w.emitted("apply-day")).toEqual([[{ date: "2026-10-05", blocked: false, shifts: {} }]]);

        await w.setProps({ disabled: true });
        expect(footer(w).exists()).toBe(false);
    });

    it("shows no footer on a holiday, even with overrides", async () => {
        const w = mountSection({
            overrides: { "2026-10-07": { blocked: true, shifts: {} } },
            holidays: [{ start_date: "2026-10-07", end_date: "2026-10-07" }],
        });

        await selectDate(w, "2026-10-07");

        expect(w.find('[data-testid="date-card-footer"]').exists()).toBe(false);
    });
});
