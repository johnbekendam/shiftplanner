import { ref } from 'vue'

/**
 * Shared save-status tracker for a card body with several autosave
 * controls (checkboxes, grid cells, inline-edited rows) and no Save
 * button of its own. One instance per page, passed to every autosave
 * component as `saveStatus`, and rendered once via SaveStatusBadge in
 * the corner of whichever panel hosts them — one signal for the whole
 * body instead of feedback scattered per row.
 */
export function useSaveStatus() {
    const status = ref('idle') // 'idle' | 'saving' | 'saved' | 'error'
    let inFlight = 0
    let clearTimer = null

    function start() {
        inFlight++
        clearTimeout(clearTimer)
        status.value = 'saving'
    }

    function settle(outcome) {
        inFlight = Math.max(0, inFlight - 1)
        // Other writes are still in flight — let those settle the status.
        if (inFlight > 0) return

        status.value = outcome
        clearTimeout(clearTimer)
        clearTimer = setTimeout(() => {
            if (status.value === outcome) status.value = 'idle'
        }, outcome === 'error' ? 2500 : 1500)
    }

    return {
        status,
        start,
        succeed: () => settle('saved'),
        fail: () => settle('error'),
    }
}
