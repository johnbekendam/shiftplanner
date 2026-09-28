// Date display helpers. Dates travel between server and client as ISO
// `YYYY-MM-DD` strings; the app shows them as `DD-MM-YYYY` everywhere.

/**
 * Format an ISO `YYYY-MM-DD` string as `DD-MM-YYYY`.
 * Returns the input unchanged when it is empty or not an ISO date.
 */
export function formatDate(iso) {
    if (typeof iso !== 'string') return ''
    const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (!m) return iso
    return `${m[3]}-${m[2]}-${m[1]}`
}

/** Today in the browser's local time, as an ISO `YYYY-MM-DD` string. */
export function todayIso() {
    const now = new Date()
    const pad = (n) => String(n).padStart(2, '0')
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}
