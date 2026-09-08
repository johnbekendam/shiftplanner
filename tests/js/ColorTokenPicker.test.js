import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";

import ColorTokenPicker from "@/components/ui/ColorTokenPicker.vue";

const trigger = (w) => w.get("button");

describe("ColorTokenPicker", () => {
    it("puts the current value in the trigger tooltip", () => {
        const w = mount(ColorTokenPicker, { props: { modelValue: "indigo-800" } });
        expect(trigger(w).attributes("title")).toBe("indigo-800");
    });

    it("falls back to 'unset' when nothing is selected", () => {
        const w = mount(ColorTokenPicker, { props: { modelValue: null } });
        expect(trigger(w).attributes("title")).toBe("unset");
    });

    it("marks a branding value with a badge and a (branding) tooltip", () => {
        const w = mount(ColorTokenPicker, {
            props: { modelValue: "brand-600" },
        });
        expect(trigger(w).attributes("title")).toBe("brand-600 (branding)");
        expect(trigger(w).find("span").exists()).toBe(true);
        expect(trigger(w).find("span").text()).toBe("B");
    });

    it("shows no badge for an ordinary colour", () => {
        const w = mount(ColorTokenPicker, { props: { modelValue: "red-500" } });
        expect(trigger(w).find("span").exists()).toBe(false);
    });
});
