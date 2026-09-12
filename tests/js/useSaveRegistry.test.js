import { describe, it, expect } from "vitest";
import { ref } from "vue";
import { useSaveRegistry } from "@/composables/useSaveRegistry";

describe("useSaveRegistry", () => {
    it("anyDirty is false with nothing registered dirty", () => {
        const { register, anyDirty } = useSaveRegistry();
        register("a", { isDirty: () => false, save: async () => true });

        expect(anyDirty.value).toBe(false);
    });

    it("anyDirty is true when any registered resource is dirty", () => {
        const { register, anyDirty } = useSaveRegistry();
        const dirty = ref(true);
        register("a", { isDirty: () => dirty.value, save: async () => true });

        expect(anyDirty.value).toBe(true);
        dirty.value = false;
        expect(anyDirty.value).toBe(false);
    });

    it("saveAll only calls save() on dirty resources", async () => {
        const { register, saveAll } = useSaveRegistry();
        const calls = [];
        register("clean", { isDirty: () => false, save: async () => calls.push("clean") });
        register("dirty", { isDirty: () => true, save: async () => calls.push("dirty") });

        await saveAll();

        expect(calls).toEqual(["dirty"]);
    });

    it("returns true and clears hasError when every dirty resource succeeds", async () => {
        const { register, saveAll, hasError } = useSaveRegistry();
        register("a", { isDirty: () => true, save: async () => true });

        const ok = await saveAll();

        expect(ok).toBe(true);
        expect(hasError("a")).toBe(false);
    });

    it("returns false and marks hasError for a resource whose save() returns false", async () => {
        const { register, saveAll, hasError } = useSaveRegistry();
        register("ok", { isDirty: () => true, save: async () => true });
        register("bad", { isDirty: () => true, save: async () => false });

        const ok = await saveAll();

        expect(ok).toBe(false);
        expect(hasError("ok")).toBe(false);
        expect(hasError("bad")).toBe(true);
    });

    it("marks hasError for a resource whose save() rejects", async () => {
        const { register, saveAll, hasError } = useSaveRegistry();
        register("bad", { isDirty: () => true, save: async () => { throw new Error("boom"); } });

        const ok = await saveAll();

        expect(ok).toBe(false);
        expect(hasError("bad")).toBe(true);
    });

    it("toggles saving to true during saveAll and back to false after", async () => {
        const { register, saveAll, saving } = useSaveRegistry();
        let resolveSave;
        register("a", {
            isDirty: () => true,
            save: () => new Promise((resolve) => { resolveSave = resolve; }),
        });

        expect(saving.value).toBe(false);
        const promise = saveAll();
        expect(saving.value).toBe(true);
        resolveSave(true);
        await promise;
        expect(saving.value).toBe(false);
    });
});
