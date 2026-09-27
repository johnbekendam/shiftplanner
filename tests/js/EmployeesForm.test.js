import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "availability.tab.information": "Information",
    "availability.tab.details": "Details",
    "availability.tab.availability": "Availability",
    "availability.questions.heading": "Questions",
    "competences.heading": "Competences",
    "availability.tab.settings": "Settings",
    "availability.info.empty": "No information has been provided yet.",
    "availability.info.cta": "Please update your details, availability and competences on the different tabs.",
    "availability.hours_warning.not_preferred": "You will be planned on not-preferred hours.",
    "availability.hours_warning.insufficient": "Your available time totals :available hours per week, below your target of :target hours.",
    "availability.holidays.empty": "No holidays yet.",
    "competences.tab": "Competences",
    "competences.checklist_empty": "No competences have been set up yet.",
    "workcenters.employee_tab": "Workcenters",
    "workcenters.checklist_empty": "No workcenters have been set up yet.",
    "workcenters.archived_suffix": ":name (archived)",
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
    "employees.action.restore": "Restore",
    "employees.archived.notice": "This employee is archived. Employee data is read-only.",
    "employees.delete.title": "Delete this employee?",
    "employees.delete.body": "This permanently removes :name's details from ShiftPlanner.",
    "employees.delete.confirm": "Yes, delete",
    "app.cancel": "Cancel",
    "tabs.unsaved.title": "Unsaved changes",
    "tabs.unsaved.discard": "Discard changes",
    "tabs.unsaved.stay": "Stay",
};

// Requests fired by putAsync/postAsync/deleteAsync (availability, holidays,
// questions, competences) go through this mocked router.
const { routerCalls, failUrlsRef, authState, router } = vi.hoisted(() => {
    const routerCalls = [];
    const failUrlsRef = { current: [] };
    const authState = { user: { role: "admin" } };
    const respond = (name) => (...args) => {
        const last = args.at(-1);
        const hasOpts = last && typeof last === "object" && (last.onSuccess || last.onError);
        const opts = hasOpts ? last : undefined;
        const rest = hasOpts ? args.slice(0, -1) : args;
        routerCalls.push([name, ...rest]);
        failUrlsRef.current.includes(rest[0]) ? opts?.onError?.() : opts?.onSuccess?.();
    };
    const router = { put: respond("put"), post: respond("post"), delete: respond("delete"), on: () => () => {} };
    return { routerCalls, failUrlsRef, authState, router };
});

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ props: { translations: en, auth: { user: authState.user } } }),
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
import { NumberInput, DateInput } from "@/components/ui/Input";
import AvailabilityCalendar from "@/components/AvailabilityCalendar.vue";
import Tabs from "@/components/ui/Tabs.vue";
import ConfirmDialog from "@/components/ui/ConfirmDialog.vue";
import DateAvailabilityGrid from "@/components/DateAvailabilityGrid.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" }, teleport: true };
const findSaveButton = (w) => w.findAll("button").find((b) => ["Save", "Saving…", "Saved"].includes(b.text()));
const findCancelButton = (w) => w.findAll("button").find((b) => b.text() === "Cancel");
const findDeleteButton = (w) => w.findAll("button").find((b) => b.text() === "Delete");

// The Default week grid only mounts once the weekday header is picked in the calendar.
const selectDefaultWeek = async (w, selected = true) => {
    w.getComponent(AvailabilityCalendar).vm.$emit("update:defaultWeekSelected", selected);
    await w.vm.$nextTick();
};

// Picks the date in the calendar, then applies a change from the date grid.
const applyDay = async (w, day) => {
    w.getComponent(AvailabilityCalendar).vm.$emit("update:selectedDate", day.date);
    await w.vm.$nextTick();
    w.getComponent(DateAvailabilityGrid).vm.$emit("apply-day", day);
    await w.vm.$nextTick();
};

beforeEach(() => {
    routerCalls.length = 0;
    failUrlsRef.current = [];
    authState.user = { role: "admin" };
    window.history.replaceState({}, "", "/");
});

