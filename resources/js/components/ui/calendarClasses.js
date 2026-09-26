// Class maps shared by Calendar.vue and CalendarLegend.vue (full strings so
// Tailwind includes them).

// Each family aliases the existing badge tokens (ThemeTokens::COLOR_DEFAULTS).
// There is no badge hover-state token in this app's theme system, so hover
// feedback in the calendar comes only from the border.
export const COLOR_CLASS = {
    success: 'bg-(--color-badge-success-bg) text-(--color-badge-success-text)',
    custom: 'bg-(--color-badge-custom-bg) text-(--color-badge-custom-text)',
    error: 'bg-(--color-badge-error-bg) text-(--color-badge-error-text)',
    warning: 'bg-(--color-badge-warning-bg) text-(--color-badge-warning-text)',
    standard: 'bg-(--color-badge-standard-bg) text-(--color-badge-standard-text)',
    muted: 'bg-(--color-badge-muted-bg) text-(--color-badge-muted-text)',
}

// A marked day's border (dayBorders), in the text token of its own color family,
// so it stands out as clearly as the day number.
export const BORDER_COLOR_CLASS = {
    success: 'border-(--color-badge-success-text)',
    custom: 'border-(--color-badge-custom-text)',
    error: 'border-(--color-badge-error-text)',
    warning: 'border-(--color-badge-warning-text)',
    standard: 'border-(--color-badge-standard-text)',
    muted: 'border-(--color-badge-muted-text)',
}

// The one day border style dayBorders supports.
export const BORDER_STYLES = ['solid']
