import { onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

export const LIVE_REFRESH_MS = 60_000

/**
 * Reloads the given page props on a timer, for a screen nobody operates.
 * A failed reload leaves the last data on screen: Inertia would show an
 * error page for an error response and throw for a dead network, so both
 * are cancelled while the page is mounted. The next tick tries again.
 */
export function useLiveRefresh({ only, intervalMs = LIVE_REFRESH_MS }) {
    let timer = null
    let loading = false
    const removers = []

    function refresh() {
        // A slow reload must not stack up behind the timer.
        if (loading) return

        loading = true
        router.reload({
            only,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => { loading = false },
        })
    }

    onMounted(() => {
        timer = setInterval(refresh, intervalMs)
        removers.push(router.on('httpException', () => false))
        removers.push(router.on('networkError', () => false))
    })

    onBeforeUnmount(() => {
        clearInterval(timer)
        removers.forEach((remove) => remove())
    })
}
