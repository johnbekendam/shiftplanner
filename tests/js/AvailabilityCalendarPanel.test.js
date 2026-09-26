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
        expect(calendar.props("dayBorders")).toEqual({ 7: "solid", 12: "dashed", 13: "dashed" });
        expect(calendar.props("legenda")).toEqual({ success: "Available", warning: "Only not preferred", error: "Not available" });
        expect(calendar.props("borderLegenda")).toEqual({ solid: "Changed", dashed: "Holiday" });
    });

    it("follows the calendar to another month", async () => {
        vi.useFakeTimers({ now: new Date(2026, 9, 20), toFake: ["Date"] });
        const w = mountCalendar();

        await w.getComponent(Calendar).vm.$emit("change", { year: 2026, month: 11, day: 1 });

        expect(w.getComponent(Calendar).props("month")).toBe(11);
    });

    it("selects a date on a day click, clears the weekday, and deselects on a second click", async () => {
        const w = mountCalendar({ selectedWeekday: 2 });
        const calendar = w.getComponent(Calendar);

        await calendar.vm.$emit("day-click", { year: 2026, month: 10, day: 5 });
        expect(w.emitted("update:selectedDate")).toEqual([["2026-10-05"]]);
        expect(w.emitted("update:selectedWeekday")).toEqual([[null]]);

        await w.setProps({ selectedWeekday: null, selectedDate: "2026-10-05" });
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

    it("selects a weekday from the header, clears the date, and deselects on a second click", async () => {
        const w = mountCalendar({ selectedDate: "2026-10-05" });
        const calendar = w.getComponent(Calendar);
        expect(calendar.props("selectedWeekday")).toBeNull();

        await calendar.vm.$emit("weekday-click", { weekday: 2 });
        expect(w.emitted("update:selectedWeekday")).toEqual([[2]]);
        expect(w.emitted("update:selectedDate")).toEqual([[null]]);

        await w.setProps({ selectedWeekday: 2, selectedDate: null });
        expect(calendar.props("selectedWeekday")).toBe(2);
        await calendar.vm.$emit("weekday-click", { weekday: 2 });
        await calendar.vm.$emit("weekday-click", { weekday: 5 });
        expect(w.emitted("update:selectedWeekday").slice(1)).toEqual([[null], [5]]);
    });
});
