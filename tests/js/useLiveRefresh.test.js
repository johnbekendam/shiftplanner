import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { mount } from "@vue/test-utils";
import { defineComponent } from "vue";

const { router, handlers, offs } = vi.hoisted(() => {
    const handlers = {};
    const offs = [];
    const router = {
        reload: vi.fn(),
        on: vi.fn((name, cb) => {
            handlers[name] = cb;
            const off = vi.fn();
            offs.push(off);
            return off;
        }),
    };
    return { router, handlers, offs };
});

vi.mock("@inertiajs/vue3", () => ({ router }));

import { useLiveRefresh, LIVE_REFRESH_MS } from "@/composables/useLiveRefresh";

const Probe = defineComponent({
    setup() {
        useLiveRefresh({ only: ["weeks", "today"] });
        return () => null;
    },
});

beforeEach(() => {
    vi.useFakeTimers();
    router.reload.mockReset();
    router.on.mockClear();
    offs.length = 0;
});

afterEach(() => vi.useRealTimers());

describe("useLiveRefresh", () => {
    it("refreshes every 60 seconds", () => {
        expect(LIVE_REFRESH_MS).toBe(60_000);
        mount(Probe);

        vi.advanceTimersByTime(59_999);
        expect(router.reload).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        expect(router.reload).toHaveBeenCalledTimes(1);
    });

    it("reloads only the planning props and keeps the page state", () => {
        mount(Probe);
        vi.advanceTimersByTime(LIVE_REFRESH_MS);

        expect(router.reload).toHaveBeenCalledWith(expect.objectContaining({
            only: ["weeks", "today"],
            preserveScroll: true,
            preserveState: true,
        }));
    });

    it("keeps refreshing after each reload finishes", () => {
        mount(Probe);

        vi.advanceTimersByTime(LIVE_REFRESH_MS);
        router.reload.mock.calls[0][0].onFinish();
        vi.advanceTimersByTime(LIVE_REFRESH_MS);

        expect(router.reload).toHaveBeenCalledTimes(2);
    });

    it("skips a tick while the last reload is still running", () => {
        mount(Probe);

        vi.advanceTimersByTime(LIVE_REFRESH_MS);
        vi.advanceTimersByTime(LIVE_REFRESH_MS);

        expect(router.reload).toHaveBeenCalledTimes(1);
    });

    it("cancels the error page when the server answers with an error", () => {
        mount(Probe);

        expect(handlers.httpException()).toBe(false);
    });

    it("cancels the error when the network is down", () => {
        mount(Probe);

        expect(handlers.networkError()).toBe(false);
    });

    it("stops the timer and removes its handlers when the page unmounts", () => {
        const w = mount(Probe);
        w.unmount();

        vi.advanceTimersByTime(LIVE_REFRESH_MS * 3);

        expect(router.reload).not.toHaveBeenCalled();
        expect(offs).toHaveLength(2);
        offs.forEach((off) => expect(off).toHaveBeenCalledTimes(1));
    });
});
