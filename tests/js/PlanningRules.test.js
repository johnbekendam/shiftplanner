import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const en = {
    "planning_rules.title": "Planning rules",
    "planning_rules.type": "Rule type",
    "planning_rules.type.max_hours_per_week": "Max hours per week",
    "planning_rules.type.max_shifts_per_day": "Max shifts per day",
    "planning_rules.type.not_preferred_shift": "Not-preferred-shift assignment",
    "planning_rules.type.competence_required": "Competence required",
    "planning_rules.type.business_line_preference": "Business-line preference",
    "planning_rules.mode": "Mode",
    "planning_rules.mode.hard": "Hard",
    "planning_rules.mode.soft": "Soft",
    "planning_rules.severity": "Severity (1-10)",
    "planning_rules.max_hours_per_week_hint": "Capped at each employee's own weekly hours.",
    "planning_rules.not_preferred_shift_hint": "Applies to not-preferred shifts.",
    "planning_rules.select_type": "Select a rule type",
    "planning_rules.select_workcenter": "Select a workcenter",
    "planning_rules.select_shift": "Select a shift",
    "planning_rules.select_competence": "Select a competence",
    "planning_rules.add": "Add rule",
    "planning_rules.delete": "Delete rule",
    "planning_rules.list_empty": "No planning rules yet.",
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

import PlanningRules from "@/pages/PlanningRules.vue";
import { SelectInput, NumberInput } from "@/components/ui/Input";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const mountPage = (props = {}) =>
    mount(PlanningRules, {
        props: {
            planningRules: [],
            workcenters: [{ id: 1, name: "Line 1" }],
            shifts: [{ id: 9, name: "Early" }],
            competences: [{ id: 3, name: "Welding" }],
            businessLines: [{ id: 5, abbreviation: "PMP" }],
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

describe("PlanningRules page", () => {
    it("renders a row per existing rule", () => {
        const w = mountPage({
            planningRules: [{ id: 1, type: "max_hours_per_week", mode: "hard", severity: null, config: {} }],
        });
        expect(w.findAll('[data-testid="planning-rule-row"]')).toHaveLength(1);
        expect(w.text()).toContain("Max hours per week");
    });

    it("Save/Cancel are disabled with nothing changed", () => {
        const w = mountPage({
            planningRules: [{ id: 1, type: "max_hours_per_week", mode: "hard", severity: null, config: {} }],
        });
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(findCancelButton(w).attributes("disabled")).toBeDefined();
    });

    it("adds a rule via the add form, enables Save, and fires no request until Save", async () => {
        const w = mountPage();
        const addSection = w.get('[data-testid="planning-rule-add"]');
        addSection.findComponent(SelectInput).vm.$emit("update:modelValue", "max_shifts_per_day");
        await w.vm.$nextTick();
        addSection.findComponent(NumberInput).vm.$emit("update:modelValue", 2);
        await w.vm.$nextTick();

        const addButton = w.findAll("button").find((b) => b.text() === "Add rule");
        await addButton.trigger("click");
        await w.vm.$nextTick();

        expect(w.findAll('[data-testid="planning-rule-row"]')).toHaveLength(1);
        expect(routerCalls).toEqual([]);
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();
    });

    it("saves a new rule with a POST on click", async () => {
        const w = mountPage();
        const addSection = w.get('[data-testid="planning-rule-add"]');
        addSection.findComponent(SelectInput).vm.$emit("update:modelValue", "max_shifts_per_day");
        await w.vm.$nextTick();
        addSection.findComponent(NumberInput).vm.$emit("update:modelValue", 2);
        await w.vm.$nextTick();
        await w.findAll("button").find((b) => b.text() === "Add rule").trigger("click");
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "post",
            "/planning-rules",
            { type: "max_shifts_per_day", mode: "hard", severity: null, value: 2 },
        ]);
    });

    it("removes a rule locally with no confirm, and saves it with a DELETE on click", async () => {
        const w = mountPage({
            planningRules: [{ id: 1, type: "max_hours_per_week", mode: "hard", severity: null, config: {} }],
        });
        await w.get('[aria-label="Delete rule"]').trigger("click");

        expect(w.findAll('[data-testid="planning-rule-row"]')).toHaveLength(0);
        expect(routerCalls).toEqual([]);

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls.some((c) => c[0] === "delete" && c[1] === "/planning-rules/1")).toBe(true);
    });

    it("Cancel reverts to the last-saved state without saving", async () => {
        const w = mountPage({
            planningRules: [{ id: 1, type: "max_hours_per_week", mode: "hard", severity: null, config: {} }],
        });
        await w.get('[aria-label="Delete rule"]').trigger("click");
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findCancelButton(w).trigger("click");
        await w.vm.$nextTick();

        expect(w.findAll('[data-testid="planning-rule-row"]')).toHaveLength(1);
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(routerCalls).toEqual([]);
    });
});
