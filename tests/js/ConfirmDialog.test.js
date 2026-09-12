import { describe, it, expect, vi } from "vitest";
import { mount } from "@vue/test-utils";

const en = { "app.cancel": "Cancel" };

vi.mock("@inertiajs/vue3", () => ({
    usePage: () => ({ props: { translations: en } }),
}));

import ConfirmDialog from "@/components/ui/ConfirmDialog.vue";

const mountDialog = (props = {}, slots = {}) =>
    mount(ConfirmDialog, {
        props: { open: true, title: "Sign off?", confirmLabel: "Yes, sign me off", ...props },
        slots: { default: "This removes your details.", ...slots },
        global: { stubs: { teleport: true } },
    });

describe("ConfirmDialog", () => {
    it("renders nothing when closed", () => {
        const w = mountDialog({ open: false });
        expect(w.find('[role="dialog"]').exists()).toBe(false);
    });

    it("shows the title, body slot, and confirm/cancel actions when open", () => {
        const w = mountDialog();
        expect(w.text()).toContain("Sign off?");
        expect(w.text()).toContain("This removes your details.");
        expect(w.text()).toContain("Yes, sign me off");
        expect(w.text()).toContain("Cancel");
    });

    it("emits confirm when the confirm button is clicked", async () => {
        const w = mountDialog();
        await w.findAll("button").find((b) => b.text() === "Yes, sign me off").trigger("click");
        expect(w.emitted("confirm")).toHaveLength(1);
    });

    it("emits cancel when the cancel button, the backdrop, or Escape triggers", async () => {
        const w = mountDialog();
        await w.findAll("button").find((b) => b.text() === "Cancel").trigger("click");
        expect(w.emitted("cancel")).toHaveLength(1);

        await w.get('[role="dialog"]').trigger("keydown.escape");
        expect(w.emitted("cancel")).toHaveLength(2);
    });

    it("uses a custom cancelLabel when given", () => {
        const w = mountDialog({ cancelLabel: "Never mind" });
        expect(w.text()).toContain("Never mind");
        expect(w.text()).not.toContain("Cancel");
    });

    it("renders the confirm action as ButtonPrimary when variant is primary", () => {
        const w = mountDialog({ variant: "primary" });
        const confirmBtn = w.findAll("button").find((b) => b.text() === "Yes, sign me off");
        expect(confirmBtn.classes().join(" ")).not.toContain("--color-btn-danger-bg");
    });
});
