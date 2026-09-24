import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { wordpressPlugin } from '@roots/vite-plugin';
import { globSync } from 'glob';
import path from 'path';

// Builds where the framework's asset container for a Laravel module reads:
// public/build/module/{kebab-name}, hot file public/{kebab-name}.hot.
const publicDirectory = '../../public';
const moduleSlug = path.basename(import.meta.dirname).replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();

const blockEntries = globSync([
    './resources/views/blocks/*/{index,view}.{js,jsx,ts,tsx}',
    './resources/views/blocks/*/{editor,style}.css',
]);

export default defineConfig({
    base: `/build/module/${moduleSlug}`,
    build: { emptyOutDir: false },
    plugins: [
        laravel({
            input: blockEntries,
            publicDirectory,
            hotFile: path.join(publicDirectory, `${moduleSlug}.hot`),
            buildDirectory: path.join('build', 'module', moduleSlug),
        }),
        wordpressPlugin(),
    ],
});
