import { describe, it, expect, vi } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { themeBuilderProps, en } from "./support/themeBuilderProps";

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a><slot /></a>" },
    router: { post: () => {}, get: () => {}, visit: () => {} },
    usePage: () => ({ props: { translations: en, logoUrl: null } }),
}));

vi.mock("axios", () => ({
    default: { post: vi.fn().mockResolvedValue({}), delete: vi.fn().mockResolvedValue({}) },
}));

import ThemeBuilder from "@/pages/ThemeBuilder.vue";

const footer = (w) => w.get('[data-testid="card-footer"]');

describe("ThemeBuilder footer", () => {
    it("orders the buttons Reset, Cancel, Save with single-word labels", () => {
        const w = mount(ThemeBuilder, { props: themeBuilderProps });
        const labels = footer(w)
            .findAll("button")
            .map((b) => b.text());
        expect(labels).toEqual(["Reset", "Cancel", "Save"]);
    });

    it("makes Reset a danger button pinned left", () => {
        const w = mount(ThemeBuilder, { props: themeBuilderProps });
        const buttons = footer(w).findAll("button");
        const reset = buttons[0];
        expect(reset.text()).toBe("Reset");
        expect(reset.classes().join(" ")).toContain(
            "bg-[var(--color-btn-danger-bg)]",
        );
        // Cancel starts the right-aligned group
        expect(buttons[1].classes()).toContain("ml-auto");
    });

    it("shows the saved confirmation left of the action group after a save", async () => {
        const w = mount(ThemeBuilder, { props: themeBuilderProps });
        expect(footer(w).text()).not.toContain("Saved");

        await footer(w)
            .findAll("button")
            .find((b) => b.text() === "Save")
            .trigger("click");
        await flushPromises();

        const html = footer(w).html();
        expect(html).toContain("Saved");
        expect(html.indexOf("Saved")).toBeLessThan(html.indexOf("ml-auto"));
    });
});
