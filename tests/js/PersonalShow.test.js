import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
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
    "personal.title": "Your working hours",
    "personal.action.save": "Save",
    "personal.action.saving": "Saving…",
    "personal.action.cancel": "Cancel",
    "personal.action.withdraw": "Withdraw",
    "personal.withdraw.title": "Withdraw?",
    "personal.withdraw.body": "This permanently removes your details from ShiftPlanner.",
    "personal.withdraw.confirm": "Yes, withdraw",
    "app.cancel": "Cancel",
    "personal.saved": "Saved",
    "personal.locked_notice": "Changes are currently closed by your planner.",
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
    "planning.tab": "Planning",
    "planning.empty": "No planned shifts yet.",
};

// Requests fired by putAsync/postAsync/deleteAsync (availability, holidays,
// questions, competences) all go through this mocked router. `form.put`
// below is a separate hand-rolled fake, same as the old test.
//
// Both `router` and `useForm` are built inside vi.hoisted (no access to
// real imports like `reactive` yet), then `useForm`'s real implementation
// is wired in below, once `reactive` is available and `form` exists.
const { routerCalls, failUrlsRef, router, useFormMock } = vi.hoisted(() => {
    const routerCalls = [];
    const failUrlsRef = { current: [] };
    const router = {
        put: (url, data, opts) => {
            routerCalls.push(["put", url, data]);
            failUrlsRef.current.includes(url) ? opts.onError() : opts.onSuccess();
        },
        post: (url, data, opts) => {
            routerCalls.push(["post", url, data]);
            failUrlsRef.current.includes(url) ? opts.onError() : opts.onSuccess();
        },
        delete: (url, opts) => {
            routerCalls.push(["delete", url]);
            failUrlsRef.current.includes(url) ? opts?.onError?.() : opts?.onSuccess?.();
        },
        on: () => () => {},
    };
    const useFormMock = (...args) => useFormMock.impl(...args);
    return { routerCalls, failUrlsRef, router, useFormMock };
});

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en, appName: "ShiftPlanner", logoUrl: null } }),
    useForm: useFormMock,
}));

const form = reactive({
    first_name: "",
    last_name: "",
    email: "",
    weekly_hours: null,
    business_line_id: null,
    errors: {},
    processing: false,
    recentlySuccessful: false,
    _defaults: {},
    _transform: null,
    get isDirty() {
        return this.weekly_hours !== this._defaults.weekly_hours
            || this.business_line_id !== this._defaults.business_line_id;
    },
    defaults() {
        this._defaults = { weekly_hours: this.weekly_hours, business_line_id: this.business_line_id };
    },
    reset() {
        this.weekly_hours = this._defaults.weekly_hours;
        this.business_line_id = this._defaults.business_line_id;
    },
    clearErrors() {
        this.errors = {};
    },
    transform(fn) {
        this._transform = fn;
        return this;
    },
    put(url, opts) {
        const data = { weekly_hours: this.weekly_hours, business_line_id: this.business_line_id };
        form.lastPut = { url, opts, data: this._transform ? this._transform(data) : data };
        if (failUrlsRef.current.includes(`FORM:${url}`)) opts.onError();
        else opts.onSuccess();
    },
});

useFormMock.impl = (initial) => {
    Object.assign(form, initial);
    form.defaults();
    return form;
};

import Show from "@/pages/Personal/Show.vue";
import EmployeeFields from "@/components/EmployeeFields.vue";
import WeeklyHoursField from "@/components/WeeklyHoursField.vue";
import HolidayList from "@/components/HolidayList.vue";
import AvailabilityGrid from "@/components/AvailabilityGrid.vue";
import ShiftNote from "@/components/ShiftNote.vue";
import TagChecklist from "@/components/TagChecklist.vue";
import QuestionChecklist from "@/components/QuestionChecklist.vue";
import SelectInput from "@/components/ui/Input/Select.vue";

const mountShow = (holidays = [], extra = {}) =>
    mount(Show, {
        props: {
            token: "tok-1",
            employee: {
                first_name: "Jordan",
                last_name: "Lee",
                email: "jordan@example.com",
                weekly_hours: 24,
                business_line_id: null,
            },
            businessLines: [],
            holidays,
            ...extra,
        },
        global: {
            stubs: {
                CenteredLayout: {
                    template: "<div><slot name='header' /><slot /><slot name='footer' /></div>",
                },
                teleport: true,
            },
        },
    });

