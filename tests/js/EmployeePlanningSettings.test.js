import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "employees.settings.minimum_hours": "Minimum weekly hours",
    "employees.settings.minimum_hours_hint": "Leave blank to inherit :min hours.",
    "employees.settings.shift_visibility": "Shift visibility",
    "employees.settings.inherit_visible": "Inherit (visible)",
    "employees.settings.inherit_hidden": "Inherit (hidden)",
    "employees.settings.visible": "Visible",
    "employees.settings.hidden": "Hidden",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import EmployeePlanningSettings from "@/components/EmployeePlanningSettings.vue";
import { NumberInput, SelectInput } from "@/components/ui/Input";

const shifts = [
    { id: 1, name: "Early", visible_by_default: true, visibility_override: null, effective_visible: true },
    { id: 2, name: "Night", visible_by_default: false, visibility_override: false, effective_visible: false },
];

describe("EmployeePlanningSettings", () => {
    it("edits a nullable minimum and explains the inherited value", async () => {
        const form = reactive({ weekly_hours_minimum: null, shift_visibility: [] });
        const w = mount(EmployeePlanningSettings, { props: { form, shifts, inheritedMinimum: 20 } });

        expect(w.findComponent(NumberInput).props()).toMatchObject({ modelValue: null, min: 1, max: 48 });
        expect(w.text()).toContain("Leave blank to inherit 20 hours.");

        w.findComponent(NumberInput).vm.$emit("update:modelValue", 28);
        await w.vm.$nextTick();
        expect(form.weekly_hours_minimum).toBe(28);
    });

    it("offers inherit, visible, and hidden states for every shift", async () => {
        const form = reactive({
            weekly_hours_minimum: null,
            shift_visibility: shifts.map((shift) => ({
                shift_id: shift.id,
                override: shift.visibility_override,
            })),
        });
        const w = mount(EmployeePlanningSettings, { props: { form, shifts, inheritedMinimum: 20 } });
        const selects = w.findAllComponents(SelectInput);

        expect(selects).toHaveLength(2);
        expect(selects[0].props("options")).toEqual([
            { value: null, label: "Inherit (visible)" },
            { value: true, label: "Visible" },
            { value: false, label: "Hidden" },
        ]);

        selects[0].vm.$emit("update:modelValue", false);
        await w.vm.$nextTick();
        expect(form.shift_visibility[0]).toEqual({ shift_id: 1, override: false });
    });
});