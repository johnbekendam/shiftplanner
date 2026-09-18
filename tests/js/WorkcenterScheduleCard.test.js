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
    useI18n: () => (key) => ({ "scheduling.publish": "Publish", "scheduling.unpublish": "Unpublish" })[key] ?? key,
}));

import WorkcenterScheduleCard from "@/components/scheduling/WorkcenterScheduleCard.vue";

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
});
