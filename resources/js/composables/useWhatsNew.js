import { computed, ref } from 'vue'
import axios from 'axios'

/**
 * The What's new dialog state (features/whats-new/). It opens on its own
 * with the unseen entries; open() shows every entry again. A close after
 * unseen entries posts to `seenUrl`.
 *
 * @param {{ entries: Array, seenAt: string|null }|null} whatsNew
 */
export function useWhatsNew(whatsNew, seenUrl) {
    const entries = whatsNew?.entries ?? []
    const seenAt = ref(whatsNew?.seenAt ?? null)
    const unseen = computed(() => entries.filter((entry) => !seenAt.value || entry.date > seenAt.value))

    // 'unseen' on its own after an update; 'all' from the link.
    const mode = ref(unseen.value.length ? 'unseen' : null)

    return {
        hasEntries: entries.length > 0,
        isOpen: computed(() => mode.value !== null),
        dialogEntries: computed(() => (mode.value === 'unseen' ? unseen.value : entries)),
        open() {
            mode.value = 'all'
        },
        close() {
            if (unseen.value.length) {
                seenAt.value = entries[0].date
                axios.post(seenUrl).catch(() => {})
            }
            mode.value = null
        },
    }
}