const hidden = (w, sel) => (w.get(sel).attributes("style") ?? "").includes("display: none");
const findSaveButton = (w) => w.findAll("button").find((b) => ["Save", "Saving…", "Saved"].includes(b.text()));
const findCancelButton = (w) => w.findAll("button").find((b) => b.text() === "Cancel");
const findWithdrawButton = (w) => w.findAll("button").find((b) => b.text() === "Withdraw");

beforeEach(() => {
    routerCalls.length = 0;
    failUrlsRef.current = [];
    form.errors = {};
    form.recentlySuccessful = false;
    form.lastPut = undefined;
});

describe("Personal/Show", () => {
    it("renders the shared fields with the identity fields read-only", () => {
        const w = mountShow();
        expect(w.findComponent(EmployeeFields).props("readonlyIdentity")).toBe(true);

        const inputs = w.get('[data-testid="panel-details"]').findAll("input");
        expect(inputs).toHaveLength(3);
        expect(inputs.every((i) => i.attributes("disabled") !== undefined)).toBe(true);
    });

    it("seeds the form from the employee's current hours and business line", () => {
        mountShow([], { employee: { first_name: "J", last_name: "L", email: "j@l.c", weekly_hours: 24, business_line_id: 5 } });
        expect(form.weekly_hours).toBe(24);
        expect(form.business_line_id).toBe(5);
    });

    it("passes the business lines to EmployeeFields", () => {
        const lines = [{ id: 5, abbreviation: "PMP" }];
        const w = mountShow([], { businessLines: lines });
        expect(w.findComponent(EmployeeFields).props("businessLines")).toEqual(lines);
    });

    it("puts the weekly-hours field on the Availability tab", () => {
        const w = mountShow();
        expect(w.get('[data-testid="panel-details"]').findComponent(WeeklyHoursField).exists()).toBe(false);
        expect(w.get('[data-testid="panel-availability"]').findComponent(WeeklyHoursField).exists()).toBe(true);
        expect(w.text()).not.toContain("Settings");
    });

    it("updates the availability-hours warning after an availability-grid change", async () => {
        const w = mountShow([], {
            shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }],
            availability: [
                { weekday: 2, shift_id: 1, level: "available" },
                { weekday: 3, shift_id: 1, level: "available" },
                { weekday: 4, shift_id: 1, level: "available" },
                { weekday: 5, shift_id: 1, level: "available" },
            ],
            employee: { first_name: "J", last_name: "L", email: "j@l.c", weekly_hours: 20, business_line_id: null },
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
        const w = mountShow();
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
    });

    it("hides Withdraw, Cancel, and Save entirely when not editable", () => {
        const locked = mountShow([], { editable: false });
        expect(findSaveButton(locked)).toBeUndefined();
        expect(findCancelButton(locked)).toBeUndefined();
        expect(findWithdrawButton(locked)).toBeUndefined();
    });

    it("clicking Withdraw opens the confirmation dialog without deleting anything yet", async () => {
        const w = mountShow();
        expect(w.text()).not.toContain("Withdraw?");

        await findWithdrawButton(w).trigger("click");

        expect(w.text()).toContain("Withdraw?");
        expect(w.text()).toContain("This permanently removes your details from ShiftPlanner.");
        expect(routerCalls).toEqual([]);
    });

    it("dismissing the withdraw dialog does not delete anything", async () => {
        const w = mountShow();
        await findWithdrawButton(w).trigger("click");

        const dialogCancel = w.findAll("button").filter((b) => b.text() === "Cancel").at(-1);
        await dialogCancel.trigger("click");
        await w.vm.$nextTick();

        expect(w.text()).not.toContain("Withdraw?");
        expect(routerCalls).toEqual([]);
    });

    it("confirming withdrawal deletes the personal record", async () => {
        const w = mountShow();
        await findWithdrawButton(w).trigger("click");

        const dialogConfirm = w.findAll("button").find((b) => b.text() === "Yes, withdraw");
        await dialogConfirm.trigger("click");

        expect(routerCalls).toContainEqual(["delete", "/personal/tok-1"]);
    });

    it("enables Save when weekly hours change, and saves via the personal endpoint on click", async () => {
        const w = mountShow();
        w.get('[data-testid="panel-availability"]').findComponent(WeeklyHoursField)
            .vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(form.lastPut.url).toBe("/personal/tok-1");
        expect(form.lastPut.data).toEqual({ weekly_hours: 40, business_line_id: null });
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
    });

    it("Cancel is disabled with nothing changed", () => {
        const w = mountShow();
        expect(findCancelButton(w).attributes("disabled")).toBeDefined();
    });

    it("Cancel restores weekly hours and disables both buttons, without saving", async () => {
        const w = mountShow();
        w.get('[data-testid="panel-availability"]').findComponent(WeeklyHoursField)
            .vm.$emit("update:modelValue", 40);
        await w.vm.$nextTick();

        expect(findCancelButton(w).attributes("disabled")).toBeUndefined();
        await findCancelButton(w).trigger("click");
        await w.vm.$nextTick();

        expect(form.weekly_hours).toBe(24);
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(findCancelButton(w).attributes("disabled")).toBeDefined();
        expect(form.lastPut).toBeUndefined();
    });

    it("Cancel discards a pending availability-grid change", async () => {
        const w = mountShow([], { shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }] });
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

    it("enables Save when the business line changes, from the Details tab", async () => {
        const w = mountShow([], { businessLines: [{ id: 5, abbreviation: "PMP" }] });

        w.get('[data-testid="panel-details"]').findComponent(SelectInput)
            .vm.$emit("update:modelValue", 5);
        await w.vm.$nextTick();

        expect(form.business_line_id).toBe(5);
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(form.lastPut.data).toEqual({ weekly_hours: 24, business_line_id: 5 });
    });

    it("saves an availability-grid change with one PUT per changed cell", async () => {
        const w = mountShow([], {
            shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }],
        });
        w.findComponent(AvailabilityGrid).vm.$emit("update:availability", { weekday: 1, shiftId: 1, level: "unavailable" });
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/personal/tok-1/availability/1/1", { level: "unavailable" }]);
    });

    it("saves a new holiday with a POST and a removed holiday with a DELETE", async () => {
        const w = mountShow([{ id: 9, start_date: "2026-01-01", end_date: "2026-01-02", note: null }]);

        w.findComponent(AvailabilityGrid); // ensure mounted
        w.findComponent(HolidayList).vm.$emit("update:holidays", [
            { id: null, start_date: "2026-02-01", end_date: "2026-02-02", note: "new" },
        ]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "post",
            "/personal/tok-1/holidays",
            { start_date: "2026-02-01", end_date: "2026-02-02", note: "new" },
        ]);
        expect(routerCalls.some((c) => c[0] === "delete" && c[1] === "/personal/tok-1/holidays/9")).toBe(true);
    });

    it("attaches a newly answered question with a PUT", async () => {
        const w = mountShow([], {
            questions: [{ id: 5, text: "Weekend?" }],
            questionAnswers: [],
        });
        w.findComponent(QuestionChecklist).vm.$emit("update:answeredIds", [5]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/personal/tok-1/questions/5", { answer: true }]);
    });

    it("attaches a newly selected competence with a PUT and detaches with a DELETE", async () => {
        const w = mountShow([], {
            competences: [{ id: 1, name: "Forklift" }, { id: 2, name: "Cleanroom" }],
            competenceIds: [2],
        });
        w.findComponent(TagChecklist).vm.$emit("update:selectedIds", [1]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/personal/tok-1/competences/1", {}]);
        expect(routerCalls.some((c) => c[0] === "delete" && c[1] === "/personal/tok-1/competences/2")).toBe(true);
    });

    it("keeps Save enabled and marks the Availability tab on a failed availability save", async () => {
        failUrlsRef.current = ["/personal/tok-1/availability/1/1"];
        const w = mountShow([], { shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00" }] });
        w.findComponent(AvailabilityGrid).vm.$emit("update:availability", { weekday: 1, shiftId: 1, level: "unavailable" });
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();
        const availabilityTab = w.findAll("button").find((b) => b.text().includes("Availability"));
        expect(availabilityTab.find('[data-testid="tab-error-dot"]').exists()).toBe(true);
    });

    it("mirrors the employee page tabs, Information first", () => {
        const w = mountShow();
        expect(w.text()).toContain("Information");
        expect(w.text()).toContain("Details");
        expect(w.text()).toContain("Availability");
        expect(w.text()).toContain("Competences");
        expect(w.text()).toContain("Planning");
        expect(hidden(w, '[data-testid="panel-information"]')).toBe(false);
        expect(hidden(w, '[data-testid="panel-details"]')).toBe(true);
        expect(hidden(w, '[data-testid="panel-availability"]')).toBe(true);
    });

    it("renders the planned shifts list on the Planning tab", () => {
        const plannedShifts = [{
            weekStart: "2026-09-07",
            weekEnd: "2026-09-13",
            published: true,
            assignments: [{ date: "2026-09-08", workcenter_name: "Line 1", shift_name: "Early", start_time: "06:00", end_time: "14:00" }],
        }];
        const w = mountShow([], { plannedShifts });

        expect(w.get('[data-testid="panel-planning"]').text()).toContain("Line 1");
    });

    it("renders the shift note on the Information tab when set", () => {
        const w = mountShow([], { shiftNoteHtml: "<p>Allowances table here</p>" });
        const note = w.findComponent(ShiftNote);
        expect(note.exists()).toBe(true);
        expect(note.props("html")).toBe("<p>Allowances table here</p>");
        expect(w.get('[data-testid="panel-information"]').text()).toContain("Allowances table here");
    });

    it("renders no shift note when the prop is absent", () => {
        expect(mountShow().findComponent(ShiftNote).exists()).toBe(false);
    });

    it("has a Competences tab with the competence checklist", () => {
        const w = mountShow([], {
            competences: [{ id: 1, name: "Forklift" }],
            competenceIds: [1],
        });
        expect(w.text()).toContain("Competences");
        expect(w.findComponent(TagChecklist).props("selectedIds")).toEqual([1]);
    });

    it("shows read-only competences in a separate disabled checklist", () => {
        const w = mountShow([], {
            competences: [
                { id: 1, name: "Forklift", read_only: false },
                { id: 2, name: "Cleanroom", read_only: true },
            ],
            competenceIds: [2],
        });

        const lists = w.findAllComponents(TagChecklist);
        expect(lists).toHaveLength(2);
        expect(lists[0].props("items")).toEqual([{ id: 1, name: "Forklift", read_only: false }]);
        expect(lists[0].props("disabled")).toBe(false);
        expect(lists[1].props("items")).toEqual([{ id: 2, name: "Cleanroom", read_only: true }]);
        expect(lists[1].props("disabled")).toBe(true);
    });

    it("shows the questions checklist on the Availability tab", () => {
        const w = mountShow([], {
            questions: [{ id: 5, text: "Can we contact you to work in the weekend?" }],
            questionAnswers: [5],
        });

        const checklist = w.findComponent(QuestionChecklist);
        expect(checklist.exists()).toBe(true);
        expect(checklist.props("answeredIds")).toEqual([5]);
        expect(w.get('[data-testid="panel-availability"]').text()).toContain("Questions");
    });

    it("omits the questions section when no question is configured", () => {
        const w = mountShow();
        expect(w.findComponent(QuestionChecklist).exists()).toBe(false);
        expect(w.get('[data-testid="panel-availability"]').text()).not.toContain("Questions");
    });

    it("is fully editable by default: no lock notice, controls enabled", () => {
        const w = mountShow();
        expect(w.find('[data-testid="locked-notice"]').exists()).toBe(false);
        expect(w.findComponent(AvailabilityGrid).props("disabled")).toBe(false);
        expect(w.findComponent(HolidayList).props("disabled")).toBe(false);
        expect(w.findComponent(EmployeeFields).props("disabled")).toBe(false);
    });

    it("shows the lock notice and disables every control when editable is false", () => {
        const w = mountShow([], {
            editable: false,
            competences: [{ id: 1, name: "Forklift" }],
            competenceIds: [],
            questions: [{ id: 5, text: "Weekend?" }],
            questionAnswers: [],
        });

        expect(w.get('[data-testid="locked-notice"]').text()).toContain("closed by your planner");
        expect(w.findComponent(AvailabilityGrid).props("disabled")).toBe(true);
        expect(w.findComponent(HolidayList).props("disabled")).toBe(true);
        expect(w.findComponent(QuestionChecklist).props("disabled")).toBe(true);
        expect(w.findComponent(TagChecklist).props("disabled")).toBe(true);
        expect(w.findComponent(EmployeeFields).props("disabled")).toBe(true);
    });

    it("reveals the holiday list when the Availability tab is clicked", async () => {
        const w = mountShow();
        const tab = w.findAll("button").find((b) => b.text() === "Availability");
        await tab.trigger("click");
        await w.vm.$nextTick();

        expect(hidden(w, '[data-testid="panel-availability"]')).toBe(false);
        expect(hidden(w, '[data-testid="panel-information"]')).toBe(true);
    });
});
