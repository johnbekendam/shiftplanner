import { onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Warns before losing unsaved changes: a browser tab close/refresh, or an
 * Inertia link navigation away from the page. `isDirty` is read fresh at
 * the moment of leaving, not watched — one listener for the page's
 * lifetime is simpler than adding/removing per dirty-state change.
 */
export function useUnsavedChangesGuard(isDirty) {
    function handleBeforeUnload(event) {
        if (!isDirty()) return
        event.preventDefault()
        event.returnValue = ''
    }

    function handleInertiaBefore() {
        if (!isDirty()) return true

        return window.confirm('You have unsaved changes. Leave anyway?')
    }

    let unsubscribe = null

    onMounted(() => {
        window.addEventListener('beforeunload', handleBeforeUnload)
        unsubscribe = router.on('before', handleInertiaBefore)
    })

    onUnmounted(() => {
        window.removeEventListener('beforeunload', handleBeforeUnload)
        unsubscribe?.()
    })
}
