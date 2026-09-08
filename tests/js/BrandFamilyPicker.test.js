import { describe, it, expect, afterEach } from "vitest";
import { mount } from "@vue/test-utils";

import BrandFamilyPicker from "@/components/ui/BrandFamilyPicker.vue";

let wrapper;

afterEach(() => {
    wrapper?.unmount();
    document.body.innerHTML = "";
});

const open = async (w) => {
    await w.get("button").trigger("click");
};

describe("BrandFamilyPicker", () => {
    it("shows a swatch for the current family, with the name as a tooltip", () => {
        wrapper = mount(BrandFamilyPicker, {
            props: { modelValue: "indigo" },
            attachTo: document.body,
        });

        expect(wrapper.get("button").attributes("title")).toBe("indigo");
        expect(wrapper.get("span[style]").attributes("style")).toContain(
            "var(--color-indigo-500)",
        );
    });

    it("opens a grid of every Tailwind family", async () => {
        wrapper = mount(BrandFamilyPicker, {
            props: { modelValue: "indigo" },
            attachTo: document.body,
        });
        await open(wrapper);

        const names = document.body.querySelectorAll(
            ".fixed.z-50 button .font-mono",
        );
        const labels = [...names].map((n) => n.textContent);
        expect(labels).toContain("slate");
        expect(labels).toContain("rose");
        expect(labels).toContain("emerald");
        expect(labels.length).toBeGreaterThanOrEqual(22);
    });

    it("emits the picked family and closes", async () => {
        wrapper = mount(BrandFamilyPicker, {
            props: { modelValue: "indigo" },
            attachTo: document.body,
        });
        await open(wrapper);

        const emeraldBtn = [
            ...document.body.querySelectorAll(".fixed.z-50 button"),
        ].find((b) => b.textContent.trim() === "emerald");
        emeraldBtn.dispatchEvent(new Event("click", { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted("update:modelValue")[0]).toEqual(["emerald"]);
        expect(document.body.querySelector(".fixed.z-50")).toBeNull();
    });
});
