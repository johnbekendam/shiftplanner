import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import ButtonDanger from "@/components/ui/ButtonDanger.vue";

describe("ButtonDanger", () => {
    it("renders the danger tokens", () => {
        const w = mount(ButtonDanger, { slots: { default: "Reset" } });
        const cls = w.get("button").classes().join(" ");
        expect(w.text()).toBe("Reset");
        expect(cls).toContain("bg-[var(--color-btn-danger-bg)]");
        expect(cls).toContain("text-[var(--color-btn-danger-text)]");
        expect(cls).toContain("hover:bg-[var(--color-btn-danger-hover-bg)]");
    });

    it("applies the disabled colour tokens when disabled", () => {
        const w = mount(ButtonDanger, {
            attrs: { disabled: "" },
            slots: { default: "Reset" },
        });
        expect(w.get("button").attributes("disabled")).toBeDefined();
        const cls = w.get("button").classes().join(" ");
        expect(cls).toContain("bg-[var(--color-btn-danger-disabled-bg)]");
        expect(cls).toContain("cursor-not-allowed");
        expect(cls).not.toContain("bg-[var(--color-btn-danger-bg)]");
    });

    it("forces the hover look with state=\"hover\"", () => {
        const w = mount(ButtonDanger, {
            props: { state: "hover" },
            slots: { default: "Reset" },
        });
        const cls = w.get("button").classes().join(" ");
        expect(cls).toContain("bg-[var(--color-btn-danger-hover-bg)]");
        expect(cls).not.toContain("hover:bg-[var(--color-btn-danger-hover-bg)]");
    });

    it("renders a leading icon when given", () => {
        const w = mount(ButtonDanger, {
            props: { icon: "x-mark" },
            slots: { default: "Reset" },
        });
        expect(w.findComponent({ name: "Icon" }).exists()).toBe(true);
    });
});
