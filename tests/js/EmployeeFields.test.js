import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "employees.field.name": "Name",
    "employees.field.email": "Email",
    "employees.field.weekly_hours": "Weekly hours",
    "employees.hours_option": ":count hours",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import EmployeeFields from "@/components/EmployeeFields.vue";
import SelectInput from "@/components/ui/Input/Select.vue";

function makeForm(overrides = {}) {
    return reactive({
        name: "Jordan Lee",
        email: "jordan@example.com",
        weekly_hours: 32,
        errors: {},
        ...overrides,
    });
}

describe("EmployeeFields", () => {
    it("offers the eight allowed weekly-hours values with ':count hours' labels", () => {
        const w = mount(EmployeeFields, { props: { form: makeForm() } });
        const opts = w.getComponent(SelectInput).props("options");

        expect(opts.map((o) => o.value)).toEqual([
            20, 24, 28, 32, 36, 40, 44, 48,
        ]);
        expect(opts[0].label).toBe("20 hours");
        expect(opts.at(-1).label).toBe("48 hours");
    });

    it("binds the weekly-hours select to form.weekly_hours", async () => {
        const form = makeForm();
        const w = mount(EmployeeFields, { props: { form } });
        const select = w.getComponent(SelectInput);

        expect(select.props("modelValue")).toBe(32);

        select.vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();
        expect(form.weekly_hours).toBe(40);
    });

    it("leaves name and email editable by default", () => {
        const w = mount(EmployeeFields, { props: { form: makeForm() } });
        const inputs = w.findAll("input");

        expect(inputs).toHaveLength(2);
        expect(inputs.every((i) => i.attributes("disabled") === undefined)).toBe(
            true,
        );
    });

    it("disables name and email when readonlyIdentity is set", () => {
        const w = mount(EmployeeFields, {
            props: { form: makeForm(), readonlyIdentity: true },
        });
        const inputs = w.findAll("input");

        expect(inputs).toHaveLength(2);
        expect(inputs.every((i) => i.attributes("disabled") !== undefined)).toBe(
            true,
        );
    });

    it("shows the field error messages from the form", () => {
        const w = mount(EmployeeFields, {
            props: {
                form: makeForm({ errors: { weekly_hours: "Pick a valid number of hours." } }),
            },
        });

        expect(w.text()).toContain("Pick a valid number of hours.");
    });
});
