import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "settings.title": "Settings",
    "settings.tab.competences": "Competences",
    "settings.tab.product_groups": "Product groups",
    "competences.name": "Name",
    "competences.list_empty": "No competences yet.",
    "competences.add_placeholder": "New competence",
    "product_groups.name": "Name",
    "product_groups.list_empty": "No product groups yet.",
    "product_groups.add_placeholder": "New product group",
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

const mountPage = (props = {}) =>
    mount(Settings, {
        props: { competences: [], productGroups: [], ...props },
        global: { stubs },
    });

describe("Settings/Index", () => {
    it("shows a Competences and a Product groups tab", () => {
        const text = mountPage().text();
        expect(text).toContain("Competences");
        expect(text).toContain("Product groups");
    });

    it("mounts a list per tab against its own endpoint and prefix", () => {
        const lists = mountPage({
            competences: [{ id: 1, name: "Forklift", position: 1, holder_count: 0 }],
            productGroups: [
                { id: 5, name: "Pumps", position: 1, holder_count: 0 },
                { id: 6, name: "Valves", position: 2, holder_count: 0 },
            ],
        }).findAllComponents(OrderedNameList);

        const byEndpoint = Object.fromEntries(lists.map((l) => [l.props("endpoint"), l]));

        expect(byEndpoint["/settings/competences"].props("i18nPrefix")).toBe("competences");
        expect(byEndpoint["/settings/competences"].props("items")).toHaveLength(1);
        expect(byEndpoint["/settings/product-groups"].props("i18nPrefix")).toBe("product_groups");
        expect(byEndpoint["/settings/product-groups"].props("items")).toHaveLength(2);
    });

    it("switches to the Product groups panel when its tab is clicked", async () => {
        const w = mountPage();
        const hidden = (sel) => (w.get(sel).attributes("style") ?? "").includes("display: none");

        expect(hidden('[data-testid="panel-product-groups"]')).toBe(true);

        const tab = w.findAll("button").find((b) => b.text() === "Product groups");
        await tab.trigger("click");
        await w.vm.$nextTick();

        expect(hidden('[data-testid="panel-product-groups"]')).toBe(false);
        expect(hidden('[data-testid="panel-competences"]')).toBe(true);
    });
});
