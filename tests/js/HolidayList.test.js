import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "availability.holidays.start": "Start",
    "availability.holidays.end": "End",
    "availability.holidays.note": "Note",
    "availability.holidays.empty": "No holidays.",
    "availability.holidays.add": "Add",
    "availability.holidays.delete": "Remove",
};

const { router } = vi.hoisted(() => ({ router: { post: vi.fn(), delete: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    usePage: () => ({ props: { translations: en } }),
}));

import HolidayList from "@/components/HolidayList.vue";
import { DateInput } from "@/components/ui/Input";

const holidays = [
    { id: 1, start_date: "2026-06-01", end_date: "2026-06-05", note: "Early" },
    { id: 2, start_date: "2026-09-01", end_date: "2026-09-03", note: null },
];

const mountList = (props = {}) =>
    mount(HolidayList, {
        props: { holidays, endpoint: "/employees/7/holidays", ...props },
    });

beforeEach(() => {
    router.post.mockReset();
    router.delete.mockReset();
});

describe("HolidayList", () => {
    it("renders a row per holiday", () => {
        const w = mountList();
        expect(w.findAll('[data-testid="holiday-row"]')).toHaveLength(2);
        expect(w.text()).toContain("2026-06-01");
        expect(w.text()).toContain("Early");
    });

    it("shows an empty state when there are no holidays", () => {
        const w = mountList({ holidays: [] });
        expect(w.text()).toContain("No holidays.");
    });

    it("keeps the add inputs as a row inside the same table", () => {
        const w = mountList();
        const addRow = w.get('[data-testid="holiday-add-row"]');

        expect(addRow.element.tagName).toBe("TR");
        expect(addRow.findAllComponents(DateInput)).toHaveLength(2);
        expect(w.findAll("table")).toHaveLength(1);
    });

    it("posts a new holiday to the endpoint and stays on the page", async () => {
        const w = mountList({ holidays: [] });
        const dates = w.findAllComponents(DateInput);
        dates[0].vm.$emit("update:modelValue", "2026-07-01");
        dates[1].vm.$emit("update:modelValue", "2026-07-14");
        await w.vm.$nextTick();

        await w.get("form").trigger("submit");

        expect(router.post).toHaveBeenCalledTimes(1);
        const [url, payload, opts] = router.post.mock.calls[0];
        expect(url).toBe("/employees/7/holidays");
        expect(payload).toMatchObject({ start_date: "2026-07-01", end_date: "2026-07-14" });
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });
    });

    it("deletes a row through the endpoint and stays on the page", async () => {
        const w = mountList();
        await w.findAll('[data-testid="holiday-row"]')[0].get("button").trigger("click");

        expect(router.delete).toHaveBeenCalledTimes(1);
        const [url, opts] = router.delete.mock.calls[0];
        expect(url).toBe("/employees/7/holidays/1");
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });
    });

    it("hides the add row and the delete buttons when disabled", () => {
        const w = mountList({ disabled: true });

        expect(w.find('[data-testid="holiday-add-row"]').exists()).toBe(false);
        expect(w.find('[data-testid="holiday-row"] button').exists()).toBe(false);
        // The rows themselves still show.
        expect(w.findAll('[data-testid="holiday-row"]')).toHaveLength(2);
    });
});
