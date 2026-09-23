import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

const enJson = JSON.parse(
    readFileSync(resolve(process.cwd(), "resources/lang/en.json"), "utf8"),
);

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
    "reports.tab.workcenter": "Workcenter",
    "reports.workcenter.mode.unassigned": "Unassigned",
    "reports.workcenter.mode.for_workcenter": "For workcenter",
    "reports.workcenter.workcenter_placeholder": "Select workcenter",
    "reports.workcenter.empty_selection": "Select a workcenter to show employees.",
    "reports.workcenter.empty_unassigned": "Every employee is assigned to a workcenter.",
    "reports.workcenter.empty_results": "No employees are assigned to this workcenter.",
    "reports.workcenter.column.name": "Name",
    "reports.workcenter.column.mode": "Requirement",
    "reports.workcenter.column.business_line": "Business line",
    "reports.workcenter.column.weekly_hours": "Weekly hours",
    "reports.workcenter.no_business_line": "—",
    "reports.workcenter.pagination.range": ":from-:to of :total",
    "reports.workcenter.pagination.prev": "Previous",
    "reports.workcenter.pagination.next": "Next",
    "reports.workcenter.pagination.page_label": "Page :page",
    "workcenters.mode.hard": "Requirement",
    "workcenters.mode.soft": "Preference",
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
    "reports.tab.planned_hours": "Planned hours",
    "reports.planned_hours.from": "From",
    "reports.planned_hours.to": "To",
    "reports.planned_hours.export": enJson["reports.planned_hours.export"],
    "reports.planned_hours.export_all": enJson["reports.planned_hours.export_all"],
    "reports.planned_hours.empty": "No published planned hours in this range.",
    "reports.planned_hours.column.workcenter": "Workcenter",
    "reports.planned_hours.column.date": "Date",
    "reports.planned_hours.column.hours": "Hours",
    "employees.no_email": "No email",
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
import { SelectInput, CheckboxInput, DateInput } from "@/components/ui/Input";

const employees = [
    { id: 1, name: "Ann Ant", has_email: true, business_line: "PMP", weekly_hours: 24, confirmed: true },
    { id: 2, name: "Bo Bee", has_email: true, business_line: null, weekly_hours: 40, confirmed: false },
];

const shifts = [{ id: 5, name: "Morning" }, { id: 6, name: "Evening" }];
const businessLines = [{ id: 1, abbreviation: "PMP" }];
const competences = [{ id: 10, name: "Forklift" }, { id: 11, name: "First aid" }];
const competenceReport = [
    { id: 1, name: "Ann Ant", competence_names: ["First aid"] },
    { id: 2, name: "Bo Bee", competence_names: ["Forklift", "First aid"] },
];
const workcenters = [{ id: 20, name: "Assembly A" }, { id: 21, name: "Paint Booth" }];

// Mirrors the shape ReportController now returns for both workcenter-report tables.
const paginate = (data, overrides = {}) => ({
    data,
    links: [],
    from: data.length ? 1 : null,
    to: data.length,
    total: data.length,
    last_page: 1,
    ...overrides,
});

const unassignedWorkcenterReport = paginate([
    { id: 1, name: "Ann Ant", business_line: "PMP", weekly_hours: 24 },
    { id: 2, name: "Bo Bee", business_line: null, weekly_hours: 40 },
]);
const workcenterReport = paginate([
    { id: 1, name: "Ann Ant", mode: "hard", business_line: "PMP", weekly_hours: 24 },
    { id: 2, name: "Bo Bee", mode: "soft", business_line: null, weekly_hours: 40 },
]);

