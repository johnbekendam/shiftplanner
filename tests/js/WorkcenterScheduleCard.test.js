import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const { routerCalls, router } = vi.hoisted(() => {
    const routerCalls = [];
    const respond = (name) => (...args) => {
        const last = args.at(-1);
        const opts = last && typeof last === "object" && (last.onSuccess || last.onError) ? last : undefined;
        const rest = opts ? args.slice(0, -1) : args;
        routerCalls.push([name, ...rest]);
        opts?.onSuccess?.();
    };
    const router = { put: respond("put"), post: respond("post"), delete: respond("delete"), on: () => () => {} };
    return { routerCalls, router };
});

vi.mock("@inertiajs/vue3", () => ({ router }));
vi.mock("axios", () => ({ default: { get: vi.fn().mockResolvedValue({ data: [] }) } }));
vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => ({
        "scheduling.publish": "Publish",
        "scheduling.unpublish": "Unpublish",
        "scheduling.allow_planner": "Allow autoplanner",
    })[key] ?? key,
}));

import WorkcenterScheduleCard from "@/components/scheduling/WorkcenterScheduleCard.vue";
import ShiftWeekTable from "@/components/scheduling/ShiftWeekTable.vue";
import { CheckboxInput } from "@/components/ui/Input";

const cells = Array.from({ length: 7 }, (_, i) => ({
    date: `2026-09-${String(14 + i).padStart(2, "0")}`,
    spots: 1,
    overridden: false,
    assignments: [],
}));

const mountCard = (props = {}) =>
    mount(WorkcenterScheduleCard, {
        props: {
            workcenter: { id: 1, name: "Line 1" },
            weekStart: "2026-09-14",
            schedule: [{ shift: { id: 9, name: "Early", start_time: "06:00", end_time: "14:00" }, cells }],
            ...props,
        },
    });

beforeEach(() => {
    routerCalls.length = 0;
});

describe("WorkcenterScheduleCard", () => {
    it("shows the workcenter name in the header and a table per shift without working hours", () => {
        const w = mountCard({
            schedule: [
                { shift: { id: 9, name: "Early", start_time: "06:00", end_time: "14:00" }, cells },
                { shift: { id: 10, name: "Late", start_time: "14:00", end_time: "22:00" }, cells },
            ],
        });

        expect(w.text()).toContain("Line 1");
        expect(w.text()).toContain("Early");
        expect(w.text()).toContain("Late");
        expect(w.text()).not.toContain("06:00");
        expect(w.text()).not.toContain("14:00");
        expect(w.findAll("table")).toHaveLength(2);
    });

    it("places a separator between shifts, but not before the first one", () => {
        const w = mountCard({
            schedule: [
                { shift: { id: 9, name: "Early", start_time: "06:00", end_time: "14:00" }, cells },
                { shift: { id: 10, name: "Late", start_time: "14:00", end_time: "22:00" }, cells },
                { shift: { id: 11, name: "Night", start_time: "22:00", end_time: "06:00" }, cells },
            ],
        });

        expect(w.findAll("hr")).toHaveLength(2);
    });

    it("shows a Publish button when not published and posts to this workcenter's publish endpoint", async () => {
        const w = mountCard({ published: false });

        const button = w.get('[data-testid="publish-workcenter-button"]');
        expect(button.text()).toBe("Publish");

        await button.trigger("click");

        expect(routerCalls).toContainEqual(["post", "/planning/weeks/2026-09-14/workcenters/1/publish", undefined]);
    });

    it("shows an Unpublish button when published and deletes this workcenter's publish endpoint", async () => {
        const w = mountCard({ published: true });

        const button = w.get('[data-testid="publish-workcenter-button"]');
        expect(button.text()).toBe("Unpublish");

        await button.trigger("click");

        expect(routerCalls).toContainEqual(["delete", "/planning/weeks/2026-09-14/workcenters/1/publish"]);
    });

    it("tells each shift table whether the workcenter is published", () => {
        expect(mountCard({ published: true }).findComponent(ShiftWeekTable).props("published")).toBe(true);
        expect(mountCard({ published: false }).findComponent(ShiftWeekTable).props("published")).toBe(false);
    });

    it("offers Allow autoplanner only while the workcenter is published", () => {
        expect(mountCard({ published: false }).find('[data-testid="planner-open-toggle"]').exists()).toBe(false);

        const w = mountCard({ published: true });
        expect(w.find('[data-testid="planner-open-toggle"]').exists()).toBe(true);
        expect(w.get('[data-testid="planner-open-toggle"]').element.closest("label").textContent.trim()).toBe("Allow autoplanner");
    });

    it("shows the toggle switched on when the autoplanner is allowed", () => {
        expect(mountCard({ published: true, plannerOpen: true }).findComponent(CheckboxInput).props("modelValue")).toBe(true);
        expect(mountCard({ published: true, plannerOpen: false }).findComponent(CheckboxInput).props("modelValue")).toBe(false);
    });

    it("allows the autoplanner for this workcenter-week with a PUT when the toggle is switched on", async () => {
        const w = mountCard({ published: true, plannerOpen: false });

        w.findComponent(CheckboxInput).vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(routerCalls).toContainEqual([
            "put",
            "/planning/weeks/2026-09-14/workcenters/1/planner-open",
            { planner_open: true },
        ]);
    });

    it("freezes the week again with a PUT when the toggle is switched off", async () => {
        const w = mountCard({ published: true, plannerOpen: true });

        w.findComponent(CheckboxInput).vm.$emit("update:modelValue", false);
        await w.vm.$nextTick();

        expect(routerCalls).toContainEqual([
            "put",
            "/planning/weeks/2026-09-14/workcenters/1/planner-open",
            { planner_open: false },
        ]);
    });
});
