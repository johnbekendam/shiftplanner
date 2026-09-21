import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "reports.title": "Reports",
    "reports.tab.missing_availability": "Missing availability",
    "reports.tab.competences": "Competences",
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
    "reports.competences.mode.missing": "Missing selected competence",
    "reports.competences.mode.has": "Has selected competence",
    "reports.competences.competence_placeholder": "Select competence",
    "reports.competences.empty_selection": "Select a competence to show employees.",
    "reports.competences.empty_results": "No employees match these competences.",
    "reports.competences.column.name": "Name",
    "reports.competences.column.competences": "Competences",
    "reports.competences.none_configured": "No competences configured.",
    "reports.tab.uninformed_planning": "Uninformed planning",
    "reports.uninformed_planning.business_line_placeholder": "All business lines",
    "reports.uninformed_planning.empty": "Every employee with planning has been informed.",
    "reports.uninformed_planning.column.name": "Name",
    "reports.uninformed_planning.column.business_line": "Business line",
    "reports.uninformed_planning.column.count": "Uninformed shifts",
    "reports.uninformed_planning.column.first_date": "First shift",
    "reports.uninformed_planning.no_business_line": "—",
    "reports.uninformed_planning.select_all": "Select all employees with uninformed planning",
    "reports.uninformed_planning.select_employee": "Select :name",
    "reports.uninformed_planning.email_selected": "Email planning to selected",
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
const competences = [{ id: 10, name: "Forklift" }, { id: 11, name: "First aid" }];
const competenceReport = [
    { id: 1, name: "Ann Ant", competence_names: ["First aid"] },
    { id: 2, name: "Bo Bee", competence_names: ["Forklift", "First aid"] },
];

const mountIndex = (props = {}) =>
    mount(Index, {
        props: {
            employees: [],
            shifts,
            businessLines,
            competences,
            competenceReport: [],
            uninformedPlanning: [],
            filters: {
                shift: null,
                business_line: null,
                unconfirmed: true,
                planning_business_line: null,
                competence_mode: "missing",
                competence_id: null,
            },
            ...props,
        },
        global: { stubs: { AppLayout: { template: "<div><slot /></div>" } } },
    });

beforeEach(() => {
    router.get.mockReset();
    router.visit.mockReset();
});

