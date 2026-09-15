import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises, DOMWrapper } from "@vue/test-utils";

const bodyWrapper = () => new DOMWrapper(document.body);

const en = {
    "scheduling.reset_spots": "Reset to the weekday default",
    "scheduling.toggle_fixed": "Toggle fixed",
    "scheduling.remove": "Remove",
    "scheduling.no_eligible_employees": "No one eligible.",
    "scheduling.open_spot": "Open",
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

import ShiftWeekTable from "@/components/scheduling/ShiftWeekTable.vue";
import { NumberInput } from "@/components/ui/Input";

// A week of cells (Mon 14 .. Sun 20). Mon: 2 spots, 1 assigned (1 open row).
// Tue: 1 spot, 1 assigned & fixed (row beyond spots is a dash). Wed: 0 spots.
// Thu: 2 spots, overridden, none assigned (both rows open). Fri-Sun: 0 spots.
const baseCells = [
    { date: "2026-09-14", spots: 2, overridden: false, assignments: [{ id: 1, employee_id: 5, employee_name: "Bram Bakker", fixed: false }] },
    { date: "2026-09-15", spots: 1, overridden: false, assignments: [{ id: 2, employee_id: 6, employee_name: "Anna Jansen", fixed: true }] },
    { date: "2026-09-16", spots: 0, overridden: false, assignments: [] },
    { date: "2026-09-17", spots: 2, overridden: true, assignments: [] },
    { date: "2026-09-18", spots: 0, overridden: false, assignments: [] },
    { date: "2026-09-19", spots: 0, overridden: false, assignments: [] },
    { date: "2026-09-20", spots: 0, overridden: false, assignments: [] },
];

const mountTable = (cells = baseCells) =>
    mount(ShiftWeekTable, { props: { workcenterId: 1, shiftId: 9, cells } });

beforeEach(() => {
    routerCalls.length = 0;
    failUrlsRef.current = [];
    axiosGet.mockReset();
    axiosGet.mockResolvedValue({ data: [] });
});

describe("ShiftWeekTable", () => {
    it("renders a spot count per day, with a reset icon only on the overridden day", () => {
        const w = mountTable();

        expect(w.get('[data-testid="spots-9-2026-09-14"]').text()).toBe("2");
        expect(w.get('[data-testid="spots-9-2026-09-17"]').text()).toBe("2");
        expect(w.findAll('[aria-label="Reset to the weekday default"]')).toHaveLength(1);
    });

    it("renders filled cells with the employee name, open cells as Open, and cells beyond that day's spot count as a dash", () => {
        const w = mountTable();

        expect(w.get('[data-testid="cell-9-2026-09-14-0"]').text()).toBe("Bram Bakker");
        expect(w.get('[data-testid="cell-9-2026-09-14-1"]').text()).toBe("Open");
        expect(w.get('[data-testid="cell-9-2026-09-15-0"]').text()).toBe("Anna Jansen");
        expect(w.get('[data-testid="cell-9-2026-09-15-1"]').text()).toBe("—");
        expect(w.get('[data-testid="cell-9-2026-09-16-0"]').text()).toBe("—");
        expect(w.get('[data-testid="cell-9-2026-09-17-0"]').text()).toBe("Open");
    });

    it("committing a changed spot value fires a PUT; an unchanged one fires nothing", async () => {
        const w = mountTable();
        await w.get('[data-testid="spots-9-2026-09-14"]').trigger("click");

        w.findComponent(NumberInput).vm.$emit("update:modelValue", 3);
        await flushPromises();

        expect(routerCalls).toContainEqual(["put", "/scheduling/spots/1/9/2026-09-14", { spots: 3 }]);

        routerCalls.length = 0;
        await w.get('[data-testid="spots-9-2026-09-15"]').trigger("click");
        w.findComponent(NumberInput).vm.$emit("update:modelValue", 1);
        await flushPromises();

        expect(routerCalls).toEqual([]);
    });

    it("clicking the reset icon fires a DELETE for that day", async () => {
        const w = mountTable();
        await w.get('[aria-label="Reset to the weekday default"]').trigger("click");

        expect(routerCalls).toContainEqual(["delete", "/scheduling/spots/1/9/2026-09-17"]);
    });

    it("clicking a filled cell reveals pin/remove controls; toggling pin fires a PUT and remove fires a DELETE", async () => {
        const w = mountTable();
        await w.get('[data-testid="cell-9-2026-09-14-0"] button').trigger("click");

        await w.get('[aria-label="Toggle fixed"]').trigger("click");
        expect(routerCalls).toContainEqual(["put", "/scheduling/assignments/1", { fixed: true }]);

        await w.get('[aria-label="Remove"]').trigger("click");
        expect(routerCalls).toContainEqual(["delete", "/scheduling/assignments/1"]);
    });

    it("clicking an Open cell fetches eligible employees and lists them; clicking one assigns and closes the popover", async () => {
        axiosGet.mockResolvedValue({ data: [{ id: 3, name: "Els de Vries", not_preferred: false }] });
        const w = mountTable();

        await w.get('[data-testid="cell-9-2026-09-17-0"] button').trigger("click");
        await flushPromises();

        expect(axiosGet).toHaveBeenCalledWith("/scheduling/eligible-employees", {
            params: { workcenter_id: 1, shift_id: 9, date: "2026-09-17" },
        });
        const popover = bodyWrapper().get('[data-testid="assign-popover"]');
        expect(popover.text()).toContain("Els de Vries");

        await popover.get("button").trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "post",
            "/scheduling/assignments",
            { employee_id: 3, workcenter_id: 1, shift_id: 9, date: "2026-09-17" },
        ]);
        expect(bodyWrapper().find('[data-testid="assign-popover"]').exists()).toBe(false);
        w.unmount();
    });
});
