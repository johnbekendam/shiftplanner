import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";

const en = {
    "scheduling.reset_spots": "Reset to the weekday default",
    "scheduling.toggle_fixed": "Toggle fixed",
    "scheduling.remove": "Remove",
    "scheduling.add": "Add",
    "scheduling.no_eligible_employees": "No one eligible.",
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

vi.mock("@inertiajs/vue3", () => ({ router }));

const axiosGet = vi.hoisted(() => vi.fn());
vi.mock("axios", () => ({ default: { get: axiosGet } }));

vi.mock("@/composables/useI18n", () => ({
    useI18n: () => (key) => en[key] ?? key,
}));

import SchedulingCell from "@/components/scheduling/SchedulingCell.vue";
import { NumberInput } from "@/components/ui/Input";

const baseCell = {
    spots: 4,
    overridden: false,
    assignments: [{ id: 1, employee_id: 5, employee_name: "Anna Jansen", fixed: false }],
};

const mountCell = (cell = baseCell) =>
    mount(SchedulingCell, {
        props: { workcenterId: 1, shiftId: 9, date: "2026-09-15", cell },
    });

beforeEach(() => {
    routerCalls.length = 0;
    failUrlsRef.current = [];
    axiosGet.mockReset();
    axiosGet.mockResolvedValue({ data: [] });
});

describe("SchedulingCell", () => {
    it("shows the spot count and assignee names", () => {
        const w = mountCell();
        expect(w.text()).toContain("1/4");
        expect(w.text()).toContain("Anna Jansen");
    });

    it("clicking the spot count and committing a new value fires a PUT", async () => {
        const w = mountCell();
        await w.get('[data-testid="spots-9-2026-09-15"]').trigger("click");

        w.findComponent(NumberInput).vm.$emit("update:modelValue", 5);
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/scheduling/spots/1/9/2026-09-15", { spots: 5 }]);
    });

    it("does not fire a request when the committed value is unchanged", async () => {
        const w = mountCell();
        await w.get('[data-testid="spots-9-2026-09-15"]').trigger("click");

        w.findComponent(NumberInput).vm.$emit("update:modelValue", 4);
        await flushPromises();

        expect(routerCalls).toEqual([]);
    });

    it("shows the reset icon only when the cell is overridden, and it fires a DELETE", async () => {
        const notOverridden = mountCell();
        expect(notOverridden.find('[aria-label="Reset to the weekday default"]').exists()).toBe(false);

        const overridden = mountCell({ ...baseCell, overridden: true });
        await overridden.get('[aria-label="Reset to the weekday default"]').trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual(["delete", "/scheduling/spots/1/9/2026-09-15"]);
    });

    it("toggling the pin fires a PUT with the flipped fixed value", async () => {
        const w = mountCell();
        await w.get('[aria-label="Toggle fixed"]').trigger("click");

        expect(routerCalls).toContainEqual(["put", "/scheduling/assignments/1", { fixed: true }]);
    });

    it("remove fires a DELETE with no confirmation", async () => {
        const w = mountCell();
        await w.get('[aria-label="Remove"]').trigger("click");

        expect(routerCalls).toContainEqual(["delete", "/scheduling/assignments/1"]);
    });

    it("opening Add fetches eligible employees and lists them, flagging not_preferred", async () => {
        axiosGet.mockResolvedValue({
            data: [
                { id: 2, name: "Bram Bakker", not_preferred: false },
                { id: 3, name: "Els de Vries", not_preferred: true },
            ],
        });
        const w = mountCell();
        await w.findAll("button").find((b) => b.text().includes("Add")).trigger("click");
        await flushPromises();

        expect(axiosGet).toHaveBeenCalledWith("/scheduling/eligible-employees", {
            params: { workcenter_id: 1, shift_id: 9, date: "2026-09-15" },
        });
        const popover = w.get('[data-testid="add-popover"]');
        expect(popover.text()).toContain("Bram Bakker");
        expect(popover.text()).toContain("Els de Vries");
    });

    it("clicking an eligible employee fires a POST and closes the popover", async () => {
        axiosGet.mockResolvedValue({ data: [{ id: 2, name: "Bram Bakker", not_preferred: false }] });
        const w = mountCell();
        await w.findAll("button").find((b) => b.text().includes("Add")).trigger("click");
        await flushPromises();

        await w.get('[data-testid="add-popover"] button').trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "post",
            "/scheduling/assignments",
            { employee_id: 2, workcenter_id: 1, shift_id: 9, date: "2026-09-15" },
        ]);
        expect(w.find('[data-testid="add-popover"]').exists()).toBe(false);
    });
});
