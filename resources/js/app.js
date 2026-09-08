import "./bootstrap";
import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import axios from "axios";

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
if (csrfToken) {
    axios.defaults.headers.common["X-CSRF-TOKEN"] = csrfToken;
}

const appName = document.title;

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob("./pages/**/*.vue");
        return pages[`./pages/${name}.vue`]().catch(() => {
            window.location.reload();
        });
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    title: (title) => (title ? `${title} - ${appName}` : appName),
});