describe("Employees/Form", () => {
    it("makes an archived employee read-only and lets an admin restore it", async () => {
        const w = mount(Form, {
            props: {
                employee: {
                    id: 3,
                    first_name: "A",
                    last_name: "B",
                    email: "a@b.c",
                    weekly_hours: 24,
                    archived: true,
                },
                holidays: [],
                questions: [{ id: 1, text: "Question" }],
                competences: [{ id: 1, name: "Skill", read_only: false }],
                workcenters: [{ id: 1, name: "Line", archived: false }],
            },
            global: { stubs },
        });
        await selectDefaultWeek(w);

        expect(w.text()).toContain("This employee is archived. Employee data is read-only.");
        expect(w.findComponent(EmployeeFields).props("readonlyIdentity")).toBe(true);
        expect(w.findComponent(WeeklyHoursField).props("disabled")).toBe(true);
        expect(w.findComponent(AvailabilityGrid).props("disabled")).toBe(true);
        expect(w.findComponent(HolidayList).props("disabled")).toBe(true);
        expect(w.findComponent(QuestionChecklist).props("disabled")).toBe(true);
        expect(w.findComponent(TagChecklist).props("disabled")).toBe(true);
        expect(w.findComponent(WorkcenterChecklist).props("disabled")).toBe(true);
        expect(w.findComponent(EmployeePlanningSettings).props("disabled")).toBe(true);
        expect(findSaveButton(w)).toBeUndefined();
        expect(findDeleteButton(w)).toBeUndefined();

        await w.findAll("button").find((button) => button.text() === "Restore").trigger("click");
        expect(routerCalls).toContainEqual(["post", "/employees/3/restore", {}]);
    });

    it("does not show restore to a manager", () => {
        authState.user = { role: "manager" };
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", weekly_hours: 24, archived: true } },
            global: { stubs },
        });

        expect(w.findAll("button").some((button) => button.text() === "Restore")).toBe(false);
    });

    it("omits employee history and falls back from a stale audit tab URL", () => {
        window.history.replaceState({}, "", "/employees/3/edit?tab=audit");
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", weekly_hours: 24, archived: true },
            },
            global: { stubs },
        });

        expect(w.findAll("button").some((button) => button.text() === "History")).toBe(false);
        expect(w.find('[data-testid="panel-audit"]').exists()).toBe(false);
        expect(w.get('[data-testid="panel-settings"]').isVisible()).toBe(true);
    });

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
        expect(w.get('[data-testid="panel-planning"]').find("button").exists()).toBe(false);

        const published = tables[0].findAll("tbody tr");
        expect(published).toHaveLength(1);
        expect(published[0].findAll("td").map((td) => td.text())).toEqual(["37", "Tuesday", "Early", "Line 1", "Jane Doe", ""]);

        const draft = tables[1].findAll("tbody tr");
        expect(draft).toHaveLength(1);
        expect(draft[0].findAll("td").map((td) => td.text())).toEqual(["38", "Tuesday", "Late", "Line 2", "-", ""]);
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
            weekdays: [1, 2, 3, 4, 5],
        };
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                shifts: [shift],
                holidays: [],
            },
            global: { stubs },
        });
        await selectDefaultWeek(w);
        const form = w.findComponent(EmployeeFields).props("form");

        await w.setProps({ shifts: [shift] });
        w.getComponent(WeeklyHoursField).vm.$emit("update:modelValue", 40);
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

    it("shows the questions in their own section on the Competences tab, below the competences", () => {
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
        const panel = w.get('[data-testid="panel-competences"]');
        expect(panel.get('[data-testid="questions-section"]').findComponent(QuestionChecklist).exists()).toBe(true);
        expect(panel.get('[data-testid="competences-section"]').text()).toContain("Competences");
        expect(panel.get('[data-testid="questions-section"]').text()).toContain("Questions");
        const html = panel.html();
        expect(html.indexOf('data-testid="competences-section"')).toBeLessThan(html.indexOf('data-testid="questions-section"'));
        expect(w.findAll("button").map((button) => button.text())).not.toContain("Questions");
    });

    it("omits the questions section when no question is configured", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(w.findComponent(QuestionChecklist).exists()).toBe(false);
        expect(w.find('[data-testid="questions-section"]').exists()).toBe(false);
        expect(w.get('[data-testid="panel-competences"]').text()).not.toContain("Questions");
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
                workcenterIds: [1],
            },
            global: { stubs },
        });

        const tab = w.findAll("button").find((b) => b.text() === "Workcenters");
        await tab.trigger("click");
        await w.vm.$nextTick();

        expect(w.findComponent(WorkcenterChecklist).props("selectedIds")).toEqual([1]);
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
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }],
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
        await selectDefaultWeek(w);

        w.findComponent(AvailabilityGrid).vm.$emit("update:availability", {
            weekday: 1,
            shiftId: 1,
            level: "unavailable",
        });
        await w.vm.$nextTick();

        expect(w.get('[data-testid="availability-hours-warning"]').text())
            .toContain("Your available time totals 16 hours per week, below your target of 20 hours.");
    });

    it("shows no Save or Cancel button with nothing changed", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(findSaveButton(w)).toBeUndefined();
        expect(findCancelButton(w)).toBeUndefined();
    });

    it("Cancel restores weekly hours and hides the footer, without saving", async () => {
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
        expect(w.find('[data-testid="card-footer-actions"]').exists()).toBe(false);
        expect(form.put).not.toHaveBeenCalled();
    });

    it("Cancel discards a pending availability-grid change", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }],
                holidays: [],
            },
            global: { stubs },
        });
        await selectDefaultWeek(w);
        w.findComponent(AvailabilityGrid).vm.$emit("update:availability", { weekday: 1, shiftId: 1, level: "unavailable" });
        await w.vm.$nextTick();
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findCancelButton(w).trigger("click");
        await w.vm.$nextTick();

        expect(w.find('[data-testid="card-footer-actions"]').exists()).toBe(false);
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
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }],
                holidays: [],
            },
            global: { stubs },
        });
        await selectDefaultWeek(w);
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

    it("attaches a newly selected workcenter with a bodyless PUT", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                workcenters: [{ id: 1, name: "Assembly A", archived: false }],
                workcenterIds: [],
            },
            global: { stubs },
        });
        w.findComponent(WorkcenterChecklist).vm.$emit("update:selectedIds", [1]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/employees/3/workcenters/1", {}]);
    });

    it("detaches an unselected workcenter row with a DELETE", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                workcenters: [{ id: 1, name: "Assembly A", archived: false }],
                workcenterIds: [1],
            },
            global: { stubs },
        });
        w.findComponent(WorkcenterChecklist).vm.$emit("update:selectedIds", []);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["delete", "/employees/3/workcenters/1"]);
    });

    it("re-seeds the availability grid after a workcenter save brings a newly-visible shift", async () => {
        const originalPut = router.put;
        router.put = async (url, data, opts) => {
            await w.setProps({
                shifts: [
                    { id: 1, name: "Early", start_time: "06:00", end_time: "14:00", weekdays: [1, 2, 3, 4, 5] },
                    { id: 2, name: "Night", start_time: "22:00", end_time: "06:00", weekdays: [1, 2, 3, 4, 5] },
                ],
                availability: [],
            });
            opts.onSuccess();
        };

        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                shifts: [{ id: 1, name: "Early", start_time: "06:00", end_time: "14:00", weekdays: [1, 2, 3, 4, 5] }],
                availability: [],
                workcenters: [{ id: 1, name: "Line 1", archived: false }],
                workcenterIds: [],
            },
            global: { stubs },
        });
        await selectDefaultWeek(w);

        w.findComponent(WorkcenterChecklist).vm.$emit("update:selectedIds", [1]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();
        router.put = originalPut;

        const cell = w.find('[data-testid="cell-1-2"]');
        expect(cell.exists()).toBe(true);
        expect(cell.classes()).toContain("bg-(--color-badge-standard-bg)");
    });

    it("Cancel resets pending workcenter rows to the last-saved state", async () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                workcenters: [{ id: 1, name: "Assembly A", archived: false }],
                workcenterIds: [1],
            },
            global: { stubs },
        });
        w.findComponent(WorkcenterChecklist).vm.$emit("update:selectedIds", []);
        await w.vm.$nextTick();

        await findCancelButton(w).trigger("click");
        await w.vm.$nextTick();

        expect(w.findComponent(WorkcenterChecklist).props("selectedIds")).toEqual([1]);
    });

    it("keeps Save enabled and marks the Availability tab on a failed availability save", async () => {
        failUrlsRef.current = ["/employees/3/availability/1/1"];
        const w = mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 },
                shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }],
                holidays: [],
            },
            global: { stubs },
        });
        await selectDefaultWeek(w);
        w.findComponent(AvailabilityGrid).vm.$emit("update:availability", { weekday: 1, shiftId: 1, level: "unavailable" });
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();
        const availabilityTab = w.findAll("button").find((b) => b.text().includes("Availability"));
        expect(availabilityTab.find('[data-testid="tab-error-dot"]').exists()).toBe(true);
    });

    it("shows a Delete button in edit mode, none on create", () => {
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

    const mountEdit = (extra = {}) =>
        mount(Form, {
            props: {
                employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24, available_from: "2026-11-02" },
                holidays: [],
                ...extra,
            },
            global: { stubs },
        });

    it("puts the start date on the Availability tab and saves it with the employee endpoint", async () => {
        const w = mountEdit();
        const panel = w.get('[data-testid="panel-availability"]');
        expect(panel.getComponent(DateInput).props("modelValue")).toBe("2026-11-02");
        expect(panel.getComponent(AvailabilityCalendar).props("availableFrom")).toBe("2026-11-02");

        const form = w.findComponent(EmployeeFields).props("form");
        panel.getComponent(DateInput).vm.$emit("update:modelValue", "2026-12-01");
        await w.vm.$nextTick();

        expect(form.available_from).toBe("2026-12-01");
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(form.put).toHaveBeenCalledWith("/employees/3", expect.objectContaining({ async: true }));
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
    });

    it("saves an applied calendar day with one PUT per date on the employee route", async () => {
        const w = mountEdit({ availabilityOverrides: [{ date: "2026-10-05", shift_id: 1, level: "available" }] });
        const calendar = w.getComponent(AvailabilityCalendar);
        expect(calendar.props("overrides")).toEqual({ "2026-10-05": { blocked: false, shifts: { 1: "available" } } });

        await applyDay(w, { date: "2026-10-07", blocked: true, shifts: {} });
        await w.vm.$nextTick();
        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toEqual([["put", "/employees/3/availability/dates/2026-10-07", { blocked: true, shifts: {} }]]);
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
    });

    it("Cancel discards a pending calendar day", async () => {
        const w = mountEdit();
        const calendar = w.getComponent(AvailabilityCalendar);

        await applyDay(w, { date: "2026-10-07", blocked: true, shifts: {} });
        await w.vm.$nextTick();
        await findCancelButton(w).trigger("click");

        expect(calendar.props("overrides")).toEqual({});
        expect(w.find('[data-testid="card-footer-actions"]').exists()).toBe(false);
    });

    it("makes the date grid read-only for an archived employee", async () => {
        const w = mountEdit({ employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24, archived: true } });

        w.getComponent(AvailabilityCalendar).vm.$emit("update:selectedDate", "2026-10-05");
        await w.vm.$nextTick();
        expect(w.getComponent(DateAvailabilityGrid).props("disabled")).toBe(true);
        expect(w.getComponent(DateInput).props("disabled")).toBe(true);
    });

    it("shows the hint card below the calendar until the default week is picked, then the default availability", async () => {
        const w = mount(Form, { props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [], shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }] }, global: { stubs } });
        const panel = w.get('[data-testid="panel-availability"]');
        const section = () => panel.get('[data-testid="default-week-section"]');
        const sections = panel.findAll('[data-testid="availability-calendar-section"], [data-testid="default-week-section"]');
        expect(sections.map((x) => x.attributes("data-testid"))).toEqual(["availability-calendar-section", "default-week-section"]);
        expect(section().find('[data-testid="default-week-hint"]').exists()).toBe(true);
        expect(section().find('[data-testid="availability-card-header"]').exists()).toBe(false);
        expect(panel.findComponent(AvailabilityGrid).exists()).toBe(false);

        await selectDefaultWeek(w);

        expect(panel.getComponent(AvailabilityCalendar).props("defaultWeekSelected")).toBe(true);
        expect(panel.findAll('[data-testid^="cell-"]').map((c) => c.attributes("data-testid"))).toEqual([
            "cell-1-1", "cell-2-1", "cell-3-1", "cell-4-1", "cell-5-1",
        ]);
        expect(section().find('[data-testid="default-week-hint"]').exists()).toBe(false);

        await selectDefaultWeek(w, false);
        expect(section().find('[data-testid="default-week-hint"]').exists()).toBe(true);
        expect(panel.findComponent(AvailabilityGrid).exists()).toBe(false);
    });


    it("keeps a pending default edit visible after hiding and showing the default week", async () => {
        const w = mount(Form, { props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [], shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }] }, global: { stubs } });
        await selectDefaultWeek(w);
        w.getComponent(AvailabilityGrid).vm.$emit("update:availability", { weekday: 1, shiftId: 1, level: "unavailable" });
        await selectDefaultWeek(w, false);
        await selectDefaultWeek(w);

        expect(w.get('[data-testid="cell-1-1"]').classes()).toContain("bg-(--color-badge-error-bg)");
    });


    it("opens the Competences tab for an old ?tab=questions link", () => {
        window.history.replaceState({}, "", "/employees/3/edit?tab=questions");
        const w = mount(Form, {
            props: { employee: { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });

        expect((w.get('[data-testid="panel-competences"]').attributes("style") ?? "")).not.toContain("display: none");
    });

    const fitStubs = { ...stubs, AppLayout: { props: ["fitHeight"], template: "<div :data-fit-height='String(fitHeight)'><slot /></div>" } };
    const editEmployee = { id: 3, first_name: "A", last_name: "B", email: "a@b.c", weekly_hours: 24 };

    it("fits the edit card to the page: a scrolling body and the buttons in the card footer", async () => {
        const w = mount(Form, { props: { employee: editEmployee, holidays: [] }, global: { stubs: fitStubs } });
        w.getComponent(WeeklyHoursField).vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        expect(w.get("[data-fit-height]").attributes("data-fit-height")).toBe("true");
        expect(w.get('[data-testid="employee-card"]').classes()).toEqual(expect.arrayContaining(["flex", "min-h-0", "flex-col"]));
        expect(w.get('[data-testid="card-body"]').classes()).toEqual(expect.arrayContaining(["min-h-0", "flex-1", "overflow-y-auto"]));
        const footer = w.get('[data-testid="card-footer-actions"]');
        expect(footer.findAll("button").map((b) => b.text())).toEqual(["Cancel", "Save"]);
        expect(footer.find("hr").exists()).toBe(false);
        expect(w.get('[data-testid="card-body"]').findAll("button").some((b) => b.text() === "Save")).toBe(false);
    });

    it("shows only Restore in the footer of an archived employee", () => {
        const w = mount(Form, { props: { employee: { ...editEmployee, archived: true }, holidays: [] }, global: { stubs: fitStubs } });

        expect(w.get('[data-testid="card-footer-actions"]').findAll("button").map((b) => b.text())).toEqual(["Restore"]);
    });

    it("scrolls the card body back to the top on a tab switch", async () => {
        const w = mount(Form, { props: { employee: editEmployee, holidays: [] }, global: { stubs: fitStubs } });
        const body = w.get('[data-testid="card-body"]').element;
        body.scrollTop = 300;

        w.getComponent(Tabs).vm.$emit("update:modelValue", "availability");
        await w.vm.$nextTick();

        expect(body.scrollTop).toBe(0);
    });

    it("leaves the create form as it was: no fit height, no footer", () => {
        const w = mount(Form, { props: { employee: null, holidays: [] }, global: { stubs: fitStubs } });

        expect(w.get("[data-fit-height]").attributes("data-fit-height")).toBe("false");
        expect(w.get('[data-testid="card-body"]').classes()).not.toContain("overflow-y-auto");
        expect(w.find('[data-testid="card-footer-actions"]').exists()).toBe(false);
    });

    it("shows no footer to a manager on an archived employee", () => {
        authState.user = { role: "manager" };
        const w = mount(Form, { props: { employee: { ...editEmployee, archived: true }, holidays: [] }, global: { stubs: fitStubs } });

        expect(w.find('[data-testid="card-footer-actions"]').exists()).toBe(false);
    });

    it("gives the card footer the body background", async () => {
        const w = mount(Form, { props: { employee: editEmployee, holidays: [] }, global: { stubs: fitStubs } });
        w.getComponent(WeeklyHoursField).vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();
        const footer = w.get('[data-testid="card-footer-actions"]').element.parentElement;

        expect(footer.className).toContain("bg-[var(--color-card-body-bg)]");
        expect(footer.className).not.toContain("--color-card-footer-bg");
    });

    it("shows Delete only on the Details tab, not in the footer, and not for an archived employee", () => {
        const w = mount(Form, { props: { employee: editEmployee, holidays: [] }, global: { stubs: fitStubs } });

        expect(w.get('[data-testid="panel-details"]').findAll("button").filter((b) => b.text() === "Delete")).toHaveLength(1);
        expect(w.findAll("button").filter((b) => b.text() === "Delete")).toHaveLength(1);

        const archived = mount(Form, { props: { employee: { ...editEmployee, archived: true }, holidays: [] }, global: { stubs: fitStubs } });
        expect(archived.findAll("button").some((b) => b.text() === "Delete")).toBe(false);
    });

    it("shows the Cancel / Save footer only when something changed", async () => {
        const w = mount(Form, { props: { employee: editEmployee, holidays: [] }, global: { stubs: fitStubs } });
        expect(w.find('[data-testid="card-footer-actions"]').exists()).toBe(false);

        w.getComponent(WeeklyHoursField).vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        expect(w.get('[data-testid="card-footer-actions"]').findAll("button").map((b) => b.text())).toEqual(["Cancel", "Save"]);
    });

    it("asks to stay or discard before leaving a tab with unsaved changes", async () => {
        const w = mount(Form, { props: { employee: editEmployee, holidays: [] }, global: { stubs: fitStubs } });
        const dialog = () => w.findAllComponents(ConfirmDialog).find((d) => d.props("title") === "Unsaved changes");
        const form = w.findComponent(EmployeeFields).props("form");
        w.getComponent(WeeklyHoursField).vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        w.getComponent(Tabs).vm.$emit("update:modelValue", "planning");
        await w.vm.$nextTick();
        expect(w.getComponent(Tabs).props("modelValue")).toBe("settings");
        expect(dialog().props()).toMatchObject({ open: true, confirmLabel: "Discard changes", cancelLabel: "Stay" });

        dialog().vm.$emit("cancel");
        await w.vm.$nextTick();
        expect(dialog().props("open")).toBe(false);
        expect(form.weekly_hours).toBe(40);

        w.getComponent(Tabs).vm.$emit("update:modelValue", "planning");
        await w.vm.$nextTick();
        dialog().vm.$emit("confirm");
        await w.vm.$nextTick();
        expect(w.getComponent(Tabs).props("modelValue")).toBe("planning");
        expect(form.weekly_hours).toBe(24);
    });
});
