import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";

import TabsComposition from "@/pages/themeBuilder/compositions/TabsComposition.vue";
import Card from "@/components/ui/Card.vue";

describe("TabsComposition", () => {
    it("puts the tab bar in the header slot of a real Card", () => {
        const w = mount(TabsComposition);
        const card = w.findComponent(Card);
        expect(card.exists()).toBe(true);

        const header = card.get('div[class*="rounded-t-lg"]');
        expect(header.text()).toContain("Active");
        const style = (el) => (el.attributes("style") ?? "").replace(/\s+/g, "");
        expect(style(header.find("div"))).toContain(
            "background-color:var(--color-tab-bg)",
        );
    });

    it("has no table or pagination", () => {
        const w = mount(TabsComposition);
        const html = w.html();
        expect(html).not.toContain("--color-table-row-bg");
        expect(html).not.toContain("--color-pagination-active-bg");
    });

    it("shows 4 tabs, evenly distributed across the bar", () => {
        const w = mount(TabsComposition);
        const bar = w
            .findComponent(Card)
            .get('div[class*="rounded-t-lg"]')
            .find("div");
        const tabs = bar.findAll(":scope > div");

        expect(tabs).toHaveLength(4);
        for (const tab of tabs) {
            expect(tab.classes()).toContain("flex-1");
            expect(tab.classes()).toContain("text-center");
        }
    });
});
