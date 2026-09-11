import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "settings.title": "Settings",
    "settings.tab.competences": "Competences",
    "settings.tab.business_lines": "Business lines",
    "settings.tab.shifts": "Shifts",
    "settings.tab.information": "Information",
    "settings.tab.questions": "Questions",
    "settings.tab.general": "General",
    "general.allow_employee_changes": "Allow employees to change their own details",
    "general.allow_employee_changes_hint": "When off, personal pages stay visible but read-only.",
    "shifts.name": "Name",
    "shifts.start_time": "Start",
    "shifts.end_time": "End",
    "shifts.list_empty": "No shifts yet.",
    "shifts.note_label": "Information for employees",
    "shifts.note_hint": "Markdown.",
    "shifts.note_save": "Save information",
    "business_lines.abbreviation": "Abbreviation",
    "business_lines.description": "Description",
    "business_lines.target_fte": "Target FTE",
    "business_lines.list_empty": "No business lines yet.",
    "period.fte_hours": "Hours per FTE",
    "period.fte_hours_hint": "hint",
    "period.period_start": "Period start",
    "period.period_end": "Period end",
    "period.save": "Save period",
    "competences.name": "Name",
    "competences.list_empty": "No competences yet.",
    "competences.add_placeholder": "New competence",
    "questions.name": "Question",
    "questions.list_empty": "No questions yet.",
    "questions.add_placeholder": "New question",
    "app.saving": "Saving…",
    "app.saved": "Saved",
    "app.save_failed": "Could not save",
};

const { router } = vi.hoisted(() => ({
    router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en } }),
    useForm: (initial) =>
        reactive({ ...initial, errors: {}, processing: false, transform() { return this; }, put: vi.fn() }),
}));

import Settings from "@/pages/Settings/Index.vue";
import OrderedNameList from "@/components/OrderedNameList.vue";
import BusinessLineList from "@/components/BusinessLineList.vue";
import ShiftList from "@/components/ShiftList.vue";
import ShiftNoteForm from "@/components/ShiftNoteForm.vue";
import PeriodSettingsForm from "@/components/PeriodSettingsForm.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const mountPage = (props = {}) =>
    mount(Settings, {
        props: {
            competences: [],
            businessLines: [],
            shifts: [],
            shiftNote: "",
            questions: [],
            period: { fte_hours: 40, period_start: null, period_end: null },
            ...props,
        },
        global: { stubs },
    });

describe("Settings/Index", () => {
    it("shows a tab for competences, business lines, shifts, information, questions and general", () => {
        const text = mountPage().text();
        expect(text).toContain("Competences");
        expect(text).toContain("Business lines");
        expect(text).toContain("Shifts");
        expect(text).toContain("Information");
        expect(text).toContain("Questions");
        expect(text).toContain("General");
        expect(text).not.toContain("Product groups");
    });

    it("mounts the period form seeded from the period prop", () => {
        const w = mountPage({ period: { fte_hours: 32, period_start: "2026-02-01", period_end: "2026-02-28" } });
        const form = w.findComponent(PeriodSettingsForm);
        expect(form.props("period")).toMatchObject({ fte_hours: 32, period_start: "2026-02-01" });
    });

    it("mounts the Shifts list against its endpoint", () => {
        const w = mountPage({
            shifts: [{ id: 3, name: "Early", start_time: "06:00", end_time: "14:00" }],
        });
        const list = w.findComponent(ShiftList);
        expect(list.props("endpoint")).toBe("/settings/shifts");
        expect(list.props("items")).toHaveLength(1);
    });

    it("mounts the shift note form seeded from the shiftNote prop", () => {
        const w = mountPage({ shiftNote: "# Allowances" });
        expect(w.findComponent(ShiftNoteForm).props("note")).toBe("# Allowances");
    });

    it("mounts the Business lines list against its endpoint", () => {
        const w = mountPage({
            businessLines: [
                { id: 3, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 2 },
            ],
        });
        const list = w.findComponent(BusinessLineList);
        expect(list.props("endpoint")).toBe("/settings/business-lines");
        expect(list.props("items")).toHaveLength(1);
    });

    it("shows the shared save-status badge on the Business lines panel while a row saves", async () => {
        const w = mountPage({
            businessLines: [
                { id: 3, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 2 },
            ],
        });
        const list = w.findComponent(BusinessLineList);
        expect(list.props("saveStatus")).toBeTruthy();

        list.props("saveStatus").start();
        await w.vm.$nextTick();

        expect(w.get('[data-testid="panel-business-lines"]').text()).toContain("Saving…");
    });

    it("mounts the competences list against its endpoint and prefix", () => {
        const lists = mountPage({
            competences: [{ id: 1, name: "Forklift", position: 1, holder_count: 0 }],
        }).findAllComponents(OrderedNameList);

        const byEndpoint = Object.fromEntries(lists.map((l) => [l.props("endpoint"), l]));

        expect(byEndpoint["/settings/competences"].props("i18nPrefix")).toBe("competences");
        expect(byEndpoint["/settings/competences"].props("items")).toHaveLength(1);
        expect(byEndpoint["/settings/product-groups"]).toBeUndefined();
    });

    it("mounts the questions list against its endpoint and prefix", () => {
        const lists = mountPage({
            questions: [{ id: 1, name: "Weekend?", position: 1, holder_count: 2 }],
        }).findAllComponents(OrderedNameList);

        const byEndpoint = Object.fromEntries(lists.map((l) => [l.props("endpoint"), l]));

        expect(byEndpoint["/settings/questions"].props("i18nPrefix")).toBe("questions");
        expect(byEndpoint["/settings/questions"].props("items")).toHaveLength(1);
    });

    it("opens on the Business lines panel and switches on a tab click", async () => {
        const w = mountPage();
        const hidden = (sel) => (w.get(sel).attributes("style") ?? "").includes("display: none");

        expect(hidden('[data-testid="panel-business-lines"]')).toBe(false);
        expect(hidden('[data-testid="panel-shifts"]')).toBe(true);

        const tab = w.findAll("button").find((b) => b.text() === "Shifts");
        await tab.trigger("click");
        await w.vm.$nextTick();

        expect(hidden('[data-testid="panel-shifts"]')).toBe(false);
        expect(hidden('[data-testid="panel-business-lines"]')).toBe(true);
    });
});
