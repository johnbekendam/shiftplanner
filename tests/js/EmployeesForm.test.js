import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "availability.tab.information": "Information",
    "availability.tab.details": "Details",
    "availability.tab.availability": "Availability",
    "availability.tab.settings": "Settings",
    "availability.info.empty": "No information has been provided yet.",
    "availability.info.cta": "Please update your details, availability and competences on the different tabs.",
    "availability.hours_warning.not_preferred": "You will be planned on not-preferred hours.",
    "availability.hours_warning.insufficient": "Your available time totals :available hours per week, below your target of :target hours.",
    "availability.holidays.empty": "No holidays yet.",
    "availability.questions.heading": "Questions",
    "competences.tab": "Competences",
    "competences.checklist_empty": "No competences have been set up yet.",
    "workcenters.employee_tab": "Workcenters",
    "workcenters.checklist_empty": "No workcenters have been set up yet.",
    "workcenters.archived_suffix": ":name (archived)",
    "workcenters.mode.hard": "Requirement",
    "workcenters.mode.soft": "Preference",
    "planning.tab": "Planning",
    "planning.empty": "No planned shifts yet.",
    "planning.published": "Published",
    "planning.draft": "Draft",
    "employees.field.first_name": "First name",
    "employees.field.last_name": "Last name",
    "employees.field.email": "Email",
    "employees.field.weekly_hours": "Weekly hours",
    "employees.field.business_line": "Business line",
    "employees.field.business_line_none": "None",
    "employees.hours_option": ":count hours",
    "employees.form.edit_title": "Edit employee",
    "employees.form.create_title": "Add employee",
    "employees.action.save": "Save",
    "employees.action.create": "Create",
    "employees.action.saving": "Saving…",
    "employees.action.saved": "Saved",
    "employees.action.cancel": "Cancel",
    "employees.action.delete": "Delete",
    "employees.delete.title": "Delete this employee?",
    "employees.delete.body": "This permanently removes :name's details from ShiftPlanner.",
    "employees.delete.confirm": "Yes, delete",
    "app.cancel": "Cancel",
};

// Requests fired by putAsync/postAsync/deleteAsync (availability, holidays,
// questions, competences) go through this mocked router.
const { routerCalls, failUrlsRef, router } = vi.hoisted(() => {
    const routerCalls = [];
    const failUrlsRef = { current: [] };
    const respond = (name) => (...args) => {
        const last = args.at(-1);
        const hasOpts = last && typeof last === "object" && (last.onSuccess || last.onError);
        const opts = hasOpts ? last : undefined;
        const rest = hasOpts ? args.slice(0, -1) : args;
        routerCalls.push([name, ...rest]);
        failUrlsRef.current.includes(rest[0]) ? opts?.onError?.() : opts?.onSuccess?.();
    };
    const router = { put: respond("put"), post: respond("post"), delete: respond("delete"), on: () => () => {} };
    return { routerCalls, failUrlsRef, router };
});

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { translations: en } }),
    useForm: (initial) => {
        const form = reactive({
            ...initial,
            errors: {},
            processing: false,
            recentlySuccessful: false,
            _defaults: { ...initial },
            get isDirty() {
                return Object.keys(initial).some((k) => this[k] !== this._defaults[k]);
            },
            defaults() {
                this._defaults = Object.fromEntries(Object.keys(initial).map((k) => [k, this[k]]));
            },
            reset() {
                Object.assign(this, this._defaults);
            },
            clearErrors() {
                this.errors = {};
            },
            put: vi.fn((url, opts) => {
                failUrlsRef.current.includes(`FORM:${url}`) ? opts.onError() : opts.onSuccess();
            }),
            post: vi.fn(),
        });
        return form;
    },
}));

import Form from "@/pages/Employees/Form.vue";
import EmployeeFields from "@/components/EmployeeFields.vue";
import WeeklyHoursField from "@/components/WeeklyHoursField.vue";
import HolidayList from "@/components/HolidayList.vue";
import AvailabilityGrid from "@/components/AvailabilityGrid.vue";
import ShiftNote from "@/components/ShiftNote.vue";
import TagChecklist from "@/components/TagChecklist.vue";
import WorkcenterChecklist from "@/components/WorkcenterChecklist.vue";
import QuestionChecklist from "@/components/QuestionChecklist.vue";
import EmployeePlanningSettings from "@/components/EmployeePlanningSettings.vue";
import { NumberInput } from "@/components/ui/Input";

