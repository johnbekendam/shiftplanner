import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises, DOMWrapper } from "@vue/test-utils";

const bodyWrapper = () => new DOMWrapper(document.body);

const en = {
    "scheduling.reset_spots": "Reset to the weekday default",
    "scheduling.freeze": "Freeze",
    "scheduling.unfreeze": "Unfreeze",
    "scheduling.remove": "Remove",
    "scheduling.no_eligible_employees": "No one eligible.",
    "scheduling.show_all_employees": "Show all",
    "scheduling.error.holiday": "This employee is on holiday that day.",
    "scheduling.open_spot": "Add employee",
    "scheduling.unfulfilled_reason.no_eligible_employee": "No eligible employee found.",
    "scheduling.unfulfilled_reason.hard_cap_reached": "Every eligible employee was blocked by a hard cap.",
    "scheduling.assignment_tooltip.unfixed": "Bram Bakker (unfixed)",
    "scheduling.assignment_tooltip.fixed": "Anna Jansen (fixed)",
    "scheduling.assignment_tooltip.informed": "Bram Bakker (informed)",
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
    useI18n: () => (key, values = {}) => Object.entries(values).reduce(
        (text, [name, value]) => text.replace(`:${name}`, value),
        en[key] ?? key,
    ),
}));

import { SearchInput } from "@/components/ui/Input";
import ShiftWeekTable from "@/components/scheduling/ShiftWeekTable.vue";

// A week of cells (Mon 14 .. Sun 20). Mon: 2 spots, 1 assigned (1 open row).
// Tue: 1 spot, 1 assigned & fixed (row beyond spots is a dash). Wed: 0 spots.
// Thu: 2 spots, overridden, none assigned (both rows open). Fri-Sun: 0 spots.
const baseCells = [
    { date: "2026-09-14", spots: 2, overridden: false, assignments: [{ id: 1, employee_id: 5, employee_name: "Bram Bakker", fixed: false, informed: false }] },
    { date: "2026-09-15", spots: 1, overridden: false, assignments: [{ id: 2, employee_id: 6, employee_name: "Anna Jansen", fixed: true, informed: false }] },
    { date: "2026-09-16", spots: 0, overridden: false, assignments: [] },
    { date: "2026-09-17", spots: 2, overridden: true, assignments: [] },
    { date: "2026-09-18", spots: 0, overridden: false, assignments: [] },
    { date: "2026-09-19", spots: 0, overridden: false, assignments: [] },
    { date: "2026-09-20", spots: 0, overridden: false, assignments: [] },
];

const mountTable = (cells = baseCells, extraProps = {}) =>
    mount(ShiftWeekTable, { props: { workcenterId: 1, shiftId: 9, cells, ...extraProps } });

beforeEach(() => {
    routerCalls.length = 0;
    failUrlsRef.current = [];
    axiosGet.mockReset();
    axiosGet.mockResolvedValue({ data: [] });
});

