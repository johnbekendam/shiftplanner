import { usePage } from '@inertiajs/vue3'

export function useI18n() {
    const page = usePage()

    return function __(key, replacements = {}) {
        let value = page.props.translations?.[key] ?? key
        for (const [placeholder, replacement] of Object.entries(replacements)) {
            value = value.replace(`:${placeholder}`, replacement)
        }
        return value
    }
}
