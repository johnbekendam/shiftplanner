import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";
import { defineComponent, ref } from "vue";
import { useUnsavedChangesGuard } from "@/composables/useUnsavedChangesGuard";

let beforeCallback = null;
const unsubscribe = vi.fn();

vi.mock("@inertiajs/vue3", () => ({
    router: {
        on: vi.fn((event, cb) => {
            if (event === "before") beforeCallback = cb;
            return unsubscribe;
        }),
    },
}));

let wrappers = [];

function mountGuard(dirty) {
    const Host = defineComponent({
        setup() {
            useUnsavedChangesGuard(() => dirty.value);
            return () => null;
        },
    });

    const w = mount(Host);
    wrappers.push(w);
    return w;
}

beforeEach(() => {
    beforeCallback = null;
    unsubscribe.mockClear();
});

afterEach(() => {
    wrappers.forEach((w) => w.unmount());
    wrappers = [];
    vi.restoreAllMocks();
});

describe("useUnsavedChangesGuard", () => {
    it("prevents the default beforeunload action while dirty", () => {
        const dirty = ref(true);
        mountGuard(dirty);

        const event = new Event("beforeunload", { cancelable: true });
        window.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    it("does not touch beforeunload when not dirty", () => {
        const dirty = ref(false);
        mountGuard(dirty);

        const event = new Event("beforeunload", { cancelable: true });
        window.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
    });

    it("cancels an Inertia visit when dirty and the user declines to leave", () => {
        const dirty = ref(true);
        mountGuard(dirty);
        vi.spyOn(window, "confirm").mockReturnValue(false);

        expect(beforeCallback()).toBe(false);
    });

    it("allows an Inertia visit when dirty and the user confirms leaving", () => {
        const dirty = ref(true);
        mountGuard(dirty);
        vi.spyOn(window, "confirm").mockReturnValue(true);

        expect(beforeCallback()).toBe(true);
    });

    it("allows an Inertia visit without prompting when not dirty", () => {
        const dirty = ref(false);
        mountGuard(dirty);
        const confirmSpy = vi.spyOn(window, "confirm");

        expect(beforeCallback()).toBe(true);
        expect(confirmSpy).not.toHaveBeenCalled();
    });

    it("unsubscribes both listeners on unmount", () => {
        const dirty = ref(true);
        const w = mountGuard(dirty);
        const removeSpy = vi.spyOn(window, "removeEventListener");

        w.unmount();

        expect(removeSpy).toHaveBeenCalledWith("beforeunload", expect.any(Function));
        expect(unsubscribe).toHaveBeenCalled();
    });
});
