import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const en = {
    "demand.title": "Demand",
    "demand.workcenter": "Workcenter",
    "demand.default.heading": "Default demand",
    "demand.column.shift": "Shift",
    "demand.weekday.mon": "Mon",
    "demand.weekday.tue": "Tue",
    "demand.weekday.wed": "Wed",
    "demand.weekday.thu": "Thu",
    "demand.weekday.fri": "Fri",
    "demand.weekday.sat": "Sat",
    "demand.weekday.sun": "Sun",
    "demand.add": "Add shift",
    "demand.select_workcenter": "Select a workcenter",
    "demand.select_shift": "Select a shift",
    "demand.delete": "Delete",
    "demand.list_empty": "No shifts yet.",
    "demand.calendar.legend.staffed": "Fully staffed",
    "demand.calendar.legend.open": "Open slots",
    "demand.calendar.legend.changed": "Changed for this date",
    "demand.specific.heading": "Specific demand",
    "demand.specific.hint": "Click a date to change its slots.",
    "demand.day.title": ":weekday :date",
    "demand.day.assigned": "Assigned",
    "demand.day.slots": "Slots",
    "demand.day.reset": "Reset to default",
    "availability.weekday_long.1": "Monday",
    "availability.weekday_long.3": "Wednesday",
    "demand.unsaved.body": "This workcenter has changes that are not saved.",
    "tabs.unsaved.title": "Unsaved changes",
    "tabs.unsaved.discard": "Discard changes",
    "tabs.unsaved.stay": "Stay",
    "app.save": "Save",
    "app.saving": "Saving…",
    "app.saved": "Saved",
    "app.cancel": "Cancel",
};

const pageState = vi.hoisted(() => ({ url: "/demand" }));

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
    const router = {
        put: respond("put"),
        post: respond("post"),
        delete: respond("delete"),
        get: (...args) => routerCalls.push(["get", ...args]),
        on: () => () => {},
    };
    return { routerCalls, failUrlsRef, router };
});

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en, auth: { user: { id: 7 } } }, url: pageState.url }),
}));

import Demand from "@/pages/Demand.vue";
import { SelectInput, NumberInput } from "@/components/ui/Input";
import DemandCalendar from "@/components/DemandCalendar.vue";
import Calendar from "@/components/ui/Calendar.vue";
import ConfirmDialog from "@/components/ui/ConfirmDialog.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const mountPage = (props = {}) =>
    mount(Demand, {
        props: {
            workcenters: [{ id: 1, name: "Line 1" }, { id: 2, name: "Line 2" }],
            workcenterId: 1,
            shifts: [
                { id: 9, name: "Early", start_time: "06:00", end_time: "14:00" },
                { id: 10, name: "Late", start_time: "14:00", end_time: "22:00" },
            ],
            defaults: [],
            overrides: [],
            assigned: [],
            ...props,
        },
        global: { stubs },
    });

beforeEach(() => {
    routerCalls.length = 0;
    failUrlsRef.current = [];
    pageState.url = "/demand";
    window.sessionStorage.clear();
});

const findSaveButton = (w) => w.findAll("button").find((b) => ["Save", "Saving…", "Saved"].includes(b.text()));
const findCancelButton = (w) => w.findAll("button").find((b) => b.text() === "Cancel");
const EARLY = { shift_id: 9, spots: [4, 4, 4, 4, 2, 0, 0] };
const addRowSelect = (w) => w.get('[data-testid="demand-add-row"]').findComponent(SelectInput);