describe("Reports/Index", () => {
    const openMissingAvailabilityTab = async (props = {}) => {
        const w = mountIndex(props);
        await w.findAll("button").find((button) => button.text() === "Missing availability").trigger("click");

        return w;
    };

    it("orders the tabs and defaults to Competences", () => {
        const w = mountIndex();
        const tabLabels = w.findAll("button").slice(0, 3).map((button) => button.text());

        expect(tabLabels).toEqual(["Competences", "Uninformed planning", "Missing availability"]);
        expect(w.text()).toContain("Select a competence to show employees.");
    });

    it("renders one row per employee with no shift selected (All shifts)", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: null, business_line: null, unconfirmed: false } });

        expect(w.text()).toContain("Ann Ant");
        expect(w.text()).toContain("Bo Bee");
        expect(w.text()).toContain("PMP");
    });

    it("renders one row per employee once a shift is selected", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        expect(w.text()).toContain("Ann Ant");
        expect(w.text()).toContain("Bo Bee");
        expect(w.text()).toContain("PMP");
    });

    it("shows the empty state when no employee is missing the shift", async () => {
        const w = await openMissingAvailabilityTab({ employees: [], filters: { shift: 5, business_line: null, unconfirmed: false } });

        expect(w.text()).toContain("Every matching employee has set availability for this shift.");
    });

    it("reloads with the shift query param when the shift changes", async () => {
        const w = await openMissingAvailabilityTab();

        w.findComponent(SelectInput).vm.$emit("update:modelValue", 5);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith("/reports", expect.objectContaining({ shift: 5 }), expect.anything());
    });

    it("reloads with the business_line query param when the filter changes", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        w.findAllComponents(SelectInput)[1].vm.$emit("update:modelValue", 1);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: 5, business_line: 1 }),
            expect.anything(),
        );
    });

    it("offers a way back to no shift selected once a shift is picked", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        expect(w.findComponent(SelectInput).props("options")).toContainEqual(
            expect.objectContaining({ value: "" }),
        );
    });

    it("clears the shift filter when the shift is reset to the blank option", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        w.findComponent(SelectInput).vm.$emit("update:modelValue", "");
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: undefined }),
            expect.anything(),
        );
    });

    it("offers a way back to no business-line filter once one is picked", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: 1, unconfirmed: false } });

        expect(w.findAllComponents(SelectInput)[1].props("options")).toContainEqual(
            expect.objectContaining({ value: "" }),
        );
    });

    it("clears the business_line filter when reset to the blank option", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: 1, unconfirmed: false } });

        w.findAllComponents(SelectInput)[1].vm.$emit("update:modelValue", "");
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: 5, business_line: undefined }),
            expect.anything(),
        );
    });

    it("checks the include-unconfirmed toggle by default", async () => {
        const w = await openMissingAvailabilityTab();

        expect(w.findComponent(CheckboxInput).props("modelValue")).toBe(true);
    });

    it("reloads with the unconfirmed query param when the toggle changes", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        w.findComponent(CheckboxInput).vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: 5, unconfirmed: 1 }),
            expect.anything(),
        );
    });

    it("sends an explicit unconfirmed=0 when the toggle is unchecked", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: null, unconfirmed: true } });

        w.findComponent(CheckboxInput).vm.$emit("update:modelValue", false);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: 5, unconfirmed: 0 }),
            expect.anything(),
        );
    });

    it("disables the email button until a row is selected", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });
        const button = w.get('[aria-label="Email selected 0"]');

        expect(button.attributes("disabled")).toBeDefined();
    });

    it("enables the email button and shows the count once rows are checked", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        await w.get('[aria-label="Select Ann Ant"]').setValue(true);

        const button = w.get('[aria-label="Email selected 1"]');
        expect(button.attributes("disabled")).toBeUndefined();
        expect(button.text()).toContain("(1)");
    });

    it("select all checks every row and select all again unchecks them", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        await w.get('[aria-label="Select all employees in this report"]').setValue(true);

        expect(w.get('[aria-label="Select Ann Ant"]').element.checked).toBe(true);
        expect(w.get('[aria-label="Select Bo Bee"]').element.checked).toBe(true);

        await w.get('[aria-label="Select all employees in this report"]').setValue(false);

        expect(w.get('[aria-label="Select Ann Ant"]').element.checked).toBe(false);
    });

    it("navigates to compose with the selected employee ids on email", async () => {
        const w = await openMissingAvailabilityTab({ employees, filters: { shift: 5, business_line: null, unconfirmed: false } });

        await w.get('[aria-label="Select Ann Ant"]').setValue(true);
        await w.get('[aria-label="Select Bo Bee"]').setValue(true);
        await w.get('[aria-label="Email selected 2"]').trigger("click");

        expect(router.visit).toHaveBeenCalledWith(
            "/mailbox?tab=compose&type=custom&employee_ids[]=1&employee_ids[]=2",
        );
    });

    // ── Competence report tab ──────────────────────────────────────────

    const openCompetenceTab = async (props = {}) => {
        const w = mountIndex(props);
        await w.findAll("button").find((button) => button.text() === "Competences").trigger("click");

        return w;
    };

    it("has a Competences tab with an empty prompt before competences are selected", async () => {
        const w = await openCompetenceTab();

        expect(w.text()).toContain("Competences");
        expect(w.text()).toContain("Select a competence to show employees.");
        expect(w.findAllComponents(SelectInput)).toHaveLength(2);
        expect(w.findAllComponents(SelectInput)[1].props("options")).toContainEqual(
            expect.objectContaining({ value: "", label: "Select competence" }),
        );
    });

    it("reloads when the competence mode changes", async () => {
        const w = await openCompetenceTab({
            filters: { shift: 5, business_line: null, unconfirmed: false, planning_business_line: null, competence_mode: "missing", competence_id: 10 },
        });

        w.findComponent(SelectInput).vm.$emit("update:modelValue", "has");
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ competence_mode: "has", competence: 10 }),
            expect.anything(),
        );
    });

    it("reloads when the selected competence changes", async () => {
        const w = await openCompetenceTab();

        w.findAllComponents(SelectInput)[1].vm.$emit("update:modelValue", 11);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ competence_mode: "missing", competence: 11 }),
            expect.anything(),
        );
    });

    it("lists competence report rows and opens the employee competences tab", async () => {
        const w = await openCompetenceTab({
            competenceReport,
            filters: { shift: null, business_line: null, unconfirmed: true, planning_business_line: null, competence_mode: "missing", competence_id: 10 },
        });

        expect(w.text()).toContain("Ann Ant");
        expect(w.text()).toContain("First aid");

        await w.find("tbody tr").trigger("click");

        expect(router.visit).toHaveBeenCalledWith("/employees/1/edit?tab=competences");
    });

    // ── Uninformed planning tab ─────────────────────────────────────────

    const uninformedPlanning = [
        { id: 1, name: "Ann Ant", business_line: "PMP", uninformed_count: 2, first_date: "2026-09-22" },
        { id: 2, name: "Bo Bee", business_line: null, uninformed_count: 1, first_date: "2026-09-24" },
    ];

    const openPlanningTab = async (props = {}) => {
        const w = mountIndex({ uninformedPlanning, ...props });
        await w.findAll("button").find((b) => b.text() === "Uninformed planning").trigger("click");

        return w;
    };

    it("has an Uninformed planning tab next to Missing availability", () => {
        const w = mountIndex();

        expect(w.text()).toContain("Missing availability");
        expect(w.text()).toContain("Uninformed planning");
    });

    it("lists each employee with the count and the first shift date as dd-mm-yyyy", async () => {
        const w = await openPlanningTab();

        const rows = w.findAll("tbody tr").map((tr) => tr.findAll("td").slice(1).map((td) => td.text()));
        expect(rows).toEqual([
            ["Ann Ant", "PMP", "2", "22-09-2026"],
            ["Bo Bee", "—", "1", "24-09-2026"],
        ]);
        expect(w.text()).not.toContain("Include unconfirmed employees");
    });

    it("shows the empty state when everyone is informed", async () => {
        const w = await openPlanningTab({ uninformedPlanning: [] });

        expect(w.text()).toContain("Every employee with planning has been informed.");
    });

    it("reloads with planning_business_line and keeps the other filters", async () => {
        const w = await openPlanningTab({
            filters: { shift: 5, business_line: null, unconfirmed: false, planning_business_line: null },
        });

        w.findComponent(SelectInput).vm.$emit("update:modelValue", 1);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ shift: 5, unconfirmed: 0, planning_business_line: 1 }),
            expect.anything(),
        );
    });

    it("disables the email button until a row is selected, then shows the count", async () => {
        const w = await openPlanningTab();
        expect(w.get('[aria-label="Email planning to selected 0"]').attributes("disabled")).toBeDefined();

        await w.get('[aria-label="Select Ann Ant"]').setValue(true);

        const button = w.get('[aria-label="Email planning to selected 1"]');
        expect(button.attributes("disabled")).toBeUndefined();
        expect(button.text()).toContain("(1)");
    });

    it("selects every row with select all", async () => {
        const w = await openPlanningTab();

        await w.get('[aria-label="Select all employees with uninformed planning"]').setValue(true);

        expect(w.get('[aria-label="Select Ann Ant"]').element.checked).toBe(true);
        expect(w.get('[aria-label="Select Bo Bee"]').element.checked).toBe(true);
    });

    it("opens Compose with the Planning type and the selected employees", async () => {
        const w = await openPlanningTab();
        await w.get('[aria-label="Select all employees with uninformed planning"]').setValue(true);

        await w.get('[aria-label="Email planning to selected 2"]').trigger("click");

        expect(router.visit).toHaveBeenCalledWith("/mailbox?tab=compose&type=planning&employee_ids[]=1&employee_ids[]=2");
    });
});
