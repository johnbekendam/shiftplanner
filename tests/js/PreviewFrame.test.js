import { describe, it, expect } from "vitest";
import { mount } from "@vue/test-utils";
import PreviewFrame from "@/pages/themeBuilder/PreviewFrame.vue";

describe("PreviewFrame", () => {
    it("renders the crumbs into the breadcrumb", () => {
        const w = mount(PreviewFrame, { props: { crumbs: ["Home", "Forms"] } });
        const crumb = w.get('[data-testid="frame-breadcrumb"]');
        expect(crumb.text()).toContain("Home");
        expect(crumb.text()).toContain("Forms");
    });

    it("shows only the sidebar-text heading in the sidebar by default", () => {
        const w = mount(PreviewFrame);
        const sidebar = w.get(".flex.flex-1.flex-col.gap-y-0\\.5");
        expect(sidebar.text()).toBe("SIDEBAR Text");
        expect(sidebar.element.children).toHaveLength(1);
    });

    it("lets a #sidebar slot replace the sidebar-text heading", () => {
        const w = mount(PreviewFrame, {
            slots: { sidebar: "<p>custom nav</p>" },
        });
        const sidebar = w.get(".flex.flex-1.flex-col.gap-y-0\\.5");
        expect(sidebar.text()).toBe("custom nav");
        expect(sidebar.text()).not.toContain("SIDEBAR Text");
    });

    it("gives the logo cell and header the same fixed height (one long header)", () => {
        const w = mount(PreviewFrame, { props: { crumbs: ["Home"] } });
        const logo = w.get(".w-60 > div").element;
        const header = w.get('[data-testid="frame-breadcrumb"]').element.parentElement;

        const heightClass = (el) =>
            [...el.classList].find((c) => /^h-\d/.test(c));

        // neither cell may derive its height from vertical padding / content
        expect([...logo.classList].some((c) => c.startsWith("py-"))).toBe(false);
        expect([...header.classList].some((c) => c.startsWith("py-"))).toBe(false);

        // both carry the same explicit height so their borders line up
        expect(heightClass(logo)).toBeDefined();
        expect(heightClass(header)).toBe(heightClass(logo));
    });

    it("renders slot content in the content area", () => {
        const w = mount(PreviewFrame, {
            slots: { default: "<p>composition here</p>" },
        });
        expect(w.text()).toContain("composition here");
    });

    it("fits its container rather than capping its own width", () => {
        const w = mount(PreviewFrame);
        const cls = w.get("[data-theme-preview]").classes();
        expect(cls).toContain("w-full");
        expect(cls.some((c) => c.startsWith("max-w-"))).toBe(false);
    });

    it("passes styleVars through to the frame element", () => {
        const w = mount(PreviewFrame, {
            props: { styleVars: "--role-surface: white" },
        });
        expect(w.get("[data-theme-preview]").attributes("style")).toContain(
            "--role-surface: white",
        );
    });
});
