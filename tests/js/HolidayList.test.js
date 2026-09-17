import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "availability.holidays.start": "Start",
    "availability.holidays.end": "End",
    "availability.holidays.note": "Note",
    "availability.holidays.empty": "No holidays.",
    "availability.holidays.add": "Add",
    "availability.holidays.delete": "Remove",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import HolidayList from "@/components/HolidayList.vue";
import { DateInput } from "@/components/ui/Input";

const holidays = [
    { id: 1, start_date: "2026-06-01", end_date: "2026-06-05", note: "Early" },
    { id: 2, start_date: "2026-09-01", end_date: "2026-09-03", note: null },
];

const mountList = (props = {}) => mount(HolidayList, { props: { holidays, ...props } });

describe("HolidayList", () => {
    it("renders a row per holiday", () => {
        const w = mountList();
        expect(w.findAll('[data-testid="holiday-row"]')).toHaveLength(2);
        expect(w.text()).toContain("01-06-2026");
        expect(w.text()).not.toContain("2026-06-01");
        expect(w.text()).toContain("Early");
    });

    it("shows an empty state when there are no holidays", () => {
        const w = mountList({ holidays: [] });
        expect(w.text()).toContain("No holidays.");
    });

    it("keeps the add inputs as a row inside the same table", () => {
        const w = mountList();
        const addRow = w.get('[data-testid="holiday-add-row"]');

        expect(addRow.element.tagName).toBe("TR");
        expect(addRow.findAllComponents(DateInput)).toHaveLength(2);
        expect(w.findAll("table")).toHaveLength(1);
    });

    it("enables the add button only after both dates are set", async () => {
        const w = mountList({ holidays: [] });
        const button = w.get('[aria-label="Add"]');
        const dates = w.findAllComponents(DateInput);

        expect(button.attributes("disabled")).toBeDefined();

        dates[0].vm.$emit("update:modelValue", "2026-07-01");
        await w.vm.$nextTick();
        expect(button.attributes("disabled")).toBeDefined();

        dates[1].vm.$emit("update:modelValue", "2026-07-14");
        await w.vm.$nextTick();
        expect(button.attributes("disabled")).toBeUndefined();
    });

    it("adds the new holiday to the list locally and emits update:holidays, without a network call", async () => {
        const w = mountList({ holidays: [] });
        const dates = w.findAllComponents(DateInput);
        dates[0].vm.$emit("update:modelValue", "2026-07-01");
        dates[1].vm.$emit("update:modelValue", "2026-07-14");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(w.findAll('[data-testid="holiday-row"]')).toHaveLength(1);
        expect(w.text()).toContain("01-07-2026");
        const emitted = w.emitted("update:holidays");
        expect(emitted).toHaveLength(1);
        expect(emitted[0][0]).toMatchObject([{ id: null, start_date: "2026-07-01", end_date: "2026-07-14" }]);
    });

    it("removes a row locally and emits update:holidays, without a network call", async () => {
        const w = mountList();
        await w.findAll('[data-testid="holiday-row"]')[0].get("button").trigger("click");

        expect(w.findAll('[data-testid="holiday-row"]')).toHaveLength(1);
        const emitted = w.emitted("update:holidays");
        expect(emitted).toHaveLength(1);
        expect(emitted[0][0]).toEqual([holidays[1]]);
    });

    it("hides the add row and the delete buttons when disabled", () => {
        const w = mountList({ disabled: true });

        expect(w.find('[data-testid="holiday-add-row"]').exists()).toBe(false);
        expect(w.find('[data-testid="holiday-row"] button').exists()).toBe(false);
        // The rows themselves still show.
        expect(w.findAll('[data-testid="holiday-row"]')).toHaveLength(2);
    });
});
