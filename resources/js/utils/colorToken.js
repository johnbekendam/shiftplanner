export const colorFamilyGroups = [
    ['slate', 'gray', 'zinc', 'neutral', 'stone'],
    ['red', 'orange', 'amber', 'yellow'],
    ['lime', 'green', 'emerald', 'teal'],
    ['cyan', 'sky', 'blue', 'indigo'],
    ['violet', 'purple', 'fuchsia', 'pink', 'rose'],
]
export const colorShades = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950']
export const SPECIALS = ['white', 'black', 'transparent']

export function parseColor(val) {
    if (!val) return { family: 'white', shade: '500' }
    const base = val.split('/')[0]
    if (SPECIALS.includes(base)) return { family: base, shade: '500' }
    const i = base.lastIndexOf('-')
    return { family: base.substring(0, i), shade: base.substring(i + 1) }
}

/** A stored token value (`indigo-600`, `white`, `black/40`) to a CSS value. */
export function cssVal(v) {
    if (!v || v === 'transparent') return 'transparent'
    const [base, opacity] = v.split('/')
    const color = SPECIALS.includes(base) ? base : `var(--color-${base})`
    if (opacity !== undefined && parseInt(opacity) < 100) {
        return `color-mix(in srgb, ${color} ${opacity}%, transparent)`
    }
    return color
}
