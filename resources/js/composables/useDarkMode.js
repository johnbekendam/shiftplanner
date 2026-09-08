import { ref } from 'vue'

const isDark = ref(document.documentElement.classList.contains('dark'))

export function useDarkMode() {
    function toggle() {
        window.toggleDarkMode()
        isDark.value = document.documentElement.classList.contains('dark')
    }
    return { isDark, toggle }
}
