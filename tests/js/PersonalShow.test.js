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
    "planning.shift_times": "Working times",
    "planning.add_to_calendar": "Add to calendar",
    "planning.details.title": "Shift details",
    "planning.details.hours": "Working hours",
    "planning.details.close": "Close",
    "planning.calendar_contact": "Contact",
    "personal.title": "Your working hours",
    "personal.action.save": "Save",
    "personal.action.saving": "Saving…",
    "personal.action.cancel": "Cancel",
    "personal.action.withdraw": "Withdraw",
    "personal.withdraw.title": "Withdraw",
    "personal.withdraw.info": "You cannot withdraw on this page. Contact your business line responsible.",
    "personal.withdraw.contact_planner": "You cannot withdraw on this page. Contact your planner.",
    "personal.withdraw.close": "Close",
    "app.cancel": "Cancel",
    "personal.saved": "Saved",
    "personal.locked_notice": "Changes are currently closed by your planner.",
    "availability.tab.information": "Information",
    "availability.tab.details": "Details",
    "availability.tab.availability": "Availability",
    "availability.tab.questions": "Questions",
    "availability.day.title": ":weekday :date",
    "availability.default_week.heading": "Default week",
    "availability.weekday_long.1": "Monday",
    "availability.tab.settings": "Settings",
    "availability.info.empty": "No information has been provided yet.",
    "availability.info.cta": "Please update your details, availability and competences on the different tabs.",
    "availability.hours_warning.not_preferred": "You will be planned on not-preferred hours.",
    "availability.hours_warning.insufficient": "Your available time totals :available hours per week, below your target of :target hours.",
    "availability.holidays.empty": "No holidays yet.",
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

