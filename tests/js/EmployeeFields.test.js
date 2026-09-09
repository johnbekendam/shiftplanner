import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "employees.field.first_name": "First name",
    "employees.field.last_name": "Last name",
    "employees.field.email": "Email",
    "employees.field.weekly_hours": "Weekly hours",
    "employees.field.business_line": "Business line",
    "employees.field.business_line_none": "None",
    "employees.hours_option": ":count hours",
    "employees.hours_below_minimum": "I can only work less than :min hours",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import EmployeeFields from "@/components/EmployeeFields.vue";
import SelectInput from "@/components/ui/Input/Select.vue";

function makeForm(overrides = {}) {
    return reactive({
        first_name: "Jordan",
        last_name: "Lee",
        email: "jordan@example.com",
        weekly_hours: 32,
        errors: {},
        ...overrides,
    });
}

describe("EmployeeFields", () => {
    it("offers 20-48 hours then a below-minimum option that saves 0", () => {
        const w = mount(EmployeeFields, { props: { form: makeForm() } });
        const opts = w.getComponent(SelectInput).props("options");

        expect(opts.map((o) => o.value)).toEqual([
            20, 24, 28, 32, 36, 40, 44, 48, 0,
        ]);
        expect(opts[0].label).toBe("20 hours");
        expect(opts[7].label).toBe("48 hours");
        expect(opts.at(-1)).toEqual({
            value: 0,
            label: "I can only work less than 20 hours",
        });
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

    it("shows first name, last name and email fields", () => {
        const w = mount(EmployeeFields, { props: { form: makeForm() } });

        expect(w.text()).toContain("First name");
        expect(w.text()).toContain("Last name");
        expect(w.findAll("input")).toHaveLength(3);
    });

    it("leaves first name, last name and email editable by default", () => {
        const w = mount(EmployeeFields, { props: { form: makeForm() } });
        const inputs = w.findAll("input");

        expect(inputs).toHaveLength(3);
        expect(inputs.every((i) => i.attributes("disabled") === undefined)).toBe(
            true,
        );
    });

    it("disables the identity fields when readonlyIdentity is set", () => {
        const w = mount(EmployeeFields, {
            props: { form: makeForm(), readonlyIdentity: true },
        });
        const inputs = w.findAll("input");

        expect(inputs).toHaveLength(3);
        expect(inputs.every((i) => i.attributes("disabled") !== undefined)).toBe(
            true,
        );
    });

    it("seeds the identity inputs from the form and shows their errors", () => {
        const form = makeForm({ errors: { first_name: "The first name is required." } });
        const w = mount(EmployeeFields, { props: { form } });
        const [first, last] = w.findAll("input");

        expect(first.element.value).toBe("Jordan");
        expect(last.element.value).toBe("Lee");
        expect(w.text()).toContain("The first name is required.");
    });

    it("hides the business-line select when no lines are given", () => {
        const w = mount(EmployeeFields, { props: { form: makeForm() } });
        expect(w.text()).not.toContain("Business line");
        expect(w.findAllComponents(SelectInput)).toHaveLength(1);
    });

    it("offers a None option then one per business line, bound to form.business_line_id", async () => {
        const form = makeForm({ business_line_id: 5 });
        const w = mount(EmployeeFields, {
            props: {
                form,
                businessLines: [
                    { id: 5, abbreviation: "PMP" },
                    { id: 8, abbreviation: "VLV" },
                ],
            },
        });

        const select = w.findAllComponents(SelectInput)[1];
        expect(select.props("options")).toEqual([
            { value: null, label: "None" },
            { value: 5, label: "PMP" },
            { value: 8, label: "VLV" },
        ]);
        expect(select.props("modelValue")).toBe(5);

        select.vm.$emit("update:modelValue", null);
        await w.vm.$nextTick();
        expect(form.business_line_id).toBe(null);
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
