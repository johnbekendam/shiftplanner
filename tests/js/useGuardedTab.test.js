import { describe, it, expect, vi } from "vitest";
import { ref } from "vue";
import { useGuardedTab } from "@/composables/useGuardedTab";

describe("useGuardedTab", () => {
    it("switches at once without unsaved changes", () => {
        const tab = ref("details");
        const guard = useGuardedTab(tab, () => false, vi.fn());

        guard.requestTab("availability");

        expect(tab.value).toBe("availability");
        expect(guard.blockedTab.value).toBeNull();
    });

    it("holds the switch with unsaved changes until Stay or Discard", () => {
        const tab = ref("details");
        let dirty = true;
        const discard = vi.fn(() => { dirty = false; });
        const guard = useGuardedTab(tab, () => dirty, discard);

        guard.requestTab("availability");
        expect(tab.value).toBe("details");
        expect(guard.blockedTab.value).toBe("availability");

        guard.stay();
        expect(tab.value).toBe("details");
        expect(guard.blockedTab.value).toBeNull();
        expect(discard).not.toHaveBeenCalled();

        guard.requestTab("planning");
        guard.discardAndSwitch();
        expect(discard).toHaveBeenCalledOnce();
        expect(tab.value).toBe("planning");
        expect(guard.blockedTab.value).toBeNull();
    });

    it("ignores a click on the open tab", () => {
        const tab = ref("details");
        const guard = useGuardedTab(tab, () => true, vi.fn());

        guard.requestTab("details");

        expect(guard.blockedTab.value).toBeNull();
    });
});
