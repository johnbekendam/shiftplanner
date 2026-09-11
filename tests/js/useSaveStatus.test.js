import { describe, it, expect, vi, beforeEach, afterEach } from "vitest";
import { useSaveStatus } from "@/composables/useSaveStatus";

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

describe("useSaveStatus", () => {
    it("starts idle", () => {
        expect(useSaveStatus().status.value).toBe("idle");
    });

    it("goes to saving on start, then saved on succeed, then idle after a delay", () => {
        const s = useSaveStatus();
        s.start();
        expect(s.status.value).toBe("saving");

        s.succeed();
        expect(s.status.value).toBe("saved");

        vi.advanceTimersByTime(1500);
        expect(s.status.value).toBe("idle");
    });

    it("goes to error on fail, then idle after a longer delay", () => {
        const s = useSaveStatus();
        s.start();
        s.fail();
        expect(s.status.value).toBe("error");

        vi.advanceTimersByTime(1500);
        expect(s.status.value).toBe("error");

        vi.advanceTimersByTime(1000);
        expect(s.status.value).toBe("idle");
    });

    it("stays saving while a second write is still in flight, only settling once both finish", () => {
        const s = useSaveStatus();
        s.start();
        s.start();
        s.succeed();
        expect(s.status.value).toBe("saving");

        s.succeed();
        expect(s.status.value).toBe("saved");
    });

    it("a fresh start cancels a pending idle timeout from a previous save", () => {
        const s = useSaveStatus();
        s.start();
        s.succeed();
        vi.advanceTimersByTime(1000);

        s.start();
        vi.advanceTimersByTime(1000);
        expect(s.status.value).toBe("saving");
    });
});
