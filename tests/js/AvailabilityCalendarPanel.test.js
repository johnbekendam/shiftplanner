import { describe, it, expect, vi, afterEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "availability.calendar.legend.available": "Available",
    "availability.calendar.legend.not_preferred": "Only not preferred",
    "availability.calendar.legend.unavailable": "Not available",
    "availability.calendar.legend.changed": "Changed",
    "availability.calendar.legend.holiday": "Holiday",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en, auth: { settings: { month_format: "my" } } } }),
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import AvailabilityCalendar from "@/components/AvailabilityCalendar.vue";
import Calendar from "@/components/ui/Calendar.vue";
import CalendarLegend from "@/components/ui/CalendarLegend.vue";

const early = { id: 10, name: "Early", start_time: "06:00", end_time: "14:00", weekdays: [1, 2, 3, 4, 5] };

const mountCalendar = (props = {}) =>
    mount(AvailabilityCalendar, {
        props: {
            shifts: [early],
            defaults: [{ weekday: 1, shift_id: 10, level: "available" }],
            overrides: { "2026-10-07": { blocked: true, shifts: {} } },
            holidays: [{ start_date: "2026-10-12", end_date: "2026-10-13" }],
            availableFrom: "2026-10-02",
            ...props,
        },
    });

afterEach(() => vi.useRealTimers());

describe("AvailabilityCalendar", () => {
    it("opens on the current month and colors each day from the availability", () => {
        vi.useFakeTimers({ now: new Date(2026, 9, 20), toFake: ["Date"] });
        const w = mountCalendar();
        const calendar = w.getComponent(Calendar);

        expect(calendar.props()).toMatchObject({ year: 2026, month: 10, dateRangeStart: "2026-10-02", highlightSelection: false });
        expect(calendar.props("dayStates")[5]).toBe("success");
        expect(calendar.props("dayStates")[6]).toBe("error");
        expect(calendar.props("dayStates")[12]).toBe("custom");
        expect(calendar.props("dayBorders")).toEqual({ 7: "solid" });
    });

    it("shows the legend in the calendar footer, without an info card", () => {
        const w = mountCalendar();
        const calendar = w.getComponent(Calendar);

        expect(calendar.props("legenda")).toEqual({ success: "Available", warning: "Only not preferred", error: "Not available", custom: "Holiday" });
        expect(calendar.props("borderLegenda")).toEqual({ solid: "Changed" });
        expect(calendar.getComponent(CalendarLegend).props("vertical")).toBe(false);
        expect(w.find('[data-testid="availability-info-card"]').exists()).toBe(false);
    });

    it("follows the calendar to another month", async () => {
        vi.useFakeTimers({ now: new Date(2026, 9, 20), toFake: ["Date"] });
        const w = mountCalendar();

        await w.getComponent(Calendar).vm.$emit("change", { year: 2026, month: 11, day: 1 });

        expect(w.getComponent(Calendar).props("month")).toBe(11);
    });

    it("selects a date on a day click and deselects on a second click", async () => {
        const w = mountCalendar();
        const calendar = w.getComponent(Calendar);

        await calendar.vm.$emit("day-click", { year: 2026, month: 10, day: 5 });
        expect(w.emitted("update:selectedDate")).toEqual([["2026-10-05"]]);

        await w.setProps({ selectedDate: "2026-10-05" });
        await calendar.vm.$emit("day-click", { year: 2026, month: 10, day: 5 });
        expect(w.emitted("update:selectedDate").at(-1)).toEqual([null]);
    });

    it("rings the selected date only in its own month", async () => {
        const w = mountCalendar({ selectedDate: "2026-11-03" });
        const calendar = w.getComponent(Calendar);

        await calendar.vm.$emit("change", { year: 2026, month: 10, day: 1 });
        expect(calendar.props("ringDay")).toBeNull();
        await calendar.vm.$emit("change", { year: 2026, month: 11, day: 1 });
        expect(calendar.props("ringDay")).toBe(3);
    });

    it("shows the weekday letters as plain labels", () => {
        const w = mountCalendar();
        const calendar = w.getComponent(Calendar);

        expect(calendar.props("enableWeekDaySelection")).toBe(false);
        expect(calendar.props("weekdayHeaderSelected")).toBeUndefined();
    });

    it("marks the current week and today", () => {
        expect(mountCalendar().getComponent(Calendar).props("boldCurrent")).toBe(true);
    });

    it("sizes the calendar to its content instead of stretching", () => {
        const w = mountCalendar();

        expect(w.getComponent(Calendar).classes()).toContain("w-fit");
    });
});
