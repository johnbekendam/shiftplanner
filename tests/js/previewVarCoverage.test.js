import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { readFileSync, readdirSync } from "node:fs";
import { resolve, join } from "node:path";

import { themeBuilderProps, en } from "./support/themeBuilderProps";

vi.mock("@inertiajs/vue3", () => ({
    Head: { name: "Head", render: () => null },
    Link: { name: "Link", props: ["href"], template: "<a><slot /></a>" },
    router: { post: () => {}, get: () => {}, visit: () => {} },
    usePage: () => ({ props: { translations: en, logoUrl: null } }),
}));

import ThemeBuilder from "@/pages/ThemeBuilder.vue";

// Files whose --color-* usage the preview must inject: the frame, every
// composition/overlay, and the real components they render.
function filesToScan() {
    const dir = resolve(process.cwd(), "resources/js/pages/themeBuilder");
    const walk = (d) =>
        readdirSync(d, { withFileTypes: true }).flatMap((e) => {
            const p = join(d, e.name);
            return e.isDirectory()
                ? walk(p)
                : /\.(vue|js)$/.test(e.name)
                  ? [p]
                  : [];
        });
    const ui = resolve(process.cwd(), "resources/js/components/ui");
    return [
        ...walk(dir),
        join(ui, "Card.vue"),
        join(ui, "ButtonPrimary.vue"),
        join(ui, "ButtonSecondary.vue"),
        join(ui, "Icon.vue"),
        resolve(process.cwd(), "resources/js/components/LabeledInput.vue"),
        ...walk(join(ui, "Input")),
    ];
}

// Prefixes assembled at runtime by string concat (e.g. `var(${p}-bg)`), never
// used whole. Their fully-formed vars are flat tokens and are injected.
const DYNAMIC_PREFIXES = new Set([
    "--color-badge-",
    "--color-menu-item",
    "--color-menu-item-hover",
    "--color-menu-item-selected",
    "--color-menu-item-disabled",
]);

function usedColorVars() {
    const vars = new Set();
    for (const f of filesToScan()) {
        const src = readFileSync(f, "utf8");
        for (const m of src.matchAll(/--color-[a-z0-9-]+/g)) {
            if (!DYNAMIC_PREFIXES.has(m[0])) vars.add(m[0]);
        }
    }
    return [...vars].sort();
}

describe("preview variable coverage", () => {
    it("injects every --color-* var the frame and compositions use", () => {
        const w = mount(ThemeBuilder, { props: themeBuilderProps });
        const style = w.get("[data-theme-preview]").attributes("style");
        const missing = usedColorVars().filter(
            (v) => !style.includes(`${v}:`),
        );
        expect(missing, `missing from preview :style — ${missing}`).toEqual([]);
    });
});