const stubs = { AppLayout: { template: "<div><slot /></div>" }, teleport: true };
const findSaveButton = (w) => w.findAll("button").find((b) => ["Save", "Saving…", "Saved"].includes(b.text()));
const findCancelButton = (w) => w.findAll("button").find((b) => b.text() === "Cancel");
const findDeleteButton = (w) => w.findAll("button").find((b) => b.text() === "Delete");

beforeEach(() => {
    routerCalls.length = 0;
    failUrlsRef.current = [];
});

describe("Employees/Form", () => {
    it("shows Settings first and hides the Information tab", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        const tabs = w.findAll("button").map((button) => button.text());

        expect(tabs[0]).toBe("Settings");
        expect(tabs).toContain("Details");
        expect(tabs).toContain("Availability");
        expect(tabs).toContain("Workcenters");
        expect(tabs).toContain("Planning");
        expect(tabs).not.toContain("Information");
        expect(w.find('[data-testid="panel-information"]').exists()).toBe(false);
    });

    it("shows published and draft assignments in separate tables on the Planning tab", () => {
        const plannedShifts = [
            {
                weekStart: "2026-09-07", weekEnd: "2026-09-13",
                assignments: [{
                    date: "2026-09-08", workcenter_name: "Line 1", responsible: "Jane Doe", shift_name: "Early",
                    start_time: "06:00", end_time: "14:00", published: true,
                }],
            },
            {
                weekStart: "2026-09-14", weekEnd: "2026-09-20",
                assignments: [{
                    date: "2026-09-15", workcenter_name: "Line 2", shift_name: "Late",
                    start_time: "14:00", end_time: "22:00", published: false,
                }],
            },
        ];
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [], plannedShifts },
            global: { stubs },
        });

        const tables = w.get('[data-testid="panel-planning"]').findAll("table");
        expect(tables).toHaveLength(2);

        const published = tables[0].findAll("tbody tr");
        expect(published).toHaveLength(1);
        expect(published[0].findAll("td").map((td) => td.text())).toEqual(["37", "Tuesday", "08-09-2026", "Line 1", "Jane Doe"]);

        const draft = tables[1].findAll("tbody tr");
        expect(draft).toHaveLength(1);
        expect(draft[0].findAll("td").map((td) => td.text())).toEqual(["38", "Tuesday", "15-09-2026", "Line 2", "-"]);
    });

    it("shows an empty message for a Planning table without assignments", () => {
        const plannedShifts = [{
            weekStart: "2026-09-07", weekEnd: "2026-09-13",
            assignments: [{
                date: "2026-09-08", workcenter_name: "Line 1", shift_name: "Early",
                start_time: "06:00", end_time: "14:00", published: false,
            }],
        }];
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [], plannedShifts },
            global: { stubs },
        });

        const panel = w.get('[data-testid="panel-planning"]');
        expect(panel.findAll("table")).toHaveLength(1);
        expect(panel.text()).toContain("planning.published_empty");
    });

    it("shows employee planning rules only on the manager edit page", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24, weekly_hours_minimum: null },
                weeklyHoursMinimum: 20,
                holidays: [],
            },
            global: { stubs },
        });

        const settings = w.findComponent(EmployeePlanningSettings);
        expect(settings.exists()).toBe(true);
        expect(settings.props("inheritedMinimum")).toBe(20);

        const create = mount(Form, { props: { employee: null, holidays: [] }, global: { stubs } });
        expect(create.findComponent(EmployeePlanningSettings).exists()).toBe(false);
    });

    it("uses a changed employee minimum for the warning and preserves the saved hours", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 27, weekly_hours_minimum: null },
                weeklyHoursMinimum: 20,
                globalWeeklyHoursMinimum: 20,
                holidays: [],
            },
            global: { stubs },
        });
        const form = w.findComponent(EmployeeFields).props("form");

        w.findComponent(EmployeePlanningSettings).findComponent(NumberInput)
            .vm.$emit("update:modelValue", 28);
        await w.vm.$nextTick();

        expect(w.findComponent(WeeklyHoursField).props("minimum")).toBe(28);
        expect(w.findComponent(WeeklyHoursField).text()).toContain("employees.weekly_hours_below_minimum_warning");

        await findSaveButton(w).trigger("click");
        await flushPromises();
        expect(form.weekly_hours).toBe(27);
    });

    it("shows a newly enabled shift as not set after the employee settings save", async () => {
        const shift = {
            id: 7,
            name: "Night",
            start_time: "20:00",
            end_time: "23:00",
            visible_by_default: false,
        };
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                shifts: [shift],
                holidays: [],
            },
            global: { stubs },
        });
        const form = w.findComponent(EmployeeFields).props("form");

        await w.setProps({ shifts: [shift] });
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(w.get('[data-testid="cell-1-7"]').classes()).toContain("bg-(--color-badge-standard-bg)");
    });

    it("starts on Settings and reveals Availability on tab click", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });

        const hidden = (sel) => (w.get(sel).attributes("style") ?? "").includes("display: none");

        expect(hidden('[data-testid="panel-settings"]')).toBe(false);
        expect(hidden('[data-testid="panel-availability"]')).toBe(true);

        const availabilityTab = w.findAll("button").find((b) => b.text() === "Availability");
        await availabilityTab.trigger("click");
        await w.vm.$nextTick();

        expect(hidden('[data-testid="panel-settings"]')).toBe(true);
        expect(hidden('[data-testid="panel-availability"]')).toBe(false);
    });

    it("on create, shows only the Details fields with a Create button and no tabs", () => {
        const w = mount(Form, {
            props: { employee: null, holidays: [] },
            global: { stubs },
        });

        expect(w.findComponent(EmployeeFields).exists()).toBe(true);
        expect(w.get('[data-testid="panel-details"]').text()).toContain("Create");
        expect(w.findComponent(EmployeeFields).props("form").weekly_hours).toBe(0);

        expect(w.findAll("button").some((b) => b.text() === "Availability")).toBe(false);
        expect(w.find('[data-testid="panel-information"]').exists()).toBe(false);
        expect(w.find('[data-testid="panel-availability"]').exists()).toBe(false);
        expect(w.find('[data-testid="panel-competences"]').exists()).toBe(false);
        expect(w.findComponent(HolidayList).exists()).toBe(false);
        expect(w.findComponent(AvailabilityGrid).exists()).toBe(false);
        expect(findSaveButton(w)).toBeUndefined();
    });

    it("on create, submitting posts to the employees endpoint", async () => {
        const w = mount(Form, {
            props: { employee: null, holidays: [] },
            global: { stubs },
        });

        const form = w.findComponent(EmployeeFields).props("form");
        await w.get('[data-testid="panel-details"] form').trigger("submit");
        expect(form.post).toHaveBeenCalledWith("/employees");
    });

    it("shows the questions checklist on the Availability tab", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                questions: [{ id: 5, text: "Can we contact you to work in the weekend?" }],
                questionAnswers: [5],
            },
            global: { stubs },
        });

        const checklist = w.findComponent(QuestionChecklist);
        expect(checklist.exists()).toBe(true);
        expect(checklist.props("answeredIds")).toEqual([5]);
        expect(w.get('[data-testid="panel-availability"]').text()).toContain("Questions");
    });

    it("omits the questions section when no question is configured", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(w.findComponent(QuestionChecklist).exists()).toBe(false);
        expect(w.get('[data-testid="panel-availability"]').text()).not.toContain("Questions");
    });

    it("shows a Competences tab with the competence checklist", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                competences: [{ id: 1, name: "Forklift" }],
                competenceIds: [1],
            },
            global: { stubs },
        });

        const tab = w.findAll("button").find((b) => b.text() === "Competences");
        await tab.trigger("click");
        await w.vm.$nextTick();

        expect(w.findComponent(TagChecklist).props("selectedIds")).toEqual([1]);
    });

    it("separates editable and read-only competences while keeping both editable for managers", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                competences: [
                    { id: 1, name: "Forklift", read_only: false },
                    { id: 2, name: "Cleanroom", read_only: true },
                ],
            },
            global: { stubs },
        });

        const lists = w.findAllComponents(TagChecklist);
        expect(lists).toHaveLength(2);
        expect(lists[0].props("items")).toEqual([{ id: 1, name: "Forklift", read_only: false }]);
        expect(lists[1].props("items")).toEqual([{ id: 2, name: "Cleanroom", read_only: true }]);
        expect(lists.every((list) => list.props("disabled") === false)).toBe(true);
    });

    it("shows a Workcenters tab with the workcenter checklist", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                workcenters: [{ id: 1, name: "Assembly A", archived: false }],
                employeeWorkcenterAssignments: [{ workcenter_id: 1, mode: "hard" }],
            },
            global: { stubs },
        });

        const tab = w.findAll("button").find((b) => b.text() === "Workcenters");
        await tab.trigger("click");
        await w.vm.$nextTick();

        expect(w.findComponent(WorkcenterChecklist).props("selectedRows")).toEqual([{ workcenter_id: 1, mode: "hard" }]);
    });

    it("forwards the business lines to EmployeeFields and preselects the employee's line", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24, business_line_id: 8 },
                businessLines: [
                    { id: 5, abbreviation: "PMP" },
                    { id: 8, abbreviation: "VLV" },
                ],
                holidays: [],
            },
            global: { stubs },
        });

        const fields = w.findComponent(EmployeeFields);
        expect(fields.props("businessLines")).toHaveLength(2);
        expect(fields.props("form").business_line_id).toBe(8);
    });

    it("puts weekly hours on the Availability tab, not on Details", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });

        expect(w.get('[data-testid="panel-details"]').findComponent(WeeklyHoursField).exists()).toBe(false);
        expect(w.get('[data-testid="panel-availability"]').findComponent(WeeklyHoursField).exists()).toBe(true);
    });

    it("updates the availability-hours warning after an availability-grid change", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 20 },
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }],
                availability: [
                    { weekday: 2, shift_id: 1, level: "available" },
                    { weekday: 3, shift_id: 1, level: "available" },
                    { weekday: 4, shift_id: 1, level: "available" },
                    { weekday: 5, shift_id: 1, level: "available" },
                ],
                holidays: [],
            },
            global: { stubs },
        });

        w.findComponent(AvailabilityGrid).vm.$emit("update:availability", {
            weekday: 1,
            shiftId: 1,
            level: "unavailable",
        });
        await w.vm.$nextTick();

        expect(w.get('[data-testid="availability-hours-warning"]').text())
            .toContain("Your available time totals 16 hours per week, below your target of 20 hours.");
    });

    it("the Save button is disabled with nothing changed", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(findCancelButton(w).attributes("disabled")).toBeDefined();
    });

    it("Cancel restores weekly hours and disables both buttons, without saving", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        const hours = w.get('[data-testid="panel-availability"]').findComponent(WeeklyHoursField);
        const form = w.findComponent(EmployeeFields).props("form");
        hours.vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        expect(findCancelButton(w).attributes("disabled")).toBeUndefined();
        await findCancelButton(w).trigger("click");
        await w.vm.$nextTick();

        expect(form.weekly_hours).toBe(24);
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(findCancelButton(w).attributes("disabled")).toBeDefined();
        expect(form.put).not.toHaveBeenCalled();
    });

    it("Cancel discards a pending availability-grid change", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }],
                holidays: [],
            },
            global: { stubs },
        });
        w.findComponent(AvailabilityGrid).vm.$emit("update:availability", { weekday: 1, shiftId: 1, level: "unavailable" });
        await w.vm.$nextTick();
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findCancelButton(w).trigger("click");
        await w.vm.$nextTick();

        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        await findSaveButton(w).trigger("click");
        await flushPromises();
        expect(routerCalls).toEqual([]);
    });

    it("enables Save when weekly hours change, and saves via the employee endpoint on click", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        const hours = w.get('[data-testid="panel-availability"]').findComponent(WeeklyHoursField);
        const form = w.findComponent(EmployeeFields).props("form");
        hours.vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        expect(form.weekly_hours).toBe(40);
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(form.put).toHaveBeenCalledWith("/employees/3", expect.objectContaining({ async: true }));
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
    });

    it("enables Save when a Details field changes", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        const form = w.findComponent(EmployeeFields).props("form");

        form.email = "new@b.c";
        await w.vm.$nextTick();

        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(form.put).toHaveBeenCalledWith("/employees/3", expect.anything());
    });

    it("does not show a Save button on create", () => {
        const w = mount(Form, { props: { employee: null, holidays: [] }, global: { stubs } });
        expect(findSaveButton(w)).toBeUndefined();
    });

    it("saves an availability-grid change with one PUT per changed cell", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }],
                holidays: [],
            },
            global: { stubs },
        });
        w.findComponent(AvailabilityGrid).vm.$emit("update:availability", { weekday: 1, shiftId: 1, level: "unavailable" });
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/employees/3/availability/1/1", { level: "unavailable" }]);
    });

    it("saves a new holiday with a POST and a removed holiday with a DELETE", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [{ id: 9, start_date: "2026-01-01", end_date: "2026-01-02", note: null }],
            },
            global: { stubs },
        });

        w.findComponent(HolidayList).vm.$emit("update:holidays", [
            { id: null, start_date: "2026-02-01", end_date: "2026-02-02", note: "new" },
        ]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "post",
            "/employees/3/holidays",
            { start_date: "2026-02-01", end_date: "2026-02-02", note: "new" },
        ]);
        expect(routerCalls.some((c) => c[0] === "delete" && c[1] === "/employees/3/holidays/9")).toBe(true);
    });

    it("attaches a newly answered question with a PUT", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                questions: [{ id: 5, text: "Weekend?" }],
                questionAnswers: [],
            },
            global: { stubs },
        });
        w.findComponent(QuestionChecklist).vm.$emit("update:answeredIds", [5]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/employees/3/questions/5", { answer: true }]);
    });

    it("attaches a newly selected competence with a PUT", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                competences: [{ id: 1, name: "Forklift" }],
                competenceIds: [],
            },
            global: { stubs },
        });
        w.findComponent(TagChecklist).vm.$emit("update:selectedIds", [1]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/employees/3/competences/1", {}]);
    });

    it("attaches a newly selected workcenter row with a PUT carrying its mode", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                workcenters: [{ id: 1, name: "Assembly A", archived: false }],
                employeeWorkcenterAssignments: [],
            },
            global: { stubs },
        });
        w.findComponent(WorkcenterChecklist).vm.$emit("update:selectedRows", [{ workcenter_id: 1, mode: "hard" }]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/employees/3/workcenters/1", { mode: "hard" }]);
    });

    it("detaches an unselected workcenter row with a DELETE", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                workcenters: [{ id: 1, name: "Assembly A", archived: false }],
                employeeWorkcenterAssignments: [{ workcenter_id: 1, mode: "hard" }],
            },
            global: { stubs },
        });
        w.findComponent(WorkcenterChecklist).vm.$emit("update:selectedRows", []);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["delete", "/employees/3/workcenters/1"]);
    });

    it("re-puts a workcenter row whose mode changed", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                workcenters: [{ id: 1, name: "Assembly A", archived: false }],
                employeeWorkcenterAssignments: [{ workcenter_id: 1, mode: "hard" }],
            },
            global: { stubs },
        });
        w.findComponent(WorkcenterChecklist).vm.$emit("update:selectedRows", [{ workcenter_id: 1, mode: "soft" }]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/employees/3/workcenters/1", { mode: "soft" }]);
    });

    it("Cancel resets pending workcenter rows to the last-saved state", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                workcenters: [{ id: 1, name: "Assembly A", archived: false }],
                employeeWorkcenterAssignments: [{ workcenter_id: 1, mode: "hard" }],
            },
            global: { stubs },
        });
        w.findComponent(WorkcenterChecklist).vm.$emit("update:selectedRows", []);
        await w.vm.$nextTick();

        await findCancelButton(w).trigger("click");
        await w.vm.$nextTick();

        expect(w.findComponent(WorkcenterChecklist).props("selectedRows")).toEqual([{ workcenter_id: 1, mode: "hard" }]);
    });

    it("keeps Save enabled and marks the Availability tab on a failed availability save", async () => {
        failUrlsRef.current = ["/employees/3/availability/1/1"];
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }],
                holidays: [],
            },
            global: { stubs },
        });
        w.findComponent(AvailabilityGrid).vm.$emit("update:availability", { weekday: 1, shiftId: 1, level: "unavailable" });
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();
        const availabilityTab = w.findAll("button").find((b) => b.text().includes("Availability"));
        expect(availabilityTab.find('[data-testid="tab-error-dot"]').exists()).toBe(true);
    });

    it("shows a left-aligned Delete button in edit mode, none on create", () => {
        const edit = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(findDeleteButton(edit)).toBeTruthy();

        const create = mount(Form, { props: { employee: null, holidays: [] }, global: { stubs } });
        expect(findDeleteButton(create)).toBeUndefined();
    });

    it("clicking Delete opens the confirmation dialog naming the employee, without deleting anything yet", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(w.text()).not.toContain("Delete this employee?");

        await findDeleteButton(w).trigger("click");

        expect(w.text()).toContain("Delete this employee?");
        expect(w.text()).toContain("This permanently removes A B's details from ShiftPlanner.");
        expect(routerCalls).toEqual([]);
    });

    it("dismissing the delete dialog does not delete anything", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        await findDeleteButton(w).trigger("click");

        const dialogCancel = w.findAll("button").filter((b) => b.text() === "Cancel").at(-1);
        await dialogCancel.trigger("click");
        await w.vm.$nextTick();

        expect(w.text()).not.toContain("Delete this employee?");
        expect(routerCalls).toEqual([]);
    });

    it("confirming deletion deletes the employee", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        await findDeleteButton(w).trigger("click");

        const dialogConfirm = w.findAll("button").find((b) => b.text() === "Yes, delete");
        await dialogConfirm.trigger("click");

        expect(routerCalls).toContainEqual(["delete", "/employees/3"]);
    });
});
