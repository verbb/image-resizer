import { resolve } from 'node:path';

import { defineConfig } from 'vite';

const root = resolve(import.meta.dirname, 'src/web/assets/cp');

// `css` and `js` build the main control panel bundle. `kit` builds the Plugin Kit
// components that only the empty states load, so other pages don't pay for them.
const entries = {
    css: 'image-resizer.css',
    js: 'image-resizer.js',
    kit: 'plugin-kit.js',
};

export default defineConfig(({ mode }) => {
    if (!entries[mode]) {
        throw new Error(`Unknown asset build mode: ${mode}`);
    }

    const isCssBuild = mode === 'css';

    const output = isCssBuild ? {
        assetFileNames: '[name][extname]',
    } : {
        assetFileNames: '[name][extname]',
        codeSplitting: false,
        entryFileNames: '[name].js',
        format: 'iife',
    };

    return {
        root,
        input: resolve(root, 'src', entries[mode]),
        build: {
            outDir: resolve(root, 'dist'),
            emptyOutDir: isCssBuild,
            assetsDir: '',
            cssMinify: 'esbuild',
            cssTarget: ['chrome61', 'safari10'],
            minify: 'oxc',
            sourcemap: !isCssBuild,
            target: 'es2015',
            rolldownOptions: {
                output,
            },
        },
    };
});
