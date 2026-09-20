// Builds an iCalendar (.ics) event for one planned shift. Times are
// floating (no time zone), so a calendar shows the shift at the same
// clock time wherever it is opened.

const pad = (n) => String(n).padStart(2, '0')

// 'HH:MM' or 'HH:MM:SS' -> 'HHMMSS'
function compactTime(time) {
    const [h, m, s = '0'] = time.split(':')
    return `${pad(h)}${pad(m)}${pad(s)}`
}

// Escapes text values per RFC 5545 section 3.3.11.
function escapeText(text) {
    return String(text)
        .replace(/\\/g, '\\\\')
        .replace(/;/g, '\;')
        .replace(/,/g, '\\,')
        .replace(/\r?\n/g, '\\n')
}

// Folds a content line at 75 characters; continuation lines start with a space.
function fold(line) {
    const parts = [line.slice(0, 75)]
    for (let i = 75; i < line.length; i += 74) {
        parts.push(` ${line.slice(i, i + 74)}`)
    }
    return parts.join('\r\n')
}

function utcStamp(date) {
    return `${date.getUTCFullYear()}${pad(date.getUTCMonth() + 1)}${pad(date.getUTCDate())}`
        + `T${pad(date.getUTCHours())}${pad(date.getUTCMinutes())}${pad(date.getUTCSeconds())}Z`
}

const slug = (text) => String(text).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')

/**
 * @param {{ date: string, shift_name: string, start_time: string, end_time: string, workcenter_name: string, responsible?: string|null }} assignment
 * @param {{ now?: Date, contactLabel?: string }} [options]
 */
export function buildShiftIcs(assignment, { now = new Date(), contactLabel = 'Contact' } = {}) {
    const day = assignment.date.replaceAll('-', '')
    const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//ShiftPlanner//Planning//EN',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        `UID:${assignment.date}-${slug(assignment.shift_name)}-${slug(assignment.workcenter_name)}@shiftplanner`,
        `DTSTAMP:${utcStamp(now)}`,
        `DTSTART:${day}T${compactTime(assignment.start_time)}`,
        `DTEND:${day}T${compactTime(assignment.end_time)}`,
        `SUMMARY:${escapeText(`${assignment.shift_name} – ${assignment.workcenter_name}`)}`,
        `LOCATION:${escapeText(assignment.workcenter_name)}`,
    ]

    if (assignment.responsible) {
        lines.push(`DESCRIPTION:${escapeText(`${contactLabel}: ${assignment.responsible}`)}`)
    }

    lines.push('END:VEVENT', 'END:VCALENDAR')

    return `${lines.map(fold).join('\r\n')}\r\n`
}

/** 'shift-dd-mm-yyyy.ics' */
export function shiftIcsFilename(assignment) {
    const [y, m, d] = assignment.date.split('-')
    return `shift-${d}-${m}-${y}.ics`
}

/** Saves the text as a downloaded .ics file. */
export function downloadIcs(filename, content) {
    const url = URL.createObjectURL(new Blob([content], { type: 'text/calendar;charset=utf-8' }))
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
}
