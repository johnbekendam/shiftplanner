import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "availability.tab.information": "Information",
    "availability.tab.details": "Details",
    "availability.tab.availability": "Availability",
    "availability.info.empty": "No information has been provided yet.",
    "availability.info.cta": "Please update your details, availability and competences on the different tabs.",
    "availability.hours_warning.not_preferred": "You will be planned on not-preferred hours.",
    "availability.hours_warning.insufficient": "Your available time totals :available hours per week, below your target of :target hours.",
    "availability.holidays.empty": "No holidays yet.",
    "availability.questions.heading": "Questions",
    "competences.tab": "Competences",
    "competences.checklist_empty": "No competences have been set up yet.",
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
};

// Requests fired by putAsync/postAsync/deleteAsync (availability, holidays,
// questions, competences) go through this mocked router.
const { routerCalls, failUrlsRef, router } = vi.hoisted(() => {
    const routerCalls = [];
    const failUrlsRef = { current: [] };
    const respond = (name) => (...args) => {
        const opts = args.at(-1);
        const rest = args.slice(0, -1);
        routerCalls.push([name, ...rest]);
        failUrlsRef.current.includes(rest[0]) ? opts.onError() : opts.onSuccess();
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
import QuestionChecklist from "@/components/QuestionChecklist.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };
const findSaveButton = (w) => w.findAll("button").find((b) => ["Save", "Saving…", "Saved"].includes(b.text()));

beforeEach(() => {
    routerCalls.length = 0;
    failUrlsRef.current = [];
});

describe("Employees/Form", () => {
    it("shows Information, Details and Availability tabs", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(w.text()).toContain("Information");
        expect(w.text()).toContain("Details");
        expect(w.text()).toContain("Availability");
    });

    it("starts on Information and reveals Availability on tab click", async () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });

        const hidden = (sel) => (w.get(sel).attributes("style") ?? "").includes("display: none");

        expect(hidden('[data-testid="panel-information"]')).toBe(false);
        expect(hidden('[data-testid="panel-availability"]')).toBe(true);

        const availabilityTab = w.findAll("button").find((b) => b.text() === "Availability");
        await availabilityTab.trigger("click");
        await w.vm.$nextTick();

        expect(hidden('[data-testid="panel-information"]')).toBe(true);
        expect(hidden('[data-testid="panel-availability"]')).toBe(false);
    });

    it("renders the shift note on the Information tab when set", () => {
        const w = mount(Form, {
            props: {
                employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 },
                holidays: [],
                shiftNoteHtml: "<p>Allowances table here</p>",
            },
            global: { stubs },
        });

        const note = w.findComponent(ShiftNote);
        expect(note.exists()).toBe(true);
        expect(note.props("html")).toBe("<p>Allowances table here</p>");
        expect(w.get('[data-testid="panel-information"]').text()).toContain("Allowances table here");
    });

    it("shows an empty state on the Information tab when no shift note is set", () => {
        const w = mount(Form, {
            props: { employee: { id: 3, name: "A", email: "a@b.c", weekly_hours: 24 }, holidays: [] },
            global: { stubs },
        });
        expect(w.findComponent(ShiftNote).exists()).toBe(false);
        expect(w.get('[data-testid="panel-information"]').text()).toContain("No information has been provided yet.");
    });

    it("on create, shows only the Details fields with a Create button and no tabs", () => {
        const w = mount(Form, {
            props: { employee: null, holidays: [] },
            global: { stubs },
        });

        expect(w.findComponent(EmployeeFields).exists()).toBe(true);
        expect(w.get('[data-testid="panel-details"]').text()).toContain("Create");
        expect(w.findComponent(EmployeeFields).props("form").weekly_hours).toBe(32);

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
});
