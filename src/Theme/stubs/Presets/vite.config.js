import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import path from "path";

let publicPath = path.resolve(__dirname) + '/../../public';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "css/app.css",
                "js/app.js"
            ],
            hotFile: publicPath + '/hot',
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
    ]
});
