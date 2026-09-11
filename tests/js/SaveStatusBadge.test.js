import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = {
    "app.saving": "Saving…",
    "app.saved": "Saved",
    "app.save_failed": "Could not save",
};

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import SaveStatusBadge from "@/components/ui/SaveStatusBadge.vue";

describe("SaveStatusBadge", () => {
    it("renders nothing when idle", () => {
        const w = mount(SaveStatusBadge, { props: { status: "idle" } });
        expect(w.find('[data-testid="save-status-badge"]').exists()).toBe(false);
    });

    it("shows Saving… while saving", () => {
        const w = mount(SaveStatusBadge, { props: { status: "saving" } });
        expect(w.get('[data-testid="save-status-badge"]').text()).toBe("Saving…");
    });

    it("shows Saved on success", () => {
        const w = mount(SaveStatusBadge, { props: { status: "saved" } });
        expect(w.get('[data-testid="save-status-badge"]').text()).toBe("Saved");
    });

    it("shows Could not save on error", () => {
        const w = mount(SaveStatusBadge, { props: { status: "error" } });
        expect(w.get('[data-testid="save-status-badge"]').text()).toBe("Could not save");
    });
});
