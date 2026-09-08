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

describe("ThemeBuilder page order", () => {
    it("orders tabs, example, settings card, then footer inside the main card", () => {
        const html = mount(ThemeBuilder, { props: themeBuilderProps }).html();

        const order = [
            'data-testid="theme-builder-card"',
            'data-testid="preview-category-tabs"',
            "data-theme-preview",
            'data-testid="settings-panel"',
            'data-testid="card-footer"',
        ].map((marker) => {
            const at = html.indexOf(marker);
            expect(at, marker).toBeGreaterThan(-1);
            return at;
        });

        expect(order).toEqual([...order].sort((a, b) => a - b));
    });
});
