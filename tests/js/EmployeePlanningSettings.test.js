import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "employees.settings.minimum_hours": "Minimum weekly hours",
    "employees.settings.minimum_hours_hint": "Leave blank to inherit :min hours.",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import EmployeePlanningSettings from "@/components/EmployeePlanningSettings.vue";
import { NumberInput } from "@/components/ui/Input";

describe("EmployeePlanningSettings", () => {
    it("edits a nullable minimum and explains the inherited value", async () => {
        const form = reactive({ weekly_hours_minimum: null });
        const w = mount(EmployeePlanningSettings, { props: { form, inheritedMinimum: 20 } });

        expect(w.findComponent(NumberInput).props()).toMatchObject({ modelValue: null, min: 1, max: 48 });
        expect(w.text()).toContain("Leave blank to inherit 20 hours.");

        w.findComponent(NumberInput).vm.$emit("update:modelValue", 28);
        await w.vm.$nextTick();
        expect(form.weekly_hours_minimum).toBe(28);
    });

});