const mountIndex = (props = {}) =>
    mount(Index, {
        props: {
            employees: [],
            shifts,
            businessLines,
            competences,
            competenceReport: [],
            workcenters,
            unassignedWorkcenterReport: paginate([]),
            workcenterReport: paginate([]),
            plannedHoursReport: paginate([]),
            uninformedPlanning: [],
            filters: {
                shift: null,
                business_line: null,
                unconfirmed: true,
                planning_business_line: null,
                competence_mode: "missing",
                competence_id: null,
                workcenter_mode: "unassigned",
                workcenter_id: null,
                workcenter_sort: "name",
                workcenter_direction: "asc",
                planned_hours_from: "2026-09-21",
                planned_hours_to: "2026-09-27",
                planned_hours_sort: "date",
                planned_hours_direction: "asc",
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
        const tabLabels = w.findAll("button").slice(0, 5).map((button) => button.text());

        expect(tabLabels).toEqual([
            "Competences", "Workcenter", "Uninformed planning", "Missing availability", "Planned hours",
        ]);
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

    it("marks an employee without an email and leaves the row out of the selection", async () => {
        const rows = employees.map((row, index) => ({ ...row, has_email: index === 0 }));
        const w = await openMissingAvailabilityTab({ employees: rows, filters: { shift: null, business_line: null, unconfirmed: false } });

        const [first, second] = w.findAll("tbody tr");
        expect(first.text()).not.toContain("No email");
        expect(second.text()).toContain("No email");
        expect(w.get('[aria-label="Select Bo Bee"]').attributes("disabled")).toBeDefined();

        await w.get('[aria-label="Select all employees in this report"]').setValue(true);

        expect(w.get('[aria-label="Select Ann Ant"]').element.checked).toBe(true);
        expect(w.get('[aria-label="Select Bo Bee"]').element.checked).toBe(false);

        await w.get('[aria-label="Email selected 1"]').trigger("click");
        expect(router.visit).toHaveBeenCalledWith("/mailbox?tab=compose&type=custom&employee_ids[]=1");
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

    // ── Workcenter report tab ────────────────────────────────────────────

    const openWorkcenterTab = async (props = {}) => {
        const w = mountIndex(props);
        await w.findAll("button").find((button) => button.text() === "Workcenter").trigger("click");

        return w;
    };

    it("defaults to Unassigned mode and lists unassigned employees", async () => {
        const w = await openWorkcenterTab({ unassignedWorkcenterReport });

        expect(w.findComponent(SelectInput).props("modelValue")).toBe("unassigned");
        expect(w.text()).toContain("Ann Ant");
        expect(w.text()).toContain("Bo Bee");
        expect(w.findAllComponents(SelectInput)).toHaveLength(1);

        const rows = w.findAll("tbody tr");
        expect(rows[0].text()).toContain("PMP");
        expect(rows[0].text()).toContain("24");
        expect(rows[1].text()).toContain("—");
        expect(rows[1].text()).toContain("40");
    });

    it("shows the empty state when every employee is assigned", async () => {
        const w = await openWorkcenterTab({ unassignedWorkcenterReport: paginate([]) });

        expect(w.text()).toContain("Every employee is assigned to a workcenter.");
    });

    it("opens the employee workcenters tab when an unassigned row is clicked", async () => {
        const w = await openWorkcenterTab({ unassignedWorkcenterReport });

        await w.find("tbody tr").trigger("click");

        expect(router.visit).toHaveBeenCalledWith("/employees/1/edit?tab=workcenters");
    });

    it("reveals the workcenter picker in For workcenter mode and reloads", async () => {
        const w = await openWorkcenterTab();

        w.findComponent(SelectInput).vm.$emit("update:modelValue", "for_workcenter");
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ workcenter_mode: "for_workcenter" }),
            expect.anything(),
        );
    });

    it("shows an empty prompt in For workcenter mode before a workcenter is picked", async () => {
        const w = await openWorkcenterTab({
            filters: { shift: null, business_line: null, unconfirmed: true, competence_mode: "missing", competence_id: null, workcenter_mode: "for_workcenter", workcenter_id: null },
        });

        expect(w.text()).toContain("Select a workcenter to show employees.");
    });

    it("reloads with the workcenter query param when a workcenter is picked", async () => {
        const w = await openWorkcenterTab({
            filters: { shift: null, business_line: null, unconfirmed: true, competence_mode: "missing", competence_id: null, workcenter_mode: "for_workcenter", workcenter_id: null },
        });

        w.findAllComponents(SelectInput)[1].vm.$emit("update:modelValue", 20);
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ workcenter_mode: "for_workcenter", workcenter: 20 }),
            expect.anything(),
        );
    });

    it("lists workcenter report rows with their mode and opens the employee workcenters tab", async () => {
        const w = await openWorkcenterTab({
            workcenterReport,
            filters: { shift: null, business_line: null, unconfirmed: true, competence_mode: "missing", competence_id: null, workcenter_mode: "for_workcenter", workcenter_id: 20 },
        });

        expect(w.text()).toContain("Ann Ant");
        expect(w.text()).toContain("Requirement");
        expect(w.text()).toContain("Bo Bee");
        expect(w.text()).toContain("Preference");

        const rows = w.findAll("tbody tr");
        expect(rows[0].text()).toContain("PMP");
        expect(rows[0].text()).toContain("24");
        expect(rows[1].text()).toContain("—");
        expect(rows[1].text()).toContain("40");

        await w.find("tbody tr").trigger("click");

        expect(router.visit).toHaveBeenCalledWith("/employees/1/edit?tab=workcenters");
    });

    it("shows empty results when the picked workcenter has no employees", async () => {
        const w = await openWorkcenterTab({
            workcenterReport: paginate([]),
            filters: { shift: null, business_line: null, unconfirmed: true, competence_mode: "missing", competence_id: null, workcenter_mode: "for_workcenter", workcenter_id: 20 },
        });

        expect(w.text()).toContain("No employees are assigned to this workcenter.");
    });

    it("reloads with the sort and direction when a column header is clicked", async () => {
        const w = await openWorkcenterTab({ unassignedWorkcenterReport });

        const header = w.findAll("thead button").find((b) => b.text() === "Weekly hours");
        await header.trigger("click");

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ workcenter_sort: "weekly_hours", workcenter_direction: "asc" }),
            expect.anything(),
        );
    });

    it("flips to descending when the same column header is clicked again", async () => {
        const w = await openWorkcenterTab({
            unassignedWorkcenterReport,
            filters: {
                shift: null, business_line: null, unconfirmed: true, competence_mode: "missing", competence_id: null,
                workcenter_mode: "unassigned", workcenter_id: null, workcenter_sort: "weekly_hours", workcenter_direction: "asc",
            },
        });

        const header = w.findAll("thead button").find((b) => b.text().includes("Weekly hours"));
        await header.trigger("click");

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ workcenter_sort: "weekly_hours", workcenter_direction: "desc" }),
            expect.anything(),
        );
    });

    it("shows the pagination footer when the report has more than one page", async () => {
        const w = await openWorkcenterTab({
            unassignedWorkcenterReport: paginate(
                [{ id: 1, name: "Ann Ant", business_line: "PMP", weekly_hours: 24 }],
                {
                    from: 1, to: 15, total: 16, last_page: 2,
                    links: [
                        { url: null, label: "&laquo; Previous", active: false },
                        { url: "/reports?workcenter_page=1", label: "1", active: true },
                        { url: "/reports?workcenter_page=2", label: "2", active: false },
                        { url: "/reports?workcenter_page=2", label: "Next &raquo;", active: false },
                    ],
                },
            ),
        });

        expect(w.find('[data-testid="report-pagination"]').exists()).toBe(true);
        expect(w.text()).toContain("1-15 of 16");

        const pageTwo = w.findAll('[data-testid="report-pagination"] button').find((b) => b.text() === "2");
        await pageTwo.trigger("click");

        expect(router.get).toHaveBeenCalledWith("/reports?workcenter_page=2", {}, expect.anything());
    });

    it("hides the pagination footer when the report fits on one page", async () => {
        const w = await openWorkcenterTab({ unassignedWorkcenterReport });

        expect(w.find('[data-testid="report-pagination"]').exists()).toBe(false);
    });

    // ── Uninformed planning tab ─────────────────────────────────────────

    const uninformedPlanning = [
        { id: 1, name: "Ann Ant", has_email: true, business_line: "PMP", uninformed_count: 2, first_date: "2026-09-22" },
        { id: 2, name: "Bo Bee", has_email: true, business_line: null, uninformed_count: 1, first_date: "2026-09-24" },
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

    it("marks an employee without an email and leaves the row out of the selection", async () => {
        const rows = uninformedPlanning.map((row, index) => ({ ...row, has_email: index === 0 }));
        const w = await openPlanningTab({ uninformedPlanning: rows });

        const [first, second] = w.findAll("tbody tr");
        expect(first.text()).not.toContain("No email");
        expect(second.text()).toContain("No email");
        expect(w.get('[aria-label="Select Bo Bee"]').attributes("disabled")).toBeDefined();

        await w.get('[aria-label="Select all employees with uninformed planning"]').setValue(true);

        expect(w.get('[aria-label="Select Ann Ant"]').element.checked).toBe(true);
        expect(w.get('[aria-label="Select Bo Bee"]').element.checked).toBe(false);

        await w.get('[aria-label="Email planning to selected 1"]').trigger("click");
        expect(router.visit).toHaveBeenCalledWith("/mailbox?tab=compose&type=planning&employee_ids[]=1");
    });

    it("opens Compose with the Planning type and the selected employees", async () => {
        const w = await openPlanningTab();
        await w.get('[aria-label="Select all employees with uninformed planning"]').setValue(true);

        await w.get('[aria-label="Email planning to selected 2"]').trigger("click");

        expect(router.visit).toHaveBeenCalledWith("/mailbox?tab=compose&type=planning&employee_ids[]=1&employee_ids[]=2");
    });

    // ── Planned hours report tab ─────────────────────────────────────────

    const openPlannedHoursTab = async (props = {}) => {
        const w = mountIndex(props);
        await w.findAll("button").find((b) => b.text() === "Planned hours").trigger("click");

        return w;
    };

    it("renders rows from the plannedHoursReport prop", async () => {
        const w = await openPlannedHoursTab({
            plannedHoursReport: paginate([{ workcenter: "Assembly A", date: "2026-09-22", hours: 8 }]),
        });

        const row = w.get("tbody tr");
        expect(row.text()).toContain("Assembly A");
        expect(row.text()).toContain("2026-09-22");
        expect(row.text()).toContain("8");
    });

    it("shows the empty state when the range has no planned hours", async () => {
        const w = await openPlannedHoursTab({ plannedHoursReport: paginate([]) });

        expect(w.text()).toContain("No published planned hours in this range.");
    });

    it("reloads with the new planned_hours_from param when the From date changes", async () => {
        const w = await openPlannedHoursTab();

        w.findAllComponents(DateInput)[0].vm.$emit("update:modelValue", "2026-09-01");
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ planned_hours_from: "2026-09-01", planned_hours_to: "2026-09-27" }),
            expect.anything(),
        );
    });

    it("reloads with the new planned_hours_to param when the To date changes", async () => {
        const w = await openPlannedHoursTab();

        w.findAllComponents(DateInput)[1].vm.$emit("update:modelValue", "2026-10-05");
        await w.vm.$nextTick();

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ planned_hours_from: "2026-09-21", planned_hours_to: "2026-10-05" }),
            expect.anything(),
        );
    });

    it("sorts by the clicked column, then flips direction on a second click", async () => {
        const w = await openPlannedHoursTab({
            plannedHoursReport: paginate([{ workcenter: "Assembly A", date: "2026-09-22", hours: 8 }]),
        });

        const hoursHeader = w.findAll("th button").find((b) => b.text().includes("Hours"));
        await hoursHeader.trigger("click");

        expect(router.get).toHaveBeenCalledWith(
            "/reports",
            expect.objectContaining({ planned_hours_sort: "hours", planned_hours_direction: "asc" }),
            expect.anything(),
        );

        await hoursHeader.trigger("click");

        expect(router.get).toHaveBeenLastCalledWith(
            "/reports",
            expect.objectContaining({ planned_hours_sort: "hours", planned_hours_direction: "desc" }),
            expect.anything(),
        );
    });

    it("points the Export Excel link at the export route with the current date range", async () => {
        const w = await openPlannedHoursTab();

        const link = w.get('a[href^="/reports/planned-hours/export"]');
        expect(link.text()).toBe("Export Excel");
        expect(link.attributes("href")).toBe(
            "/reports/planned-hours/export?planned_hours_from=2026-09-21&planned_hours_to=2026-09-27",
        );
    });

    it("points the Export all to Excel link at the export route without a date range", async () => {
        const w = await openPlannedHoursTab();

        const link = w.get('a[href="/reports/planned-hours/export?all=1"]');
        expect(link.text()).toBe("Export all to Excel");
        expect(link.classes()).toContain("bg-[var(--color-btn-secondary-bg)]");
    });

    it("shows the pagination footer only when there is more than one page", async () => {
        const w = await openPlannedHoursTab({
            plannedHoursReport: paginate([{ workcenter: "Assembly A", date: "2026-09-22", hours: 8 }], {
                from: 1, to: 15, total: 16, last_page: 2,
                links: [
                    { url: null, label: "&laquo; Previous", active: false },
                    { url: "/reports?planned_hours_page=1", label: "1", active: true },
                    { url: "/reports?planned_hours_page=2", label: "2", active: false },
                    { url: "/reports?planned_hours_page=2", label: "Next &raquo;", active: false },
                ],
            }),
        });

        expect(w.find('[data-testid="report-pagination"]').exists()).toBe(true);

        const pageTwo = w.findAll('[data-testid="report-pagination"] button').find((b) => b.text() === "2");
        await pageTwo.trigger("click");

        expect(router.get).toHaveBeenCalledWith("/reports?planned_hours_page=2", {}, expect.anything());
    });
});
