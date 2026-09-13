import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
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
    "app.save": "Save",
    "app.saving": "Saving…",
    "app.saved": "Saved",
    "app.cancel": "Cancel",
    "app.save_failed": "Could not save",
    "business_lines.drag_handle": "Drag to reorder",
    "business_lines.delete": "Delete",
    "business_lines.add_abbreviation_placeholder": "ABBR",
    "business_lines.add_description_placeholder": "New business line",
};

// Requests fired by putAsync/postAsync/deleteAsync go through this mocked
// router; other still-autosaving lists just get a plain post/put/delete.
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
    const router = { put: respond("put"), post: respond("post"), delete: respond("delete") };
    return { routerCalls, failUrlsRef, router };
});

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

beforeEach(() => {
    routerCalls.length = 0;
    failUrlsRef.current = [];
});

const findSaveButton = (w) => w.findAll("button").find((b) => ["Save", "Saving…", "Saved"].includes(b.text()));
const findCancelButton = (w) => w.findAll("button").find((b) => b.text() === "Cancel");

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

    it("mounts the Business lines list with its items", () => {
        const w = mountPage({
            businessLines: [
                { id: 3, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 2 },
            ],
        });
        const list = w.findComponent(BusinessLineList);
        expect(list.props("items")).toHaveLength(1);
    });

    it("the Business lines Save/Cancel are disabled with nothing changed", () => {
        const w = mountPage({
            businessLines: [{ id: 3, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 2 }],
        });
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(findCancelButton(w).attributes("disabled")).toBeDefined();
    });

    it("enables Save when a business line field changes, and saves via a PUT on click", async () => {
        const w = mountPage({
            businessLines: [{ id: 3, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 2 }],
        });
        w.findComponent(BusinessLineList).vm.$emit("update:items", [
            { id: 3, abbreviation: "PUM", description: "Pumps", target_fte: 4, employee_count: 2 },
        ]);
        await w.vm.$nextTick();

        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();
        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "put",
            "/settings/business-lines/3",
            { abbreviation: "PUM", description: "Pumps", target_fte: 4 },
        ]);
        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
    });

    it("saves a new business line with a POST and a removed one with a DELETE", async () => {
        const w = mountPage({
            businessLines: [{ id: 3, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 0 }],
        });
        w.findComponent(BusinessLineList).vm.$emit("update:items", [
            { id: null, abbreviation: "SNS", description: "Sensors", target_fte: 1, employee_count: 0 },
        ]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "post",
            "/settings/business-lines",
            { abbreviation: "SNS", description: "Sensors", target_fte: 1 },
        ]);
        expect(routerCalls.some((c) => c[0] === "delete" && c[1] === "/settings/business-lines/3")).toBe(true);
    });

    it("saves a reorder with one PUT to the reorder endpoint", async () => {
        const w = mountPage({
            businessLines: [
                { id: 3, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 0 },
                { id: 4, abbreviation: "VLV", description: "Valves", target_fte: 2, employee_count: 0 },
            ],
        });
        w.findComponent(BusinessLineList).vm.$emit("update:items", [
            { id: 4, abbreviation: "VLV", description: "Valves", target_fte: 2, employee_count: 0 },
            { id: 3, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 0 },
        ]);
        await w.vm.$nextTick();

        await findSaveButton(w).trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "put",
            "/settings/business-lines/reorder",
            { ids: [4, 3] },
        ]);
    });

    it("Cancel reverts business lines to the last-saved state without saving", async () => {
        const w = mountPage({
            businessLines: [{ id: 3, abbreviation: "PMP", description: "Pumps", target_fte: 4, employee_count: 0 }],
        });
        w.findComponent(BusinessLineList).vm.$emit("update:items", [
            { id: 3, abbreviation: "PUM", description: "Pumps", target_fte: 4, employee_count: 0 },
        ]);
        await w.vm.$nextTick();
        expect(findSaveButton(w).attributes("disabled")).toBeUndefined();

        await findCancelButton(w).trigger("click");
        await w.vm.$nextTick();

        expect(findSaveButton(w).attributes("disabled")).toBeDefined();
        expect(routerCalls).toEqual([]);
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

    it("opens on the General panel and switches on a tab click", async () => {
        const w = mountPage();
        const hidden = (sel) => (w.get(sel).attributes("style") ?? "").includes("display: none");

        expect(hidden('[data-testid="panel-general"]')).toBe(false);
        expect(hidden('[data-testid="panel-shifts"]')).toBe(true);

        const tab = w.findAll("button").find((b) => b.text() === "Shifts");
        await tab.trigger("click");
        await w.vm.$nextTick();

        expect(hidden('[data-testid="panel-shifts"]')).toBe(false);
        expect(hidden('[data-testid="panel-general"]')).toBe(true);
    });
});
