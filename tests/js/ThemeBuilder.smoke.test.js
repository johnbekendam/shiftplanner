import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { themeBuilderProps } from "./support/themeBuilderProps";

// Inertia needs a running app for usePage(); stub it for a unit mount.
vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a><slot /></a>" },
    router: { post: () => {}, get: () => {}, visit: () => {} },
    usePage: () => ({ props: { translations: {}, logoUrl: null } }),
}));

import ThemeBuilder from "@/pages/ThemeBuilder.vue";

describe("ThemeBuilder", () => {
    it("mounts without throwing", () => {
        const wrapper = mount(ThemeBuilder, { props: themeBuilderProps });
        expect(wrapper.exists()).toBe(true);
    });
});
