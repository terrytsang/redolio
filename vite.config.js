import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', { weights: [400, 500, 600] }),
                bunny('Inter', { weights: [400, 500, 600, 700] }),
                bunny('Manrope', { weights: [500, 600, 700, 800] }),
                bunny('Fraunces', { weights: [400, 500, 600, 700] }),
                bunny('Source Sans 3', { weights: [400, 500, 600] }),
                bunny('Space Grotesk', { weights: [500, 600, 700] }),
                bunny('IBM Plex Sans', { weights: [400, 500, 600, 700] }),
                bunny('Instrument Serif', { weights: [400] }),
                bunny('Archivo', { weights: [400, 500, 600, 700, 800] }),
                bunny('Cormorant Garamond', { weights: [400, 500, 600, 700] }),
                bunny('Jost', { weights: [400, 500, 600] }),
                bunny('Rubik', { weights: [400, 500, 600, 700, 800] }),
                bunny('Syne', { weights: [500, 600, 700, 800] }),
                bunny('DM Sans', { weights: [400, 500, 600, 700] }),
                bunny('Playfair Display', { weights: [400, 500, 600, 700] }),
                bunny('Work Sans', { weights: [400, 500, 600] }),
                bunny('Plus Jakarta Sans', { weights: [400, 500, 600, 700, 800] }),
                bunny('Lora', { weights: [400, 500, 600, 700] }),
                bunny('Nunito Sans', { weights: [400, 600, 700] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
