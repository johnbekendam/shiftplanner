import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { themeBuilderProps, en } from "./support/themeBuilderProps";

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a><slot /></a>" },
    router: { post: () => {}, get: () => {}, visit: () => {} },
    usePage: () => ({ props: { translations: en, logoUrl: null } }),
}));

import ThemeBuilder from "@/pages/ThemeBuilder.vue";
import BrandFamilyPicker from "@/components/ui/BrandFamilyPicker.vue";

const mountBuilder = () => mount(ThemeBuilder, { props: themeBuilderProps });

describe("ThemeBuilder branding colour", () => {
    it("shows a Branding picker in the card header", () => {
        const w = mountBuilder(); // chrome active by default
        const row = w.get('[data-testid="branding-row"]');

        expect(row.text()).toContain("Branding");
        expect(row.findComponent(BrandFamilyPicker).props("modelValue")).toBe(
            "sky",
        );
    });

    it("emits the 11-shade brand ramp into the preview, pointed at the family", () => {
        const w = mountBuilder();
        const style = w.get("[data-theme-preview]").attributes("style");

        expect(style).toContain("--color-brand-50: var(--color-sky-50)");
        expect(style).toContain("--color-brand-600: var(--color-sky-600)");
        expect(style).toContain("--color-brand-950: var(--color-sky-950)");
    });

    it("re-points the whole ramp when the family changes", async () => {
        const w = mountBuilder();
        const style = () => w.get("[data-theme-preview]").attributes("style");

        await w
            .get('[data-testid="branding-row"]')
            .findComponent(BrandFamilyPicker)
            .vm.$emit("update:modelValue", "emerald");

        expect(style()).toContain("--color-brand-50: var(--color-emerald-50)");
        expect(style()).toContain("--color-brand-600: var(--color-emerald-600)");
        expect(style()).toContain("--color-brand-950: var(--color-emerald-950)");
        expect(style()).not.toContain("--color-brand-50: var(--color-indigo-50)");
    });

    it("publishes the live ramp on :root so brand swatches outside the preview track it", async () => {
        const root = document.documentElement;
        const w = mountBuilder();

        expect(root.style.getPropertyValue("--color-brand-600")).toBe(
            "var(--color-sky-600)",
        );

        await w
            .get('[data-testid="branding-row"]')
            .findComponent(BrandFamilyPicker)
            .vm.$emit("update:modelValue", "emerald");

        expect(root.style.getPropertyValue("--color-brand-600")).toBe(
            "var(--color-emerald-600)",
        );

        w.unmount();
        expect(root.style.getPropertyValue("--color-brand-600")).toBe("");
    });

    it("keeps the branding picker visible on every tab", async () => {
        const w = mountBuilder();
        const tabs = w
            .get('[data-testid="preview-category-tabs"]')
            .findAll('button[role="tab"]');
        await tabs[1].trigger("click"); // menu

        expect(w.find('[data-testid="branding-row"]').exists()).toBe(true);
        expect(
            w
                .get('[data-testid="branding-row"]')
                .findComponent(BrandFamilyPicker)
                .exists(),
        ).toBe(true);
    });
});
