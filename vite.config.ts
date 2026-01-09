import { fileURLToPath, URL } from "node:url";

import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";

export default defineConfig({
    build: {
        commonjsOptions: {
            include: ["tailwind.config.js", "node_modules/**"],
        },
    },
    optimizeDeps: {
        include: ["tailwind-config"],
    },
    plugins: [
        vue(),
        laravel({
            input: ["resources/@client/main.ts"],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            "@": fileURLToPath(new URL("./resources/@client", import.meta.url)),
            "tailwind-config": fileURLToPath(
                new URL("./tailwind.config.js", import.meta.url)
            ),
        },
    },
});
