import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";

import QuestionChecklist from "@/components/QuestionChecklist.vue";
import { CheckboxInput } from "@/components/ui/Input";

const items = [
    { id: 1, text: "Can we contact you to work in the weekend?" },
    { id: 2, text: "Can we contact you to work in week 53?" },
];

const mountList = (props = {}) =>
    mount(QuestionChecklist, { props: { items, answeredIds: [2], ...props } });

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

    it("checks the box locally and emits update:answeredIds, without a network call", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[0].vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(w.findAllComponents(CheckboxInput)[0].props("modelValue")).toBe(true);
        expect(w.emitted("update:answeredIds").at(-1)[0]).toEqual([2, 1]);
    });

    it("unchecks the box locally and emits update:answeredIds", async () => {
        const w = mountList();
        w.findAllComponents(CheckboxInput)[1].vm.$emit("update:modelValue", false);
        await w.vm.$nextTick();

        expect(w.emitted("update:answeredIds").at(-1)[0]).toEqual([]);
    });

    it("does not toggle and disables the boxes when disabled", async () => {
        const w = mountList({ disabled: true });
        const boxes = w.findAllComponents(CheckboxInput);
        expect(boxes.every((b) => b.props("disabled") === true)).toBe(true);

        boxes[0].vm.$emit("update:modelValue", true);
        await w.vm.$nextTick();

        expect(w.emitted("update:answeredIds")).toBeUndefined();
    });
});
