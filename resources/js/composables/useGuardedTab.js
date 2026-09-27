import { ref } from 'vue'

/**
 * Keeps unsaved changes on the open tab: a switch away from a tab with
 * unsaved changes waits for the user to stay or discard them.
 *
 * tab: the ref of the open tab.
 * isDirty: () => whether the open tab has unsaved changes.
 * discard: () => resets the unsaved changes.
 */
export function useGuardedTab(tab, isDirty, discard) {
    // The tab the user asked for while the open tab had unsaved changes.
    const blockedTab = ref(null)

    function requestTab(next) {
        if (next === tab.value) return
        if (isDirty()) {
            blockedTab.value = next
            return
        }
        tab.value = next
    }

    function stay() {
        blockedTab.value = null
    }

    function discardAndSwitch() {
        discard()
        tab.value = blockedTab.value
        blockedTab.value = null
    }

    return { blockedTab, requestTab, stay, discardAndSwitch }
}
