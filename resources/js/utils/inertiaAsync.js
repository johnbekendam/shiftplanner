import { router } from '@inertiajs/vue3'

// `async: true` routes these through Inertia's async request stream, which
// runs requests to different URLs concurrently instead of interrupting the
// previous in-flight (sync) visit — required since a page-level Save can
// fire several of these at once (e.g. one PUT per changed availability cell).
const BASE_OPTIONS = { preserveScroll: true, preserveState: true, async: true }

function visit(method, url, data) {
    return new Promise((resolve, reject) => {
        const options = { ...BASE_OPTIONS, onSuccess: resolve, onError: reject }
        if (method === 'delete') {
            router.delete(url, options)
        } else {
            router[method](url, data, options)
        }
    })
}

/** Promise-wrapped Inertia requests, for grouping several writes behind one await. */
export const putAsync = (url, data) => visit('put', url, data)
export const postAsync = (url, data) => visit('post', url, data)
export const deleteAsync = (url) => visit('delete', url)
