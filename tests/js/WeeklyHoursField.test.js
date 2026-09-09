import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "employees.field.weekly_hours": "Weekly hours",
    "employees.hours_option": ":count hours",
    "employees.hours_below_minimum": "I can only work less than :min hours",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import WeeklyHoursField from "@/components/WeeklyHoursField.vue";
import SelectInput from "@/components/ui/Input/Select.vue";

describe("WeeklyHoursField", () => {
    it("offers 20-48 in steps of 4 then a below-minimum option that saves 0", () => {
        const w = mount(WeeklyHoursField, { props: { modelValue: 32 } });
        const opts = w.getComponent(SelectInput).props("options");

        expect(opts.map((o) => o.value)).toEqual([20, 24, 28, 32, 36, 40, 44, 48, 0]);
        expect(opts[0].label).toBe("20 hours");
        expect(opts[7].label).toBe("48 hours");
        expect(opts.at(-1)).toEqual({
            value: 0,
            label: "I can only work less than 20 hours",
        });
    });

    it("binds the select to modelValue and emits on change", async () => {
        const w = mount(WeeklyHoursField, { props: { modelValue: 32 } });
        const select = w.getComponent(SelectInput);

        expect(select.props("modelValue")).toBe(32);

        select.vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        expect(w.emitted("update:modelValue")).toBeTruthy();
        expect(w.emitted("update:modelValue")[0]).toEqual([40]);
    });

    it("passes disabled through", () => {
        const w = mount(WeeklyHoursField, { props: { modelValue: 32, disabled: true } });
        expect(w.getComponent(SelectInput).props("disabled")).toBe(true);
    });

    it("shows an error message", () => {
        const w = mount(WeeklyHoursField, {
            props: { modelValue: 32, error: "Pick a valid number of hours." },
        });
        expect(w.text()).toContain("Pick a valid number of hours.");
    });
});
