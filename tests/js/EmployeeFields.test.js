import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "employees.field.first_name": "First name",
    "employees.field.last_name": "Last name",
    "employees.field.email": "Email",
    "employees.field.business_line": "Business line",
    "employees.field.business_line_none": "None",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import EmployeeFields from "@/components/EmployeeFields.vue";
import LabeledInput from "@/components/LabeledInput.vue";
import SelectInput from "@/components/ui/Input/Select.vue";

function makeForm(overrides = {}) {
    return reactive({
        first_name: "Jordan",
        last_name: "Lee",
        email: "jordan@example.com",
        business_line_id: null,
        errors: {},
        ...overrides,
    });
}

describe("EmployeeFields", () => {
    it("orders the fields: first/last name, email, business line", () => {
        const w = mount(EmployeeFields, {
            props: { form: makeForm(), businessLines: [{ id: 5, abbreviation: "PMP" }] },
        });
        const labels = w.findAllComponents(LabeledInput).map((l) => l.props("label"));

        expect(labels).toEqual(["First name", "Last name", "Email", "Business line"]);
    });

    it("puts first and last name on one row", () => {
        const w = mount(EmployeeFields, { props: { form: makeForm() } });
        const row = w.get('[data-testid="name-row"]');

        expect(row.findAll("input")).toHaveLength(2);
        expect(row.text()).toContain("First name");
        expect(row.text()).toContain("Last name");
    });

    it("has no weekly-hours field", () => {
        const w = mount(EmployeeFields, { props: { form: makeForm() } });
        expect(w.text()).not.toContain("Weekly hours");
    });

    it("shows first name, last name and email as three inputs", () => {
        const w = mount(EmployeeFields, { props: { form: makeForm() } });
        expect(w.findAll("input")).toHaveLength(3);
    });

    it("leaves first name, last name and email editable by default", () => {
        const w = mount(EmployeeFields, { props: { form: makeForm() } });
        const inputs = w.findAll("input");

        expect(inputs).toHaveLength(3);
        expect(inputs.every((i) => i.attributes("disabled") === undefined)).toBe(true);
    });

    it("disables the identity fields when readonlyIdentity is set", () => {
        const w = mount(EmployeeFields, {
            props: { form: makeForm(), readonlyIdentity: true },
        });
        const inputs = w.findAll("input");

        expect(inputs).toHaveLength(3);
        expect(inputs.every((i) => i.attributes("disabled") !== undefined)).toBe(true);
    });

    it("keeps the business line editable under readonlyIdentity", () => {
        const w = mount(EmployeeFields, {
            props: {
                form: makeForm(),
                readonlyIdentity: true,
                businessLines: [{ id: 5, abbreviation: "PMP" }],
            },
        });

        expect(w.getComponent(SelectInput).props("disabled")).toBe(false);
    });

    it("disables the business line when disabled is set", () => {
        const w = mount(EmployeeFields, {
            props: {
                form: makeForm(),
                disabled: true,
                businessLines: [{ id: 5, abbreviation: "PMP" }],
            },
        });

        expect(w.getComponent(SelectInput).props("disabled")).toBe(true);
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
        expect(w.findAllComponents(SelectInput)).toHaveLength(0);
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

        const select = w.getComponent(SelectInput);
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
            props: { form: makeForm({ errors: { email: "That email is taken." } }) },
        });

        expect(w.text()).toContain("That email is taken.");
    });
});
