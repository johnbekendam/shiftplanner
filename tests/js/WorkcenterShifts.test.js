import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const en = {
    "workcenter_shifts.title": "Workcenter Shifts",
    "workcenter_shifts.column.workcenter": "Workcenter",
    "workcenter_shifts.column.shift": "Shift",
    "workcenter_shifts.weekday.mon": "Mon",
    "workcenter_shifts.weekday.tue": "Tue",
    "workcenter_shifts.weekday.wed": "Wed",
    "workcenter_shifts.weekday.thu": "Thu",
    "workcenter_shifts.weekday.fri": "Fri",
    "workcenter_shifts.weekday.sat": "Sat",
    "workcenter_shifts.weekday.sun": "Sun",
    "workcenter_shifts.add": "Add assignment",
    "workcenter_shifts.select_workcenter": "Select a workcenter",
    "workcenter_shifts.select_shift": "Select a shift",
    "workcenter_shifts.delete": "Delete",
    "workcenter_shifts.list_empty": "No assignments yet.",
    "app.save": "Save",
    "app.saving": "Saving…",
    "app.saved": "Saved",
    "app.cancel": "Cancel",
};

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
    usePage: () => ({ props: { translations: en } }),
}));

import WorkcenterShifts from "@/pages/WorkcenterShifts.vue";
import { SelectInput, NumberInput } from "@/components/ui/Input";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const mountPage = (props = {}) =>
    mount(WorkcenterShifts, {
        props: {
            workcenters: [{ id: 1, name: "Line 1" }],
            shifts: [{ id: 9, name: "Early", start_time: "06:00", end_time: "14:00" }],
            assignments: [],
            ...props,
        },
        global: { stubs },
    });

beforeEach(() => {
    routerCalls.length = 0;
    failUrlsRef.current = [];
});

const findSaveButton = (w) => w.findAll("button").find((b) => ["Save", "Saving…", "Saved"].includes(b.text()));
const findCancelButton = (w) => w.findAll("button").find((b) => b.text() === "Cancel");

describe("WorkcenterShifts", () => {
    it("renders a row per assignment with the workcenter and shift name", () => {
        const w = mountPage({
            assignments: [{ workcenter_id: 1, shift_id: 9, spots: [4, 4, 4, 4, 2, 0, 0] }],
        });
        const rows = w.findAll('[data-testid="workcenter-shift-row"]');
        expect(rows).toHaveLength(1);
        expect(rows[0].text()).toContain("Line 1");
        expect(rows[0].text()).toContain("Early");
    });

    it("shows an empty state with no assignments", () => {
        expect(mountPage().text()).toContain("No assignments yet.");
    });

    it("Save/Cancel are disabled with nothing changed", () => {
        const w = mountPage({
            assignments: [{ workcenter_id: 1, shift_id: 9, spots: [4, 4, 4, 4, 2, 0, 0] }],
        });
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(findCancelButton(w).attributes("disabled")).toBeDefined();
    });

    it("editing a spot cell changes local state, enables Save, and fires no request", async () => {
        const w = mountPage({
            assignments: [{ workcenter_id: 1, shift_id: 9, spots: [4, 4, 4, 4, 2, 0, 0] }],
        });
        w.findAllComponents(NumberInput)[0].vm.$emit("update:modelValue", 5);
        await w.vm.$nextTick();

        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();
        expect(routerCalls).toEqual([]);
    });

    it("saves a changed spot cell with a PUT on click", async () => {
        const w = mountPage({
            assignments: [{ workcenter_id: 1, shift_id: 9, spots: [4, 4, 4, 4, 2, 0, 0] }],
        });
        w.findAllComponents(NumberInput)[0].vm.$emit("update:modelValue", 5);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "put",
            "/workcenter-shifts/1/9",
            { spots: [5, 4, 4, 4, 2, 0, 0] },
        ]);
    });

    it("adds a new row via the two selects and appends it locally, firing no request", async () => {
        const w = mountPage();
        const addRow = w.get('[data-testid="workcenter-shift-add-row"]');
        addRow.findComponent(SelectInput).vm.$emit("update:modelValue", 1);
        addRow.findAllComponents(SelectInput)[1].vm.$emit("update:modelValue", 9);
        await w.vm.$nextTick();

        await addRow.get('[aria-label="Add assignment"]').trigger("click");
        await w.vm.$nextTick();

        expect(w.findAll('[data-testid="workcenter-shift-row"]')).toHaveLength(1);
        expect(routerCalls).toEqual([]);
    });

    it("saves a new row with a POST on click", async () => {
        const w = mountPage();
        const addRow = w.get('[data-testid="workcenter-shift-add-row"]');
        addRow.findComponent(SelectInput).vm.$emit("update:modelValue", 1);
        addRow.findAllComponents(SelectInput)[1].vm.$emit("update:modelValue", 9);
        await w.vm.$nextTick();
        await addRow.get('[aria-label="Add assignment"]').trigger("click");
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "post",
            "/workcenter-shifts",
            { workcenter_id: 1, shift_id: 9, spots: [0, 0, 0, 0, 0, 0, 0] },
        ]);
    });

    it("removes a row locally with no confirm, and saves it with a DELETE on click", async () => {
        const w = mountPage({
            assignments: [{ workcenter_id: 1, shift_id: 9, spots: [4, 4, 4, 4, 2, 0, 0] }],
        });
        await w.get('[aria-label="Delete"]').trigger("click");

        expect(w.findAll('[data-testid="workcenter-shift-row"]')).toHaveLength(0);
        expect(routerCalls).toEqual([]);

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls.some((c) => c[0] === "delete" && c[1] === "/workcenter-shifts/1/9")).toBe(true);
    });

    it("Cancel reverts to the last-saved state without saving", async () => {
        const w = mountPage({
            assignments: [{ workcenter_id: 1, shift_id: 9, spots: [4, 4, 4, 4, 2, 0, 0] }],
        });
        w.findAllComponents(NumberInput)[0].vm.$emit("update:modelValue", 5);
        await w.vm.$nextTick();
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findCancelButton(w).trigger("click");
        await w.vm.$nextTick();

        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(routerCalls).toEqual([]);
    });
});
