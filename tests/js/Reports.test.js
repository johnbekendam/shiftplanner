import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "reports.title": "Reports",
    "reports.tab.missing_availability": "Missing availability",
    "reports.missing_availability.shift_placeholder": "All shifts",
    "reports.missing_availability.business_line_placeholder": "All business lines",
    "reports.missing_availability.include_unconfirmed": "Include unconfirmed employees",
    "reports.missing_availability.empty": "Every matching employee has set availability for this shift.",
    "reports.missing_availability.column.name": "Name",
    "reports.missing_availability.column.business_line": "Business line",
    "reports.missing_availability.column.weekly_hours": "Weekly hours",
    "reports.missing_availability.column.confirmed": "Confirmed",
    "reports.missing_availability.no_business_line": "—",
    "reports.missing_availability.confirmed.yes": "Confirmed",
    "reports.missing_availability.confirmed.no": "Unconfirmed",
    "reports.missing_availability.select_all": "Select all employees in this report",
    "reports.missing_availability.select_employee": "Select :name",
    "reports.missing_availability.email_selected": "Email selected",
};

const { router } = vi.hoisted(() => ({
    router: { get: vi.fn(), visit: vi.fn() },
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en } }),
}));

import Index from "@/pages/Reports/Index.vue";
import { SelectInput, CheckboxInput } from "@/components/ui/Input";

const employees = [
    { id: 1, name: "Ann Ant", business_line: "PMP", weekly_hours: 24, confirmed: true },
    { id: 2, name: "Bo Bee", business_line: null, weekly_hours: 40, confirmed: false },
];

const shifts = [{ id: 5, name: "Morning" }, { id: 6, name: "Evening" }];
const businessLines = [{ id: 1, abbreviation: "PMP" }];

const mountIndex = (props = {}) =>
    mount(Index, {
        props: {
            employees: [],
            shifts,
            businessLines,
            filters: { shift: null, business_line: null, unconfirmed: true },
            ...props,
        },
        global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
    });

beforeEach(() => {
    router.get.mockReset();
    router.visit.mockReset();
});

describe("Reports/Index", () => {
    it("shows the tab and the shift picker", () => {
        const w = mountIndex();

        expect(w.text()).toContain("Missing availability");
        expect(w.findComponent(SelectInput).exists()).toBe(true);
    });

    it("renders one row per employee with no shift selected (All shifts)", () => {
        const w = mountIndex({ employees, filters: { shift: null, business_line: null, unconfirmed: false } });

        expect(w.text()).toContain("Ann Ant");
        expect(w.text()).toContain("Bo Bee");
        expect(w.text()).toContain("PMP");
    });

    it("renders one row per employee once a shift is selected", () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        expect(w.text()).toContain("Ann Ant");
        expect(w.text()).toContain("Bo Bee");
        expect(w.text()).toContain("PMP");
    });

    it("shows the empty state when no employee is missing the shift", () => {
        const w = mountIndex({ employees: [], filters: { shift: 5, business_line: null, unconfirmed: false } });

        expect(w.text()).toContain("Every matching employee has set availability for this shift.");
    });

    it("reloads with the shift query param when the shift changes", async () => {
        const w = mountIndex();

        w.findComponent(SelectInput).vm.$emit("update:modelValue", 5);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith("/reports", expect.objectContaining({ shift: 5 }), expect.anything());
    });

    it("reloads with the business_line query param when the filter changes", async () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        w.findAllComponents(SelectInput)[1].vm.$emit("update:modelValue", 1);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: 5, business_line: 1 }),
            expect.anything(),
        );
    });

    it("offers a way back to no shift selected once a shift is picked", () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        expect(w.findComponent(SelectInput).props("options")).toContainEqual(
            expect.objectContaining({ value: "" }),
        );
    });

    it("clears the shift filter when the shift is reset to the blank option", async () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        w.findComponent(SelectInput).vm.$emit("update:modelValue", "");
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: undefined }),
            expect.anything(),
        );
    });

    it("offers a way back to no business-line filter once one is picked", () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: 1, unconfirmed: false } });

        expect(w.findAllComponents(SelectInput)[1].props("options")).toContainEqual(
            expect.objectContaining({ value: "" }),
        );
    });

    it("clears the business_line filter when reset to the blank option", async () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: 1, unconfirmed: false } });

        w.findAllComponents(SelectInput)[1].vm.$emit("update:modelValue", "");
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: 5, business_line: undefined }),
            expect.anything(),
        );
    });

    it("checks the include-unconfirmed toggle by default", () => {
        const w = mountIndex();

        expect(w.findComponent(CheckboxInput).props("modelValue")).toBe(true);
    });

    it("reloads with the unconfirmed query param when the toggle changes", async () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        w.findComponent(CheckboxInput).vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: 5, unconfirmed: 1 }),
            expect.anything(),
        );
    });

    it("sends an explicit unconfirmed=0 when the toggle is unchecked", async () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: null, unconfirmed: true } });

        w.findComponent(CheckboxInput).vm.$emit("update:modelValue", false);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: 5, unconfirmed: 0 }),
            expect.anything(),
        );
    });

    it("disables the email button until a row is selected", () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });
        const button = w.get('[aria-label="Email selected 0"]');

        expect(button.attributes("disabled")).toBeDefined();
    });

    it("enables the email button and shows the count once rows are checked", async () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        await w.get('[aria-label="Select Ann Ant"]').setValue(true);

        const button = w.get('[aria-label="Email selected 1"]');
        expect(button.attributes("disabled")).toBeUndefined();
        expect(button.text()).toContain("(1)");
    });

    it("select all checks every row and select all again unchecks them", async () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        await w.get('[aria-label="Select all employees in this report"]').setValue(true);

        expect(w.get('[aria-label="Select Ann Ant"]').element.checked).toBe(true);
        expect(w.get('[aria-label="Select Bo Bee"]').element.checked).toBe(true);

        await w.get('[aria-label="Select all employees in this report"]').setValue(false);

        expect(w.get('[aria-label="Select Ann Ant"]').element.checked).toBe(false);
    });

    it("navigates to compose with the selected employee ids on email", async () => {
        const w = mountIndex({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        await w.get('[aria-label="Select Ann Ant"]').setValue(true);
        await w.get('[aria-label="Select Bo Bee"]').setValue(true);
        await w.get('[aria-label="Email selected 2"]').trigger("click");

        expect(router.visit).toHaveBeenCalledWith(
            "/mailbox?tab=compose&type=custom&employee_ids[]=1&employee_ids[]=2",
        );
    });
});
