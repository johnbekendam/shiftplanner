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
import ColorTokenPicker from "@/components/ui/ColorTokenPicker.vue";
import ColorTokenTable from "@/pages/themeBuilder/ColorTokenTable.vue";
import { PREVIEW_CATEGORIES } from "@/pages/themeBuilder/previewCategories";

const mountBuilder = () => mount(ThemeBuilder, { props: themeBuilderProps });
const clickTab = (w, key) =>
    w
        .get('[data-testid="preview-category-tabs"]')
        .findAll('button[role="tab"]')
        [PREVIEW_CATEGORIES.findIndex((c) => c.key === key)].trigger("click");

describe("ThemeBuilder Text tab", () => {
    it("is two tables: text types left, link states right", async () => {
        const w = mountBuilder();
        await clickTab(w, "text");
        const [left, right] = w.findAllComponents(ColorTokenTable);

        expect(left.findAll("tbody th").map((t) => t.text())).toEqual([
            "Heading",
            "Normal",
            "Muted",
        ]);
        expect(right.findAll("tbody th").map((t) => t.text())).toEqual([
            "Link normal",
            "Link hover",
        ]);
        expect(left.findAllComponents(ColorTokenPicker)).toHaveLength(3);
        expect(right.findAllComponents(ColorTokenPicker)).toHaveLength(2);
    });

    it("edits text_link_hover from the right table's Link hover row", async () => {
        const w = mountBuilder();
        await clickTab(w, "text");
        const style = () => w.get("[data-theme-preview]").attributes("style");
        expect(style()).toContain(
            "--color-text-link-hover: var(--color-brand-500)",
        );

        const right = w.findAllComponents(ColorTokenTable)[1];
        await right
            .findAllComponents(ColorTokenPicker)[1]
            .vm.$emit("update:modelValue", "sky-500");

        expect(style()).toContain("--color-text-link-hover: var(--color-sky-500)");
    });
});
