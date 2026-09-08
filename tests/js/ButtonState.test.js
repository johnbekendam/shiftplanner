import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import ButtonPrimary from "@/components/ui/ButtonPrimary.vue";
import ButtonSecondary from "@/components/ui/ButtonSecondary.vue";
import ButtonDanger from "@/components/ui/ButtonDanger.vue";

const variants = [
    ["primary", ButtonPrimary],
    ["secondary", ButtonSecondary],
    ["danger", ButtonDanger],
];

describe.each(variants)("Button %s state prop", (name, Comp) => {
    const cls = (w) => w.get("button").classes().join(" ");

    it("normal: base colours + live :hover", () => {
        const c = cls(mount(Comp, { slots: { default: "x" } }));
        expect(c).toContain(`bg-[var(--color-btn-${name}-bg)]`);
        expect(c).toContain(`hover:bg-[var(--color-btn-${name}-hover-bg)]`);
    });

    it("state=hover: hover colours applied directly, no :hover", () => {
        const c = cls(mount(Comp, { props: { state: "hover" }, slots: { default: "x" } }));
        expect(c).toContain(`bg-[var(--color-btn-${name}-hover-bg)]`);
        expect(c).not.toContain(`hover:bg-[var(--color-btn-${name}-hover-bg)]`);
    });

    it("state=disabled and the disabled attr both use the disabled tokens", () => {
        for (const opts of [
            { props: { state: "disabled" } },
            { attrs: { disabled: "" } },
            { attrs: { disabled: true } },
        ]) {
            const c = cls(mount(Comp, { ...opts, slots: { default: "x" } }));
            expect(c).toContain(`bg-[var(--color-btn-${name}-disabled-bg)]`);
            expect(c).toContain(`text-[var(--color-btn-${name}-disabled-text)]`);
            expect(c).toContain("cursor-not-allowed");
            expect(c).not.toContain(`bg-[var(--color-btn-${name}-bg)]`);
        }
    });

    it("disabled={false} stays enabled (bound :disabled with a falsy value)", () => {
        const c = cls(mount(Comp, { attrs: { disabled: false }, slots: { default: "x" } }));
        expect(c).toContain(`bg-[var(--color-btn-${name}-bg)]`);
        expect(c).not.toContain(`bg-[var(--color-btn-${name}-disabled-bg)]`);
        expect(c).not.toContain("cursor-not-allowed");
    });
});
