import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "employees.field.weekly_hours": "Weekly hours",
    "employees.weekly_hours_below_minimum_warning": "Weekly hours are below the required minimum of :min.",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import WeeklyHoursField from "@/components/WeeklyHoursField.vue";
import NumberInput from "@/components/ui/Input/Number.vue";

describe("WeeklyHoursField", () => {
    it("uses a whole-number input from zero through forty-eight", () => {
        const w = mount(WeeklyHoursField, { props: { modelValue: 32, minimum: 20 } });
        const input = w.getComponent(NumberInput);

        expect(input.props()).toMatchObject({ modelValue: 32, min: 0, max: 48, step: 1 });
    });

    it("binds the number input to modelValue and emits on change", async () => {
        const w = mount(WeeklyHoursField, { props: { modelValue: 0 } });
        const input = w.getComponent(NumberInput);

        expect(input.props("modelValue")).toBe(0);

        input.vm.$emit("update:modelValue", 37);
        await w.vm.$nextTick();

        expect(w.emitted("update:modelValue")).toBeTruthy();
        expect(w.emitted("update:modelValue")[0]).toEqual([37]);
    });

    it("warns for a positive value below the effective minimum", () => {
        const w = mount(WeeklyHoursField, { props: { modelValue: 19, minimum: 20 } });

        expect(w.get('[data-testid="weekly-hours-minimum-warning"]').text())
            .toBe("Weekly hours are below the required minimum of 20.");
    });

    it("does not warn for zero or the minimum", () => {
        expect(mount(WeeklyHoursField, { props: { modelValue: 0, minimum: 20 } })
            .find('[data-testid="weekly-hours-minimum-warning"]').exists()).toBe(false);
        expect(mount(WeeklyHoursField, { props: { modelValue: 20, minimum: 20 } })
            .find('[data-testid="weekly-hours-minimum-warning"]').exists()).toBe(false);
    });

    it("passes disabled through", () => {
        const w = mount(WeeklyHoursField, { props: { modelValue: 32, disabled: true } });
        expect(w.getComponent(NumberInput).props("disabled")).toBe(true);
    });

    it("shows an error message", () => {
        const w = mount(WeeklyHoursField, {
            props: { modelValue: 32, error: "Pick a valid number of hours." },
        });
        expect(w.text()).toContain("Pick a valid number of hours.");
    });
});
