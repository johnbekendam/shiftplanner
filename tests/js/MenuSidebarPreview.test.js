import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import MenuSidebarPreview from "@/pages/themeBuilder/MenuSidebarPreview.vue";

describe("MenuSidebarPreview", () => {
    it("renders one item per menu-item state, coloured from the state vars", () => {
        const w = mount(MenuSidebarPreview);
        expect(w.findAll(".rounded-md")).toHaveLength(4);

        // disabled, then normal, hover, selected
        expect(w.findAll(".rounded-md").map((r) => r.text())).toEqual([
            "Menu item disabled",
            "Menu item normal",
            "Menu item hover",
            "Menu item selected",
        ]);
        const html = w.html();
        expect(html).toContain("var(--color-menu-item-hover-bg)");
        expect(html).toContain("var(--color-menu-item-selected-text)");
        expect(html).toContain("var(--color-menu-item-disabled-bg)");
    });
});
