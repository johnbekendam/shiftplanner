import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
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
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import PlanningRuleList from "@/components/PlanningRuleList.vue";
import { SelectInput, NumberInput } from "@/components/ui/Input";

const workcenters = [{ id: 1, name: "Line 1" }];
const shifts = [{ id: 9, name: "Early" }];
const competences = [{ id: 3, name: "Welding" }];
const businessLines = [{ id: 5, abbreviation: "PMP" }, { id: 6, abbreviation: "VLV" }];

const mountList = (props = {}) => mount(PlanningRuleList, {
    props: { items: [], workcenters, shifts, competences, businessLines, ...props },
});

describe("PlanningRuleList", () => {
    it("renders a row per item and an empty state with none", () => {
        const withRows = mountList({
            items: [{ id: 1, type: "max_hours_per_week", mode: "hard", severity: null, config: {} }],
        });
        expect(withRows.findAll('[data-testid="planning-rule-row"]')).toHaveLength(1);
        expect(withRows.text()).toContain("Max hours per week");

        expect(mountList().text()).toContain("No planning rules yet.");
    });

    it("only offers singleton types not already present", () => {
        const w = mountList({
            items: [{ id: 1, type: "max_hours_per_week", mode: "hard", severity: null, config: {} }],
        });
        const addSelect = w.get('[data-testid="planning-rule-add"]').findComponent(SelectInput);
        const values = addSelect.props("options").map((o) => o.value);
        expect(values).not.toContain("max_hours_per_week");
        expect(values).toContain("competence_required");
    });

    it("adds a competence_required rule via the add form and emits it", async () => {
        const w = mountList();
        const addSection = w.get('[data-testid="planning-rule-add"]');
        addSection.findComponent(SelectInput).vm.$emit("update:modelValue", "competence_required");
        await w.vm.$nextTick();

        const selects = addSection.findAllComponents(SelectInput);
        selects[1].vm.$emit("update:modelValue", 1); // workcenter
        selects[2].vm.$emit("update:modelValue", 9); // shift
        selects[3].vm.$emit("update:modelValue", 3); // competence
        await w.vm.$nextTick();

        const addButton = w.findAll('button').find((b) => b.text() === "Add rule");
        await addButton.trigger('click');

        const rows = w.emitted("update:items").at(-1)[0];
        expect(rows).toHaveLength(1);
        expect(rows[0]).toMatchObject({
            type: "competence_required",
            config: { workcenter_id: 1, shift_id: 9, competence_id: 3 },
        });
    });

    it("adds a max_shifts_per_day rule via the add form and emits it", async () => {
        const w = mountList();
        const addSection = w.get('[data-testid="planning-rule-add"]');
        addSection.findComponent(SelectInput).vm.$emit("update:modelValue", "max_shifts_per_day");
        await w.vm.$nextTick();

        addSection.findComponent(NumberInput).vm.$emit("update:modelValue", 2);
        await w.vm.$nextTick();

        const addButton = w.findAll('button').find((b) => b.text() === "Add rule");
        await addButton.trigger('click');

        const emitted = w.emitted("update:items");
        expect(emitted).toBeTruthy();
        const rows = emitted.at(-1)[0];
        expect(rows).toHaveLength(1);
        expect(rows[0]).toMatchObject({ id: null, type: "max_shifts_per_day", mode: "hard", config: { value: 2 } });
    });

    it("reveals a severity field only when mode is soft", async () => {
        const w = mountList();
        const addSection = w.get('[data-testid="planning-rule-add"]');
        addSection.findComponent(SelectInput).vm.$emit("update:modelValue", "not_preferred_shift");
        await w.vm.$nextTick();

        expect(w.text()).not.toContain("Severity (1-10)");

        const modeSelect = addSection.findAllComponents(SelectInput).find((s) => s.props("options")[0]?.value === "hard");
        modeSelect.vm.$emit("update:modelValue", "soft");
        await w.vm.$nextTick();

        expect(w.text()).toContain("Severity (1-10)");
    });

    it("removes a row locally and emits update:items, without a network call", async () => {
        const w = mountList({
            items: [{ id: 1, type: "max_hours_per_week", mode: "hard", severity: null, config: {} }],
        });

        const row = w.get('[data-testid="planning-rule-row"]');
        await row.get('button').trigger('click');

        const emitted = w.emitted("update:items");
        expect(emitted.at(-1)[0]).toHaveLength(0);
    });
});
