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
    usePage: () => ({ props: { translations: en } }),
}));

import Demand from "@/pages/Demand.vue";
import { SelectInput, NumberInput } from "@/components/ui/Input";

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
});

const findSaveButton = (w) => w.findAll("button").find((b) => ["Save", "Saving…", "Saved"].includes(b.text()));
const findCancelButton = (w) => w.findAll("button").find((b) => b.text() === "Cancel");
const EARLY = { shift_id: 9, spots: [4, 4, 4, 4, 2, 0, 0] };
const addRowSelect = (w) => w.get('[data-testid="demand-add-row"]').findComponent(SelectInput);

describe("Demand", () => {
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
});