describe("Demand", () => {
    it("places the page content in one card with Demand in the header", () => {
        const card = mountPage({ defaults: [EARLY] }).get('[data-testid="demand-card"]');
        expect(card.get('[data-testid="demand-card-header"]').text()).toBe("Demand");
        expect(card.find('[data-testid="demand-workcenter"]').exists()).toBe(true);
        expect(card.find('[data-testid="demand-default-card"]').exists()).toBe(true);
        expect(card.find('[data-testid="demand-calendar-section"]').exists()).toBe(true);
        expect(card.text()).toContain("Save");
    });

    it("selects the current workcenter and reloads the page for another one", async () => {
        const w = mountPage();
        const select = w.get('[data-testid="demand-workcenter"]').findComponent(SelectInput);
        expect(select.props("modelValue")).toBe(1);

        select.vm.$emit("update:modelValue", 2);
        await w.vm.$nextTick();

        expect(routerCalls).toContainEqual(["get", "/demand", { workcenter: 2 }]);
    });

    it("renders a row per shift of the workcenter with the shift name", () => {
        const w = mountPage({ defaults: [EARLY] });
        const rows = w.findAll('[data-testid="demand-default-row"]');
        expect(rows).toHaveLength(1);
        expect(rows[0].text()).toContain("Early");
        expect(w.text()).toContain("Default demand");
    });

    it("shows an empty state with no shifts", () => {
        expect(mountPage().text()).toContain("No shifts yet.");
    });

    it("lists only the shifts that the workcenter does not have yet in the add row", () => {
        const w = mountPage({ defaults: [EARLY] });
        expect(addRowSelect(w).props("options").map((o) => o.value)).toEqual([10]);
    });

    it("Save/Cancel are disabled with nothing changed", () => {
        const w = mountPage({ defaults: [EARLY] });
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(findCancelButton(w).attributes("disabled")).toBeDefined();
    });

    it("editing a spot cell changes local state, enables Save, and fires no request", async () => {
        const w = mountPage({ defaults: [EARLY] });
        w.findAllComponents(NumberInput)[0].vm.$emit("update:modelValue", 5);
        await w.vm.$nextTick();

        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();
        expect(routerCalls).toEqual([]);
    });

    it("saves a changed spot cell with a PUT on click", async () => {
        const w = mountPage({ defaults: [EARLY] });
        w.findAllComponents(NumberInput)[0].vm.$emit("update:modelValue", 5);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/demand/1/9", { spots: [5, 4, 4, 4, 2, 0, 0] }]);
    });

    it("adds a shift locally and saves it with a POST for the selected workcenter", async () => {
        const w = mountPage();
        addRowSelect(w).vm.$emit("update:modelValue", 9);
        await w.vm.$nextTick();
        await w.get('[data-testid="demand-add-row"]').get('[aria-label="Add shift"]').trigger("click");
        await w.vm.$nextTick();

        expect(w.findAll('[data-testid="demand-default-row"]')).toHaveLength(1);
        expect(routerCalls).toEqual([]);

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "post",
            "/demand",
            { workcenter_id: 1, shift_id: 9, spots: [0, 0, 0, 0, 0, 0, 0] },
        ]);
    });

    it("removes a shift locally and saves it with a DELETE on click", async () => {
        const w = mountPage({ defaults: [EARLY] });
        await w.get('[data-testid="demand-default-row"]').get('[aria-label="Delete"]').trigger("click");

        expect(w.findAll('[data-testid="demand-default-row"]')).toHaveLength(0);
        expect(routerCalls).toEqual([]);

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls.some((c) => c[0] === "delete" && c[1] === "/demand/1/9")).toBe(true);
    });

    it("Cancel reverts to the last-saved state without saving", async () => {
        const w = mountPage({ defaults: [EARLY] });
        w.findAllComponents(NumberInput)[0].vm.$emit("update:modelValue", 5);
        await w.vm.$nextTick();
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findCancelButton(w).trigger("click");
        await w.vm.$nextTick();

        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(routerCalls).toEqual([]);
    });

    it("colors the calendar from the slots and the assigned counts, with a border on an overridden day", async () => {
        const w = mountPage({
            defaults: [{ shift_id: 9, spots: [1, 1, 1, 1, 1, 1, 1] }],
            overrides: [{ shift_id: 9, date: "2026-10-07", spots: 0 }],
            assigned: [{ shift_id: 9, date: "2026-10-05", count: 1 }],
        });
        w.getComponent(Calendar).vm.$emit("change", { year: 2026, month: 10 });
        await w.vm.$nextTick();

        const props = w.getComponent(Calendar).props();
        expect(props.dayStates[5]).toBe("success");
        expect(props.dayStates[6]).toBe("warning");
        expect(props.dayStates[7]).toBe("muted");
        expect(props.dayBorders).toEqual({ 7: "solid" });
        expect(props.legenda).toEqual({ success: "Fully staffed", warning: "Open slots" });
        expect(props.borderLegenda).toEqual({ solid: "Changed for this date" });
    });

    it("recolors the calendar from pending default edits", async () => {
        const w = mountPage({ defaults: [{ shift_id: 9, spots: [0, 0, 0, 0, 0, 0, 0] }] });
        w.getComponent(Calendar).vm.$emit("change", { year: 2026, month: 10 });
        await w.vm.$nextTick();
        expect(w.getComponent(Calendar).props("dayStates")[5]).toBe("muted");

        w.findAllComponents(NumberInput)[0].vm.$emit("update:modelValue", 2);
        await w.vm.$nextTick();

        expect(w.getComponent(Calendar).props("dayStates")[5]).toBe("warning");
    });

    it("selects a clicked day and deselects it on a second click", async () => {
        const w = mountPage();
        const calendar = w.getComponent(Calendar);
        calendar.vm.$emit("change", { year: 2026, month: 10 });
        calendar.vm.$emit("day-click", { year: 2026, month: 10, day: 5 });
        await w.vm.$nextTick();
        expect(w.getComponent(DemandCalendar).props("selectedDate")).toBe("2026-10-05");
        expect(w.getComponent(Calendar).props("ringDay")).toBe(5);

        w.getComponent(Calendar).vm.$emit("day-click", { year: 2026, month: 10, day: 5 });
        await w.vm.$nextTick();
        expect(w.getComponent(DemandCalendar).props("selectedDate")).toBe(null);
    });

    describe("date card", () => {
        const WEEK = { shift_id: 9, spots: [1, 1, 1, 1, 1, 1, 1] };

        async function selectDate(w, day) {
            w.getComponent(Calendar).vm.$emit("change", { year: 2026, month: 10 });
            w.getComponent(Calendar).vm.$emit("day-click", { year: 2026, month: 10, day });
            await w.vm.$nextTick();
        }

        const dateInput = (w, shiftId) => w.get(`[data-testid="demand-date-row-${shiftId}"]`).findComponent(NumberInput);

        it("shows the hint while no date is selected", () => {
            window.sessionStorage.setItem("demand.date.7", "");
            const w = mountPage({ defaults: [WEEK] });
            expect(w.get('[data-testid="demand-date-card"]').text()).toContain("Specific demand");
            expect(w.get('[data-testid="demand-date-card"]').text()).toContain("Click a date to change its slots.");
        });

        it("shows a row per shift with the assigned count and the slots, at least the assigned count", async () => {
            const w = mountPage({
                defaults: [WEEK],
                assigned: [{ shift_id: 9, date: "2026-10-05", count: 1 }],
            });
            await selectDate(w, 5);

            const card = w.get('[data-testid="demand-date-card"]');
            expect(card.text()).toContain("Monday 05-10-2026");
            const row = w.get('[data-testid="demand-date-row-9"]');
            expect(row.text()).toContain("Early");
            expect(row.get('[data-testid="demand-date-assigned-9"]').text()).toBe("1");
            expect(dateInput(w, 9).props("modelValue")).toBe(1);
            expect(dateInput(w, 9).props("min")).toBe(1);
        });

        it("keeps a date change pending, marks the day and saves it with a PUT", async () => {
            const w = mountPage({ defaults: [WEEK] });
            await selectDate(w, 5);

            dateInput(w, 9).vm.$emit("update:modelValue", 3);
            await w.vm.$nextTick();

            expect(routerCalls).toEqual([]);
            expect(w.getComponent(Calendar).props("dayBorders")).toEqual({ 5: "solid" });
            expect(w.get('[data-testid="demand-date-row-9"]').find('[data-overridden="true"]').exists()).toBe(true);

            await findSaveButton(w).trigger("click");
            await flushPromises();

            expect(routerCalls).toContainEqual(["put", "/demand/1/9/2026-10-05", { spots: 3 }]);
        });

        it("removes the override when the value equals the weekday default", async () => {
            const w = mountPage({ defaults: [WEEK], overrides: [{ shift_id: 9, date: "2026-10-07", spots: 0 }] });
            await selectDate(w, 7);

            dateInput(w, 9).vm.$emit("update:modelValue", 1);
            await w.vm.$nextTick();
            expect(w.getComponent(Calendar).props("dayBorders")).toEqual({});

            await findSaveButton(w).trigger("click");
            await flushPromises();

            expect(routerCalls).toContainEqual(["delete", "/demand/1/9/2026-10-07"]);
        });

        it("offers Reset to default only for a date with overrides, and saves it with a DELETE", async () => {
            const w = mountPage({ defaults: [WEEK], overrides: [{ shift_id: 9, date: "2026-10-07", spots: 0 }] });
            await selectDate(w, 5);
            expect(w.find('[data-testid="demand-date-reset"]').exists()).toBe(false);

            await selectDate(w, 7);
            await w.get('[data-testid="demand-date-reset"]').trigger("click");
            expect(dateInput(w, 9).props("modelValue")).toBe(1);
            expect(w.find('[data-testid="demand-date-reset"]').exists()).toBe(false);

            await findSaveButton(w).trigger("click");
            await flushPromises();

            expect(routerCalls).toContainEqual(["delete", "/demand/1/9/2026-10-07"]);
        });

        it("Cancel reverts a pending date change", async () => {
            const w = mountPage({ defaults: [WEEK] });
            await selectDate(w, 5);
            dateInput(w, 9).vm.$emit("update:modelValue", 3);
            await w.vm.$nextTick();

            await findCancelButton(w).trigger("click");
            await w.vm.$nextTick();

            expect(dateInput(w, 9).props("modelValue")).toBe(1);
            expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        });

        it("drops the pending date changes of a removed shift", async () => {
            const w = mountPage({ defaults: [WEEK] });
            await selectDate(w, 5);
            dateInput(w, 9).vm.$emit("update:modelValue", 3);
            await w.vm.$nextTick();
            await w.get('[data-testid="demand-default-row"]').get('[aria-label="Delete"]').trigger("click");

            await findSaveButton(w).trigger("click");
            await flushPromises();

            expect(routerCalls.filter((c) => c[1].startsWith("/demand/1/9/"))).toEqual([]);
        });
    });

    describe("selection", () => {
        const workcenterSelect = (w) => w.get('[data-testid="demand-workcenter"]').findComponent(SelectInput);

        it("stores the shown workcenter for the user", () => {
            mountPage({ workcenterId: 2 });
            expect(window.sessionStorage.getItem("demand.workcenter.7")).toBe("2");
        });

        it("restores the stored workcenter on a visit without a workcenter in the URL", () => {
            window.sessionStorage.setItem("demand.workcenter.7", "2");
            mountPage();
            expect(routerCalls).toContainEqual(["get", "/demand", { workcenter: 2 }, { replace: true }]);
        });

        it("lets an explicit workcenter in the URL win", () => {
            window.sessionStorage.setItem("demand.workcenter.7", "2");
            pageState.url = "/demand?workcenter=1";
            mountPage();
            expect(routerCalls).toEqual([]);
            expect(window.sessionStorage.getItem("demand.workcenter.7")).toBe("1");
        });

        it("ignores a stored workcenter that is no longer active", () => {
            window.sessionStorage.setItem("demand.workcenter.7", "99");
            mountPage();
            expect(routerCalls).toEqual([]);
        });

        it("selects today without a stored day", () => {
            const w = mountPage();
            const today = new Date();
            const iso = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
            expect(w.getComponent(DemandCalendar).props("selectedDate")).toBe(iso);
        });

        it("restores the stored day and opens its month", () => {
            window.sessionStorage.setItem("demand.date.7", "2026-12-24");
            const w = mountPage();
            expect(w.getComponent(DemandCalendar).props("selectedDate")).toBe("2026-12-24");
            expect(w.getComponent(Calendar).props("month")).toBe(12);
        });

        it("restores a deselected day as no selection, and an invalid one as today", () => {
            window.sessionStorage.setItem("demand.date.7", "");
            expect(mountPage().getComponent(DemandCalendar).props("selectedDate")).toBe(null);

            window.sessionStorage.setItem("demand.date.7", "2026-02-31");
            expect(mountPage().getComponent(DemandCalendar).props("selectedDate")).not.toBe("2026-02-31");
        });

        it("stores a newly selected or deselected day", async () => {
            const w = mountPage();
            w.getComponent(Calendar).vm.$emit("change", { year: 2026, month: 10 });
            w.getComponent(Calendar).vm.$emit("day-click", { year: 2026, month: 10, day: 5 });
            await w.vm.$nextTick();
            expect(window.sessionStorage.getItem("demand.date.7")).toBe("2026-10-05");

            w.getComponent(Calendar).vm.$emit("day-click", { year: 2026, month: 10, day: 5 });
            await w.vm.$nextTick();
            expect(window.sessionStorage.getItem("demand.date.7")).toBe("");
        });

        it("asks to stay or discard before switching workcenter with unsaved edits", async () => {
            const w = mountPage({ defaults: [EARLY] });
            w.findAllComponents(NumberInput)[0].vm.$emit("update:modelValue", 5);
            await w.vm.$nextTick();

            workcenterSelect(w).vm.$emit("update:modelValue", 2);
            await w.vm.$nextTick();
            const dialog = w.getComponent(ConfirmDialog);
            expect(dialog.props("open")).toBe(true);
            expect(routerCalls).toEqual([]);

            dialog.vm.$emit("cancel");
            await w.vm.$nextTick();
            expect(w.getComponent(ConfirmDialog).props("open")).toBe(false);
            expect(routerCalls).toEqual([]);

            workcenterSelect(w).vm.$emit("update:modelValue", 2);
            await w.vm.$nextTick();
            w.getComponent(ConfirmDialog).vm.$emit("confirm");
            await w.vm.$nextTick();
            expect(routerCalls).toContainEqual(["get", "/demand", { workcenter: 2 }]);
            expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        });
    });
});
