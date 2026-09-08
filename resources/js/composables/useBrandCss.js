const STYLE_ID = 'brand-css'

/**
 * Applies (or clears) the active context entity's brand color override CSS by writing it
 * into a dedicated <style> element in <head>. This mirrors how app.blade.php injects the
 * base theme stylesheet server-side, just made reactive to whatever CSS string is passed in.
 * Call synchronously (not inside onMounted) so there's no flash of unstyled chrome.
 */
export function useBrandCss(css) {
    let el = document.getElementById(STYLE_ID)
    if (!el) {
        el = document.createElement('style')
        el.id = STYLE_ID
        document.head.appendChild(el)
    }
    el.textContent = css ?? ''
}
