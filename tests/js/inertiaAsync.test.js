import { describe, it, expect, vi } from "vitest";
import { putAsync, postAsync, deleteAsync } from "@/utils/inertiaAsync";

const calls = [];

vi.mock("@inertiajs/vue3", () => ({
    router: {
        put: (url, data, opts) => {
            calls.push(["put", url, data]);
            opts.onSuccess();
        },
        post: (url, data, opts) => {
            calls.push(["post", url, data]);
            opts.onSuccess();
        },
        delete: (url, opts) => {
            calls.push(["delete", url]);
            opts.onError("boom");
        },
    },
}));

describe("inertiaAsync", () => {
    it("putAsync resolves on success and passes the payload through", async () => {
        await expect(putAsync("/x/1", { level: "available" })).resolves.toBeUndefined();
        expect(calls).toContainEqual(["put", "/x/1", { level: "available" }]);
    });

    it("postAsync resolves on success", async () => {
        await expect(postAsync("/x", { name: "a" })).resolves.toBeUndefined();
        expect(calls).toContainEqual(["post", "/x", { name: "a" }]);
    });

    it("deleteAsync rejects on error", async () => {
        await expect(deleteAsync("/x/1")).rejects.toBe("boom");
        expect(calls).toContainEqual(["delete", "/x/1"]);
    });
});
