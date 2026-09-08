import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    resolve: {
        alias: { '@': '/resources/js' },
    },
    plugins: [
        tailwindcss(),
        vue(),
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
    ],
    build: {
        outDir: "public/build",
        emptyOutDir: true,
        manifest: "manifest.json",
        rollupOptions: {
            output: {
                manualChunks: (id) => {
                    if (id.includes("/node_modules/vue") || id.includes("/node_modules/@vue/")) return "vue";
                    if (id.includes("/node_modules/@inertiajs/")) return "inertia";
                    if (id.includes("/node_modules/axios")) return "vendor";
                },
            },
        },
    },
});