describe("ShiftWeekTable", () => {
    it("renders a date label per day, with a reset icon only on the overridden day, and no spot count", () => {
        const w = mountTable();

        expect(w.get('[data-testid="spots-9-2026-09-14"]').text()).toContain("14");
        expect(w.findAll('[aria-label="Reset to the weekday default"]')).toHaveLength(1);
        expect(w.text()).not.toContain("2/");
    });

    it("keeps the spots selection menu hidden until the date is clicked, then toggles it on a second click", async () => {
        const w = mountTable();

        expect(bodyWrapper().find('[data-testid="spots-menu"]').exists()).toBe(false);

        await w.get('[data-testid="spots-9-2026-09-14"]').trigger("click");
        expect(bodyWrapper().find('[data-testid="spots-menu"]').exists()).toBe(true);

        await w.get('[data-testid="spots-9-2026-09-14"]').trigger("click");
        expect(bodyWrapper().find('[data-testid="spots-menu"]').exists()).toBe(false);
        w.unmount();
    });

    it("renders filled cells with the employee name, open cells as an add-employee icon, and cells beyond that day's spot count as a dash", () => {
        const w = mountTable();

        expect(w.get('[data-testid="cell-9-2026-09-14-0"] [data-testid="assignment-status-badge"]').text()).toBe("Bram Bakker");
        expect(w.get('[data-testid="cell-9-2026-09-14-1"]').find('[aria-label="Add employee"]').exists()).toBe(true);
        expect(w.get('[data-testid="cell-9-2026-09-15-0"] [data-testid="assignment-status-badge"]').text()).toBe("Anna Jansen");
        expect(w.get('[data-testid="cell-9-2026-09-15-1"]').text()).toBe("—");
        expect(w.get('[data-testid="cell-9-2026-09-16-0"]').text()).toBe("—");
        expect(w.get('[data-testid="cell-9-2026-09-17-0"]').find('[aria-label="Add employee"]').exists()).toBe(true);
    });

    const nameClasses = (w, cell) => w.get(`[data-testid="${cell}"] button`).find("span").classes();

    it("shows an unfixed assignee in a muted badge", () => {
        const w = mountTable(undefined, { published: false });

        // Bram Bakker (Mon, row 0) is not fixed.
        const badge = w.get('[data-testid="cell-9-2026-09-14-0"] [data-testid="assignment-status-badge"]');
        expect(badge.text()).toBe("Bram Bakker");
        expect(badge.classes()).toContain("text-(--color-badge-muted-text)");
    });

    it("shows a fixed assignee with an info badge", () => {
        const w = mountTable(undefined, { published: false });

        // Anna Jansen (Tue, row 0) is fixed.
        const badge = w.get('[data-testid="cell-9-2026-09-15-0"] [data-testid="assignment-status-badge"]');
        expect(badge.text()).toBe("Anna Jansen");
        expect(badge.classes()).toContain("text-(--color-badge-standard-text)");
    });

    it("shows an informed assignee with a success badge", () => {
        const cells = baseCells.map((cell) => ({
            ...cell,
            assignments: cell.assignments.map((assignment) => ({ ...assignment, informed: assignment.id === 1 })),
        }));
        const w = mountTable(cells, { published: true });

        const cell = w.get('[data-testid="cell-9-2026-09-14-0"]');
        const badge = cell.get('[data-testid="assignment-status-badge"]');
        expect(badge.text()).toBe("Bram Bakker");
        expect(badge.classes()).toContain("text-(--color-badge-success-text)");
    });

    it("shows full-width badges for unfixed and fixed assignees when published", () => {
        const w = mountTable(undefined, { published: true });

        expect(w.get('[data-testid="cell-9-2026-09-14-0"] [data-testid="assignment-status-badge"]').classes()).toContain("w-full");
        expect(w.get('[data-testid="cell-9-2026-09-15-0"] [data-testid="assignment-status-badge"]').classes()).toContain("w-full");
    });

    it("shows the full employee name and state in the hover tooltip", () => {
        const w = mountTable();

        expect(w.get('[data-testid="cell-9-2026-09-14-0"] [role="tooltip"]').text()).toBe("Bram Bakker (unfixed)");
        expect(w.get('[data-testid="cell-9-2026-09-15-0"] [role="tooltip"]').text()).toBe("Anna Jansen (fixed)");

        const informedCells = baseCells.map((cell) => ({
            ...cell,
            assignments: cell.assignments.map((assignment) => ({ ...assignment, informed: assignment.id === 1 })),
        }));
        const informed = mountTable(informedCells);
        expect(informed.get('[data-testid="cell-9-2026-09-14-0"] [role="tooltip"]').text()).toBe("Bram Bakker (informed)");
    });

    it("shows no pin icon for a fixed assignee, only the name", () => {
        const w = mountTable();

        const cell = w.get('[data-testid="cell-9-2026-09-15-0"]');
        expect(cell.get('[data-testid="assignment-status-badge"]').text()).toBe("Anna Jansen");
        expect(cell.find("svg").exists()).toBe(false);
        expect(w.get('[data-testid="cell-9-2026-09-14-0"]').find("svg").exists()).toBe(false);
    });

    it("picking a value from the menu fires a PUT and closes the menu; picking the current value fires nothing", async () => {
        const w = mountTable();
        await w.get('[data-testid="spots-9-2026-09-14"]').trigger("click"); // Mon: 2 spots

        const menu = bodyWrapper().get('[data-testid="spots-menu"]');
        const options = menu.findAll("button");
        await options[3].trigger("click"); // "3"

        expect(routerCalls).toContainEqual(["put", "/planning/spots/1/9/2026-09-14", { spots: 3 }]);
        expect(bodyWrapper().find('[data-testid="spots-menu"]').exists()).toBe(false);

        routerCalls.length = 0;
        await w.get('[data-testid="spots-9-2026-09-14"]').trigger("click");
        await bodyWrapper().get('[data-testid="spots-menu"]').findAll("button")[2].trigger("click"); // "2", unchanged

        expect(routerCalls).toEqual([]);
        w.unmount();
    });

    it("clicking the reset icon fires a DELETE for that day", async () => {
        const w = mountTable();
        await w.get('[aria-label="Reset to the weekday default"]').trigger("click");

        expect(routerCalls).toContainEqual(["delete", "/planning/spots/1/9/2026-09-17"]);
    });

    it("clicking a filled cell opens a Freeze/Remove menu, not inline icons", async () => {
        const w = mountTable();
        await w.get('[data-testid="cell-9-2026-09-14-0"] button').trigger("click");

        expect(w.find('[aria-label="Toggle fixed"]').exists()).toBe(false);
        const menu = bodyWrapper().get('[data-testid="assignment-menu"]');
        expect(menu.text()).toContain("Freeze");
        expect(menu.text()).toContain("Remove");
        w.unmount();
    });

    it("the menu offers Unfreeze for an already-fixed assignment", async () => {
        const w = mountTable();
        await w.get('[data-testid="cell-9-2026-09-15-0"] button').trigger("click"); // Anna, fixed: true

        const menu = bodyWrapper().get('[data-testid="assignment-menu"]');
        expect(menu.text()).toContain("Unfreeze");
        w.unmount();
    });

    it("picking Freeze fires a PUT with fixed flipped, and closes the menu", async () => {
        const w = mountTable();
        await w.get('[data-testid="cell-9-2026-09-14-0"] button').trigger("click"); // Bram, fixed: false

        const menu = bodyWrapper().get('[data-testid="assignment-menu"]');
        await menu.findAll("button")[0].trigger("click");

        expect(routerCalls).toContainEqual(["put", "/planning/assignments/1", { fixed: true }]);
        expect(bodyWrapper().find('[data-testid="assignment-menu"]').exists()).toBe(false);
        w.unmount();
    });

    it("picking Remove fires a DELETE and closes the menu", async () => {
        const w = mountTable();
        await w.get('[data-testid="cell-9-2026-09-14-0"] button').trigger("click");

        const menu = bodyWrapper().get('[data-testid="assignment-menu"]');
        await menu.findAll("button")[1].trigger("click");

        expect(routerCalls).toContainEqual(["delete", "/planning/assignments/1"]);
        expect(bodyWrapper().find('[data-testid="assignment-menu"]').exists()).toBe(false);
        w.unmount();
    });

    it("clicking an Open cell fetches eligible employees and lists them; clicking one assigns and closes the popover", async () => {
        axiosGet.mockResolvedValue({ data: [{ id: 3, name: "Els de Vries", block_reason: null, not_preferred: false }] });
        const w = mountTable();

        await w.get('[data-testid="cell-9-2026-09-17-0"] button').trigger("click");
        await flushPromises();

        expect(axiosGet).toHaveBeenCalledWith("/planning/eligible-employees", {
            params: { workcenter_id: 1, shift_id: 9, date: "2026-09-17" },
        });
        const popover = bodyWrapper().get('[data-testid="assign-popover"]');
        expect(popover.text()).toContain("Els de Vries");

        await popover.get("button").trigger("click");
        await flushPromises();

        expect(routerCalls).toContainEqual([
            "post",
            "/planning/assignments",
            { employee_id: 3, workcenter_id: 1, shift_id: 9, date: "2026-09-17" },
        ]);
        expect(bodyWrapper().find('[data-testid="assign-popover"]').exists()).toBe(false);
        w.unmount();
    });

    it("shows blocked employees only when Show all is checked and does not allow their assignment", async () => {
        axiosGet.mockResolvedValue({
            data: [
                { id: 3, name: "Els de Vries", block_reason: null, not_preferred: false },
                { id: 4, name: "Jan Smit", block_reason: "holiday", not_preferred: false },
            ],
        });
        const w = mountTable();

        await w.get('[data-testid="cell-9-2026-09-17-0"] button').trigger("click");
        await flushPromises();

        const popover = bodyWrapper().get('[data-testid="assign-popover"]');
        const eligibleOption = popover.get('[data-testid="employee-option-3"]');
        const showAll = popover.get('[data-testid="show-all-employees"]');

        expect(eligibleOption.attributes("disabled")).toBeUndefined();
        expect(showAll.element.checked).toBe(false);
        expect(popover.find('[data-testid="employee-option-4"]').exists()).toBe(false);

        await showAll.setValue(true);

        const blockedOption = popover.get('[data-testid="employee-option-4"]');
        expect(blockedOption.attributes("disabled")).toBeDefined();
        expect(blockedOption.text()).toContain("Jan Smit");
        expect(blockedOption.text()).toContain("This employee is on holiday that day.");

        await blockedOption.trigger("click");

        expect(routerCalls).toEqual([]);
        expect(bodyWrapper().find('[data-testid="assign-popover"]').exists()).toBe(true);
        w.unmount();
    });

    const openPopoverWith = async (names) => {
        axiosGet.mockResolvedValue({ data: names.map((name, i) => ({ id: i + 1, name, block_reason: null, not_preferred: false })) });
        const w = mountTable();
        await w.get('[data-testid="cell-9-2026-09-17-0"] button').trigger("click");
        await flushPromises();

        return w;
    };

    it("sizes the assign popover to its content and keeps names on one line", async () => {
        const w = await openPopoverWith(["Maria Alexandra van der Westhuizen-Oosterhoutstraat"]);
        const popover = bodyWrapper().get('[data-testid="assign-popover"]');

        expect(popover.classes()).toContain("w-max");
        expect(popover.classes()).toContain("min-w-48");
        expect(popover.classes()).not.toContain("w-48");
        expect(popover.get('[data-testid="assign-list"] li button span').classes()).toContain("whitespace-nowrap");
        w.unmount();
    });

    it("keeps the popover width steady while the search narrows the list", async () => {
        const w = await openPopoverWith(["Els de Vries", "Bram Bakker"]);
        const list = () => bodyWrapper().get('[data-testid="assign-list"]');
        const sizer = () => bodyWrapper().get('[data-testid="assign-sizer"]');
        expect(list().findAll("li")).toHaveLength(2);

        w.findComponent(SearchInput).vm.$emit("update:modelValue", "Els");
        await flushPromises();

        expect(list().findAll("li")).toHaveLength(1);
        // A hidden copy of the full list keeps the width the same.
        expect(sizer().attributes("aria-hidden")).toBe("true");
        expect(sizer().findAll("li").map((li) => li.text())).toEqual(["Els de Vries", "Bram Bakker"]);
        w.unmount();
    });

    it("flags a workcenter-not-preferred employee in the assign popover with a warning icon", async () => {
        axiosGet.mockResolvedValue({
            data: [{ id: 3, name: "Els de Vries", not_preferred: false, workcenter_not_preferred: true }],
        });
        const w = mountTable();

        await w.get('[data-testid="cell-9-2026-09-17-0"] button').trigger("click");
        await flushPromises();

        const popover = bodyWrapper().get('[data-testid="assign-popover"]');
        expect(popover.find('[data-testid="workcenter-not-preferred-icon"]').exists()).toBe(true);
        expect(popover.find('[data-testid="not-preferred-icon"]').exists()).toBe(false);
        w.unmount();
    });

    it("flips the assign popover above the trigger when there isn't room below the viewport", async () => {
        const originalInnerHeight = window.innerHeight;
        const offsetHeightSpy = vi.spyOn(window.HTMLElement.prototype, "offsetHeight", "get").mockReturnValue(200);
        window.innerHeight = 400;

        const w = mountTable();
        const trigger = w.get('[data-testid="cell-9-2026-09-17-0"] button');
        vi.spyOn(trigger.element, "getBoundingClientRect").mockReturnValue({
            top: 380, bottom: 390, left: 10, right: 50, width: 40, height: 10, x: 10, y: 380, toJSON: () => {},
        });

        await trigger.trigger("click");
        await flushPromises();

        const popover = bodyWrapper().get('[data-testid="assign-popover"]');
        expect(parseFloat(popover.element.style.top)).toBeLessThan(380);

        offsetHeightSpy.mockRestore();
        window.innerHeight = originalInnerHeight;
        w.unmount();
    });

    it("shows an unfulfilled-reason icon on an open cell matching this table's workcenter/shift/date", () => {
        const w = mountTable(baseCells, {
            unfulfilled: [{ workcenter_id: 1, shift_id: 9, date: "2026-09-17", reason: "no_eligible_employee" }],
        });

        const cell = w.get('[data-testid="cell-9-2026-09-17-0"]');
        expect(cell.find('[data-testid="unfulfilled-icon"]').exists()).toBe(true);
        expect(cell.get('[data-testid="unfulfilled-icon"]').attributes("title")).toBe("No eligible employee found.");
    });

    it("does not show the icon on a different date, shift, or workcenter", () => {
        const wrongDate = mountTable(baseCells, {
            unfulfilled: [{ workcenter_id: 1, shift_id: 9, date: "2026-09-18", reason: "no_eligible_employee" }],
        });
        expect(wrongDate.find('[data-testid="unfulfilled-icon"]').exists()).toBe(false);

        const wrongShift = mountTable(baseCells, {
            unfulfilled: [{ workcenter_id: 1, shift_id: 99, date: "2026-09-17", reason: "no_eligible_employee" }],
        });
        expect(wrongShift.find('[data-testid="unfulfilled-icon"]').exists()).toBe(false);

        const wrongWorkcenter = mountTable(baseCells, {
            unfulfilled: [{ workcenter_id: 99, shift_id: 9, date: "2026-09-17", reason: "no_eligible_employee" }],
        });
        expect(wrongWorkcenter.find('[data-testid="unfulfilled-icon"]').exists()).toBe(false);
    });

    it("shows no icon at all when nothing is unfulfilled", () => {
        const w = mountTable();
        expect(w.find('[data-testid="unfulfilled-icon"]').exists()).toBe(false);
    });

    it("shows the hard_cap_reached reason text", () => {
        const w = mountTable(baseCells, {
            unfulfilled: [{ workcenter_id: 1, shift_id: 9, date: "2026-09-17", reason: "hard_cap_reached" }],
        });
        expect(w.get('[data-testid="unfulfilled-icon"]').attributes("title")).toBe("Every eligible employee was blocked by a hard cap.");
    });

    it("still allows assigning through the open-spot button when the cell is unfulfilled", async () => {
        const w = mountTable(baseCells, {
            unfulfilled: [{ workcenter_id: 1, shift_id: 9, date: "2026-09-17", reason: "no_eligible_employee" }],
        });

        await w.get('[data-testid="cell-9-2026-09-17-0"] button[aria-label="Add employee"]').trigger("click");
        await flushPromises();

        expect(bodyWrapper().find('[data-testid="assign-popover"]').exists()).toBe(true);
        w.unmount();
    });
});
