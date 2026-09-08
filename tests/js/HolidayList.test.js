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
        const bodyRows = w.findAll("tbody tr");
        expect(bodyRows).toHaveLength(2);
        expect(w.text()).toContain("2026-06-01");
        expect(w.text()).toContain("Early");
    });

    it("shows an empty state when there are no holidays", () => {
        const w = mountList({ holidays: [] });
        expect(w.text()).toContain("No holidays.");
    });

    it("posts a new holiday to the endpoint", async () => {
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
        expect(opts).toMatchObject({ preserveScroll: true });
    });

    it("deletes a row through the endpoint and its id", async () => {
        const w = mountList();
        await w.findAll("tbody tr")[0].get("button").trigger("click");

        expect(router.delete).toHaveBeenCalledTimes(1);
        const [url, opts] = router.delete.mock.calls[0];
        expect(url).toBe("/employees/7/holidays/1");
        expect(opts).toMatchObject({ preserveScroll: true });
    });
});
