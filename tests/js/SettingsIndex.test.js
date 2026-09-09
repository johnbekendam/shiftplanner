import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { reactive } from "vue";

const en = {
    "settings.title": "Settings",
    "settings.tab.competences": "Competences",
    "settings.tab.business_lines": "Business lines",
    "settings.tab.shifts": "Shifts",
    "settings.tab.period": "Period",
    "shifts.name": "Name",
    "shifts.start_time": "Start",
    "shifts.end_time": "End",
    "shifts.list_empty": "No shifts yet.",
    "shifts.note_label": "Shift information",
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
            period: { fte_hours: 40, period_start: null, period_end: null },
            ...props,
        },
        global: { stubs },
    });

describe("Settings/Index", () => {
    it("shows a tab for competences, business lines, shifts and the period", () => {
        const text = mountPage().text();
        expect(text).toContain("Competences");
        expect(text).toContain("Business lines");
        expect(text).toContain("Shifts");
        expect(text).toContain("Period");
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

    it("mounts the competences list against its endpoint and prefix", () => {
        const lists = mountPage({
            competences: [{ id: 1, name: "Forklift", position: 1, holder_count: 0 }],
        }).findAllComponents(OrderedNameList);

        const byEndpoint = Object.fromEntries(lists.map((l) => [l.props("endpoint"), l]));

        expect(byEndpoint["/settings/competences"].props("i18nPrefix")).toBe("competences");
        expect(byEndpoint["/settings/competences"].props("items")).toHaveLength(1);
        expect(byEndpoint["/settings/product-groups"]).toBeUndefined();
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
