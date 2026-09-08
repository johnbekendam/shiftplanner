import { ref } from 'vue'

const segments = ref([])

export function useBreadcrumb() {
    function setBreadcrumb(...parts) {
        segments.value = parts.filter(Boolean)
    }
    return { segments, setBreadcrumb }
}
