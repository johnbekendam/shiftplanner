import { describe, it, expect, vi, beforeEach } from "vitest";
import { mount } from "@vue/test-utils";

const { router } = vi.hoisted(() => ({ router: { put: vi.fn() } }));

vi.mock("@inertiajs/vue3", () => ({
    router,
    usePage: () => ({ props: { translations: {} } }),
}));

import QuestionChecklist from "@/components/QuestionChecklist.vue";
import { CheckboxInput } from "@/components/ui/Input";

const items = [
    { id: 1, text: "Can we contact you to work in the weekend?" },
    { id: 2, text: "Can we contact you to work in week 53?" },
];

const mountList = (props = {}) =>
    mount(QuestionChecklist, {
        props: {
            items,
            answeredIds: [2],
            endpoint: "/employees/7/questions",
            ...props,
        },
    });

beforeEach(() => router.put.mockReset());

describe("QuestionChecklist", () => {
    it("renders a checkbox per question with its text", () => {
        const w = mountList();
        expect(w.findAllComponents(CheckboxInput)).toHaveLength(2);
        expect(w.text()).toContain("Can we contact you to work in the weekend?");
        expect(w.text()).toContain("Can we contact you to work in week 53?");
    });

    it("checks the questions the employee answered yes", () => {
        const boxes = mountList().findAllComponents(CheckboxInput);
        expect(boxes[0].props("modelValue")).toBe(false);
        expect(boxes[1].props("modelValue")).toBe(true);
    });

    it("writes answer:true when a question is checked on", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[0].vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, data, opts] = router.put.mock.calls[0];
        expect(url).toBe("/employees/7/questions/1");
        expect(data).toEqual({ answer: true });
        expect(opts).toMatchObject({ preserveScroll: true, preserveState: true });
    });

    it("writes answer:false when a question is checked off", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[1].vm.$emit("update:modelValue", false);
        await w.vm.$nextTick();

        const [url, data] = router.put.mock.calls[0];
        expect(url).toBe("/employees/7/questions/2");
        expect(data).toEqual({ answer: false });
    });
});
