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
const tabs = (w) =>
    w.get('[data-testid="preview-category-tabs"]').findAll('button[role="tab"]');
const clickTab = (w, key) =>
    tabs(w)[PREVIEW_CATEGORIES.findIndex((c) => c.key === key)].trigger("click");

describe("ThemeBuilder Menu table", () => {
    it("is two Background/Text tables: Disabled/Normal left, Hover/Selected right", async () => {
        const w = mountBuilder();
        await clickTab(w, "menu");
        const [left, right] = w.findAllComponents(ColorTokenTable);

        for (const t of [left, right]) {
            expect(t.findAll("thead th").map((th) => th.text())).toEqual([
                "",
                "Background",
                "Text",
            ]);
            expect(t.findAllComponents(ColorTokenPicker)).toHaveLength(4);
        }
        expect(left.findAll("tbody th").map((th) => th.text())).toEqual([
            "Disabled",
            "Normal",
        ]);
        expect(right.findAll("tbody th").map((th) => th.text())).toEqual([
            "Hover",
            "Selected",
        ]);
    });

    it("binds the hover background cell to menu_item_hover_bg", async () => {
        const w = mountBuilder();
        await clickTab(w, "menu");
        const style = () => w.get("[data-theme-preview]").attributes("style");
        expect(style()).toContain(
            "--color-menu-item-hover-bg: var(--color-zinc-100)",
        );

        // right table, row 0 (Hover), column 0 (Background) => picker index 0
        const right = w.findAllComponents(ColorTokenTable)[1];
        await right
            .findAllComponents(ColorTokenPicker)[0]
            .vm.$emit("update:modelValue", "orange-200");

        expect(style()).toContain(
            "--color-menu-item-hover-bg: var(--color-orange-200)",
        );
    });

    it("shows the menu items in the frame sidebar, not the sidebar-text heading", async () => {
        const w = mountBuilder();
        const sidebar = () =>
            w.get(".flex.flex-1.flex-col.gap-y-0\\.5").text();

        // chrome (default): just the heading
        expect(sidebar()).toBe("SIDEBAR Text");

        await clickTab(w, "menu");
        expect(sidebar()).not.toContain("SIDEBAR Text");
        for (const s of ["disabled", "normal", "hover", "selected"]) {
            expect(sidebar()).toContain(`Menu item ${s}`);
        }

        await clickTab(w, "text");
        expect(sidebar()).toBe("SIDEBAR Text");
    });
});
