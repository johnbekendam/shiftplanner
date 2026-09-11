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

    it("does not write and disables the boxes when disabled", async () => {
        const w = mountList({ disabled: true });
        const boxes = w.findAllComponents(CheckboxInput);
        expect(boxes.every((b) => b.props("disabled") === true)).toBe(true);

        boxes[0].vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(router.put).not.toHaveBeenCalled();
    });

    it("writes answer:false when a question is checked off", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[1].vm.$emit("update:modelValue", false);
        await w.vm.$nextTick();

        const [url, data] = router.put.mock.calls[0];
        expect(url).toBe("/employees/7/questions/2");
        expect(data).toEqual({ answer: false });
    });

    it("shows a pending spinner and disables the box while the write is in flight", async () => {
        const w = mountList();
        const box = w.findAllComponents(CheckboxInput)[0];
        box.vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(box.props("disabled")).toBe(true);
        expect(w.find('[data-testid="question-pending"]').exists()).toBe(true);

        router.put.mock.calls[0][2].onFinish();
        await w.vm.$nextTick();

        expect(box.props("disabled")).toBe(false);
        expect(w.find('[data-testid="question-pending"]').exists()).toBe(false);
    });

    it("flashes an error outline when the write fails, no spinner left behind", async () => {
        const w = mountList();
        const box = w.findAllComponents(CheckboxInput)[0];
        box.vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        const opts = router.put.mock.calls[0][2];
        opts.onError();
        opts.onFinish();
        await w.vm.$nextTick();

        expect(box.find("input").classes()).toContain("outline-[var(--color-badge-error-border)]");
        expect(w.find('[data-testid="question-pending"]').exists()).toBe(false);
    });
});
