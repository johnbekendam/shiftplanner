import { reactive, computed, ref } from 'vue'

/**
 * Aggregates several independently-dirty, independently-savable resources
 * (one per tab, typically) behind one page-level Save action. Each
 * resource's own `save()` decides how to persist itself and returns
 * `false` (or throws/rejects) on failure — everything else counts as
 * success. A failed resource keeps its own dirty state; saveAll() only
 * ever tells the resource to try again, it never clears dirty itself.
 */
export function useSaveRegistry() {
    const resources = reactive({})
    const saving = ref(false)

    function register(key, { isDirty, save }) {
        resources[key] = { isDirty, save, hasError: false }
    }

    const anyDirty = computed(() => Object.values(resources).some((r) => r.isDirty()))

    function hasError(key) {
        return !!resources[key]?.hasError
    }

    async function saveAll() {
        const dirtyKeys = Object.keys(resources).filter((k) => resources[k].isDirty())
        if (dirtyKeys.length === 0) return true

        saving.value = true
        const results = await Promise.allSettled(
            dirtyKeys.map(async (k) => {
                const resource = resources[k]
                try {
                    const outcome = await resource.save()
                    resource.hasError = outcome === false
                } catch {
                    resource.hasError = true
                }
            }),
        )
        saving.value = false

        return results.every((r) => r.status === 'fulfilled') && dirtyKeys.every((k) => !resources[k].hasError)
    }

    return { register, anyDirty, saving, saveAll, hasError }
}