const { downloadIcs } = vi.hoisted(() => ({ downloadIcs: vi.fn() }));
vi.mock("@/utils/shiftIcs", async (importOriginal) => ({ ...(await importOriginal()), downloadIcs }));

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
    available_from: "",
    errors: {},
    processing: false,
    recentlySuccessful: false,
    _defaults: {},
    _transform: null,
    get isDirty() {
        return this.weekly_hours !== this._defaults.weekly_hours
            || this.business_line_id !== this._defaults.business_line_id
            || this.available_from !== this._defaults.available_from;
    },
    defaults() {
        this._defaults = {
            weekly_hours: this.weekly_hours,
            business_line_id: this.business_line_id,
            available_from: this.available_from,
        };
    },
    reset() {
        this.weekly_hours = this._defaults.weekly_hours;
        this.business_line_id = this._defaults.business_line_id;
        this.available_from = this._defaults.available_from;
    },
    clearErrors() {
        this.errors = {};
    },
    transform(fn) {
        this._transform = fn;
        return this;
    },
    put(url, opts) {
        const data = {
            weekly_hours: this.weekly_hours,
            business_line_id: this.business_line_id,
            available_from: this.available_from,
        };
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
import DateInput from "@/components/ui/Input/Date.vue";
import AvailabilityCalendar from "@/components/AvailabilityCalendar.vue";
import DateAvailabilityGrid from "@/components/DateAvailabilityGrid.vue";

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
            shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }],
            availability: [
                { weekday: 2, shift_id: 1, level: "available" },
                { weekday: 3, shift_id: 1, level: "available" },
                { weekday: 4, shift_id: 1, level: "available" },
                { weekday: 5, shift_id: 1, level: "available" },
            ],
            employee: { first_name: "J", last_name: "L", email: "j@l.c", weekly_hours: 20, business_line_id: null },
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

    it("clicking Withdraw opens a card that names the business line responsible, and deletes nothing", async () => {
        const w = mountShow([], { businessLineResponsible: { name: "Rita Lead" } });
        expect(w.find('[role="dialog"]').exists()).toBe(false);

        await findWithdrawButton(w).trigger("click");

        const dialog = w.get('[role="dialog"]');
        expect(dialog.text()).toContain("You cannot withdraw on this page. Contact your business line responsible.");
        expect(dialog.text()).toContain("Rita Lead");
        expect(dialog.find("a").exists()).toBe(false);
        expect(dialog.findAll("button").some((b) => b.text() === "Yes, withdraw")).toBe(false);
        expect(routerCalls).toEqual([]);
    });

    it("tells the employee to contact the planner when there is no business line responsible", async () => {
        const w = mountShow([], { businessLineResponsible: null });

        await findWithdrawButton(w).trigger("click");

        const dialog = w.get('[role="dialog"]');
        expect(dialog.text()).toContain("You cannot withdraw on this page. Contact your planner.");
        expect(dialog.find("a").exists()).toBe(false);
    });

    it("closes the withdraw card with Close", async () => {
        const w = mountShow();
        await findWithdrawButton(w).trigger("click");

        await w.get('[role="dialog"]').findAll("button").find((b) => b.text() === "Close").trigger("click");

        expect(w.find('[role="dialog"]').exists()).toBe(false);
        expect(routerCalls).toEqual([]);
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
        expect(form.lastPut.data).toEqual({ weekly_hours: 40, business_line_id: null, available_from: null });
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
        const w = mountShow([], { shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }] });
        await selectDefaultWeek(w);
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

        expect(form.lastPut.data).toEqual({ available_from: null, weekly_hours: 24, business_line_id: 5 });
    });

    it("saves an availability-grid change with one PUT per changed cell", async () => {
        const w = mountShow([], {
            shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }],
        });
        await selectDefaultWeek(w);
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
        const w = mountShow([], { shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }] });
        await selectDefaultWeek(w);
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

    it("renders the planned shifts table on the Planning tab", () => {
        const plannedShifts = [{
            weekStart: "2026-09-07",
            weekEnd: "2026-09-13",
            assignments: [{
                date: "2026-09-08", workcenter_name: "Line 1", responsible: "Jane Doe", shift_name: "Early",
                start_time: "06:00:00", end_time: "14:00:00", published: true,
            }],
        }];
        const w = mountShow([], { plannedShifts });

        const cells = w.get('[data-testid="panel-planning"]').findAll("tbody tr td").map((td) => td.text());
        expect(cells).toEqual(["37", "Tuesday", "Early", "Line 1", "Jane Doe", ""]);
    });

    it("opens a shift card from a table row with an Add to calendar button that downloads the shift", async () => {
        downloadIcs.mockClear();
        const plannedShifts = [{
            weekStart: "2026-09-07",
            weekEnd: "2026-09-13",
            assignments: [{
                date: "2026-09-08", workcenter_name: "Line 1", responsible: "Jane Doe", shift_name: "Early",
                start_time: "06:00:00", end_time: "14:00:00", published: true,
            }],
        }];
        const w = mountShow([], { plannedShifts });

        await w.get('[data-testid="panel-planning"] tbody tr').trigger("click");
        const dialog = w.get('[role="dialog"]');
        expect(dialog.text()).toContain("06:00–14:00");
        await dialog.findAll("button").find((b) => b.text() === "Add to calendar").trigger("click");

        expect(downloadIcs).toHaveBeenCalledTimes(1);
        const [filename, content] = downloadIcs.mock.calls[0];
        expect(filename).toBe("shift-08-09-2026.ics");
        expect(content).toContain("DTSTART:20260908T060000");
        expect(content).toContain("SUMMARY:Early – Line 1");
        expect(content).toContain("DESCRIPTION:Contact: Jane Doe");
    });

    it("lists the working times of the planned shifts once each, ordered by start time", () => {
        const assignment = (date, shift_name, start_time, end_time) => ({
            date, workcenter_name: "Line 1", shift_name, start_time, end_time, published: true,
        });
        const plannedShifts = [{
            weekStart: "2026-09-07",
            weekEnd: "2026-09-13",
            assignments: [
                assignment("2026-09-08", "Late", "14:00:00", "22:00:00"),
                assignment("2026-09-09", "Early", "06:00:00", "14:00:00"),
                assignment("2026-09-10", "Late", "14:00:00", "22:00:00"),
            ],
        }];
        const w = mountShow([], { plannedShifts });

        const items = w.get('[data-testid="planning-shift-times"]').findAll("li").map((li) => li.text());
        expect(items).toEqual(["Early 06:00–14:00", "Late 14:00–22:00"]);
        expect(w.get('[data-testid="planning-shift-times"]').text()).toContain("Working times");
    });

    it("shows no working times when nothing is planned", () => {
        const w = mountShow([], { plannedShifts: [] });

        expect(w.find('[data-testid="planning-shift-times"]').exists()).toBe(false);
    });

    it("puts the working times above the schedule note", () => {
        const plannedShifts = [{
            weekStart: "2026-09-07",
            weekEnd: "2026-09-13",
            assignments: [{
                date: "2026-09-08", workcenter_name: "Line 1", shift_name: "Early",
                start_time: "06:00", end_time: "14:00", published: true,
            }],
        }];
        const w = mountShow([], { plannedShifts, scheduleNoteHtml: "<p>Schedule remarks</p>" });

        const html = w.get('[data-testid="panel-planning"]').html();
        expect(html.indexOf("planning-shift-times")).toBeLessThan(html.indexOf("Schedule remarks"));
        expect(html.indexOf("<table")).toBeLessThan(html.indexOf("planning-shift-times"));
    });

    it("shows the schedule note below the table on the Planning tab", () => {
        const w = mountShow([], { scheduleNoteHtml: "<p>Schedule remarks</p>" });
        const panel = w.get('[data-testid="panel-planning"]');

        expect(panel.text()).toContain("Schedule remarks");
        expect(panel.findComponent(ShiftNote).props("html")).toBe("<p>Schedule remarks</p>");
    });

    it("shows no note on the Planning tab when the schedule note is empty", () => {
        const w = mountShow([], { scheduleNoteHtml: null });

        expect(w.get('[data-testid="panel-planning"]').findComponent(ShiftNote).exists()).toBe(false);
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

    it("shows the questions checklist on its own tab, after Availability", () => {
        const w = mountShow([], {
            questions: [{ id: 5, text: "Can we contact you to work in the weekend?" }],
            questionAnswers: [5],
        });

        const checklist = w.findComponent(QuestionChecklist);
        expect(checklist.exists()).toBe(true);
        expect(checklist.props("answeredIds")).toEqual([5]);
        expect(w.get('[data-testid="panel-questions"]').findComponent(QuestionChecklist).exists()).toBe(true);
        expect(w.get('[data-testid="panel-availability"]').findComponent(QuestionChecklist).exists()).toBe(false);
        const tabs = w.findAll("button").map((button) => button.text());
        expect(tabs.indexOf("Questions")).toBe(tabs.indexOf("Availability") + 1);
    });

    it("omits the questions tab when no question is configured", () => {
        const w = mountShow();
        expect(w.findComponent(QuestionChecklist).exists()).toBe(false);
        expect(w.find('[data-testid="panel-questions"]').exists()).toBe(false);
        expect(w.findAll("button").map((button) => button.text())).not.toContain("Questions");
    });

    it("is fully editable by default: no lock notice, controls enabled", async () => {
        const w = mountShow();
        await selectDefaultWeek(w);
        expect(w.find('[data-testid="locked-notice"]').exists()).toBe(false);
        expect(w.findComponent(AvailabilityGrid).props("disabled")).toBe(false);
        expect(w.findComponent(HolidayList).props("disabled")).toBe(false);
        expect(w.findComponent(EmployeeFields).props("disabled")).toBe(false);
    });

    it("shows the lock notice and disables every control when editable is false", async () => {
        const w = mountShow([], {
            editable: false,
            competences: [{ id: 1, name: "Forklift" }],
            competenceIds: [],
            questions: [{ id: 5, text: "Weekend?" }],
            questionAnswers: [],
        });
        await selectDefaultWeek(w);

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

    it("saves the start date with the personal endpoint and passes it to the calendar", async () => {
        const w = mountShow([], {
            employee: { first_name: "J", last_name: "L", email: "j@l.c", weekly_hours: 24, business_line_id: null, available_from: "2026-11-02" },
        });
        const panel = w.get('[data-testid="panel-availability"]');
        expect(panel.getComponent(DateInput).props("modelValue")).toBe("2026-11-02");
        expect(panel.getComponent(AvailabilityCalendar).props("availableFrom")).toBe("2026-11-02");

        panel.getComponent(DateInput).vm.$emit("update:modelValue", "2026-12-01");
        await w.vm.$nextTick();
        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(form.lastPut.data).toEqual({ weekly_hours: 24, business_line_id: null, available_from: "2026-12-01" });
    });

    it("sends an empty start date as null", async () => {
        const w = mountShow([], {
            employee: { first_name: "J", last_name: "L", email: "j@l.c", weekly_hours: 24, business_line_id: null, available_from: "2026-11-02" },
        });

        w.getComponent(DateInput).vm.$emit("update:modelValue", "");
        await w.vm.$nextTick();
        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(form.lastPut.data.available_from).toBeNull();
    });

    it("gives the calendar the grouped overrides and saves an applied day with one PUT per date", async () => {
        const w = mountShow([], {
            availabilityOverrides: [{ date: "2026-10-05", shift_id: null, level: "unavailable" }],
        });
        const calendar = w.getComponent(AvailabilityCalendar);
        expect(calendar.props("overrides")).toEqual({ "2026-10-05": { blocked: true, shifts: {} } });

        await applyDay(w, { date: "2026-10-06", blocked: false, shifts: { 1: "available" } });
        await applyDay(w, { date: "2026-10-05", blocked: false, shifts: {} });
        await w.vm.$nextTick();

        expect(calendar.props("overrides")).toEqual({ "2026-10-06": { blocked: false, shifts: { 1: "available" } } });
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toEqual([
            ["put", "/personal/tok-1/availability/dates/2026-10-06", { blocked: false, shifts: { 1: "available" } }],
            ["put", "/personal/tok-1/availability/dates/2026-10-05", { blocked: false, shifts: {} }],
        ]);
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
    });

    it("drops a day change that returns to the saved state", async () => {
        const w = mountShow([], { availabilityOverrides: [{ date: "2026-10-05", shift_id: null, level: "unavailable" }] });
        const calendar = w.getComponent(AvailabilityCalendar);

        await applyDay(w, { date: "2026-10-05", blocked: false, shifts: {} });
        await applyDay(w, { date: "2026-10-05", blocked: true, shifts: {} });
        await w.vm.$nextTick();

        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
    });

    it("Cancel discards a pending day change", async () => {
        const w = mountShow();
        const calendar = w.getComponent(AvailabilityCalendar);

        await applyDay(w, { date: "2026-10-06", blocked: true, shifts: {} });
        await w.vm.$nextTick();
        await findCancelButton(w).trigger("click");

        expect(calendar.props("overrides")).toEqual({});
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
    });

    it("keeps a failed day change pending and marks the Availability tab", async () => {
        failUrlsRef.current = ["/personal/tok-1/availability/dates/2026-10-06"];
        const w = mountShow();

        await applyDay(w, { date: "2026-10-06", blocked: true, shifts: {} });
        await w.vm.$nextTick();
        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();
    });

    it("puts the calendar left and shows the whole default week only after the weekday header is picked", async () => {
        const w = mountShow([], { shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }] });
        const panel = w.get('[data-testid="panel-availability"]');
        const sections = panel.findAll('[data-testid="availability-calendar-section"], [data-testid="default-week-section"]');
        expect(sections.map((x) => x.attributes("data-testid"))).toEqual(["availability-calendar-section", "default-week-section"]);
        expect(panel.findComponent(AvailabilityGrid).exists()).toBe(false);
        expect(panel.find('[data-testid="default-week-hint"]').exists()).toBe(true);

        await selectDefaultWeek(w);

        expect(panel.getComponent(AvailabilityCalendar).props("defaultWeekSelected")).toBe(true);
        expect(panel.findAll('[data-testid^="cell-"]').map((c) => c.attributes("data-testid"))).toEqual([
            "cell-1-1", "cell-2-1", "cell-3-1", "cell-4-1", "cell-5-1",
        ]);
        expect(panel.find('[data-testid="default-week-hint"]').exists()).toBe(false);

        await selectDefaultWeek(w, false);
        expect(panel.findComponent(AvailabilityGrid).exists()).toBe(false);
    });

    it("keeps a pending default edit visible after hiding and showing the default week", async () => {
        const w = mountShow([], { shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }] });
        await selectDefaultWeek(w);
        w.getComponent(AvailabilityGrid).vm.$emit("update:availability", { weekday: 1, shiftId: 1, level: "unavailable" });
        await selectDefaultWeek(w, false);
        await selectDefaultWeek(w);

        expect(w.get('[data-testid="cell-1-1"]').classes()).toContain("bg-(--color-badge-error-bg)");
    });


    it("replaces the default week with the schedule of a clicked date", async () => {
        const w = mountShow([], {
            shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }],
            availability: [{ weekday: 1, shift_id: 1, level: "available" }],
            availabilityOverrides: [{ date: "2026-10-05", shift_id: 1, level: "unavailable" }],
        });
        const calendar = w.getComponent(AvailabilityCalendar);

        await selectDefaultWeek(w);
        expect(w.findComponent(AvailabilityGrid).exists()).toBe(true);

        calendar.vm.$emit("update:selectedDate", "2026-10-05");
        calendar.vm.$emit("update:defaultWeekSelected", false);
        await w.vm.$nextTick();

        expect(w.findComponent(AvailabilityGrid).exists()).toBe(false);
        const grid = w.getComponent(DateAvailabilityGrid);
        expect(grid.props("day")).toMatchObject({ date: "2026-10-05", changed: true });
        expect(grid.props("day").shifts[0]).toMatchObject({ defaultLevel: "available", override: "unavailable" });
        expect(w.get('[data-testid="default-week-section"]').text()).toContain("05-10-2026");

        await applyDay(w, { date: "2026-10-05", blocked: false, shifts: {} });
        expect(w.getComponent(DateAvailabilityGrid).props("day").changed).toBe(false);
    });

    it("shows the default week or the date in the header of the availability card", async () => {
        const w = mountShow([], { shifts: [{ id: 1, name: "Day", start_time: "08:00", end_time: "12:00", weekdays: [1, 2, 3, 4, 5] }] });
        const section = () => w.get('[data-testid="default-week-section"]');

        expect(section().find('[data-testid="availability-card-header"]').exists()).toBe(false);
        expect(section().get('[data-testid="default-week-hint"]').exists()).toBe(true);

        await selectDefaultWeek(w);
        expect(section().get('[data-testid="availability-card-header"]').text()).toBe("Default week");

        w.getComponent(AvailabilityCalendar).vm.$emit("update:defaultWeekSelected", false);
        w.getComponent(AvailabilityCalendar).vm.$emit("update:selectedDate", "2026-10-05");
        await w.vm.$nextTick();
        expect(section().get('[data-testid="availability-card-header"]').text()).toBe("Monday 05-10-2026");
    });
});
