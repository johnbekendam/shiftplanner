import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import Tabs from "@/components/ui/Tabs.vue";

const tabs = [
    { value: "details", label: "Details" },
    { value: "availability", label: "Availability" },
];

describe("Tabs", () => {
    it("renders one button per tab with its label", () => {
        const w = mount(Tabs, { props: { tabs, modelValue: "details" } });
        const btns = w.findAll("button");

        expect(btns).toHaveLength(2);
        expect(btns[0].text()).toBe("Details");
        expect(btns[1].text()).toBe("Availability");
    });

    it("marks the active tab with the active tokens and the rest inactive", () => {
        const w = mount(Tabs, { props: { tabs, modelValue: "availability" } });
        const [details, availability] = w.findAll("button");

        expect(availability.classes().join(" ")).toContain("text-(--color-tab-active-text)");
        expect(details.classes().join(" ")).toContain("text-(--color-tab-text)");
        expect(details.classes().join(" ")).not.toContain("text-(--color-tab-active-text)");
    });

    it("emits update:modelValue with the clicked tab value", async () => {
        const w = mount(Tabs, { props: { tabs, modelValue: "details" } });
        await w.findAll("button")[1].trigger("click");

        expect(w.emitted("update:modelValue")).toEqual([["availability"]]);
    });
});
