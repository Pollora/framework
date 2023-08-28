import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { readFileSync } from 'node:fs';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "themes/%theme_name%/css/app.css",
                "themes/%theme_name%/js/app.js"
            ],
            buildDirectory: "build/%theme_name%",
        }),
        {
            name: "blade",
            handleHotUpdate({ file, server }) {
                if (file.endsWith(".blade.php")) {
                    server.ws.send({
                        type: "full-reload",
                        path: "*",
                    });
                }
            },
        },
    ],
    build: {
        emptyOutDir: false,
    },
    server: {
        cors: true,
        strictPort: true,
        port: 5173,
        host: '0.0.0.0',
        open: false,
        hmr: {
            port: 5173,
            clientPort: 5173,
        },
        https: {
            key: readFileSync('/etc/certs/local-key.pem'),
            cert: readFileSync('/etc/certs/local-cert.pem'),
        }
    },
});
