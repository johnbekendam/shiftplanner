import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "settings.title": "Settings",
    "settings.tab.competences": "Competences",
    "competences.name": "Name",
    "competences.list_empty": "No competences yet.",
    "competences.add_placeholder": "New competence",
};

const { router } = vi.hoisted(() => ({
    router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock("@inertiajs/vue3", () => ({
    router,
    Head: { name: "Head", render: () => null },
    usePage: () => ({ props: { translations: en } }),
}));

import Settings from "@/pages/Settings/Index.vue";
import OrderedNameList from "@/components/OrderedNameList.vue";

const stubs = { AppLayout: { template: "<div><slot /></div>" } };

const mountPage = (competences = []) =>
    mount(Settings, { props: { competences }, global: { stubs } });

describe("Settings/Index", () => {
    it("shows a Competences tab", () => {
        expect(mountPage().text()).toContain("Competences");
    });

    it("mounts the competence list against the settings endpoint", () => {
        const list = mountPage([{ id: 1, name: "Forklift", position: 1, holder_count: 0 }])
            .findComponent(OrderedNameList);
        expect(list.props("endpoint")).toBe("/settings/competences");
        expect(list.props("i18nPrefix")).toBe("competences");
        expect(list.props("items")).toHaveLength(1);
    });
});
