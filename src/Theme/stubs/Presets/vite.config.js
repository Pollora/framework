import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "%theme_path%%app_css_input%",
                "%theme_path%js/app.js"
            ],
            buildDirectory: "%theme_name%",
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
    %css_config%
});
