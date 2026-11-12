/**
 * The order modal and cart drawer use a handful of Tailwind utilities. This config mirrors the
 * inline config that used to sit in templates/base.html.twig next to the Play CDN script.
 *
 * The CDN compiled that CSS in the visitor's browser on every page load, which is explicitly
 * not supported for production. The stylesheet is now generated once and committed as
 * public/css/tailwind.css, so there is still no build step in the normal workflow.
 *
 * Regenerate it only after adding new utility classes to a template:
 *   npx tailwindcss@3.4.17 -c tailwind.config.js -o public/css/tailwind.css --minify
 */
module.exports = {
    content: ['./templates/**/*.twig', './public/js/**/*.js'],
    corePlugins: { preflight: false },
    // `container` and `table` are the storefront's and admin panel's OWN class names.
    // Without this, Tailwind would emit utilities of the same name and quietly override
    // the site's layout and tables.
    blocklist: ['container', 'table'],
    theme: {
        extend: {
            colors: {
                ink: '#1a1a1a',
                muted: '#6f655b',
                surface: '#faf6f0',
                'cream-dark': '#ede5d8',
                burgundy: { DEFAULT: '#5c0f12', dark: '#4a0c0f' },
                rose: { DEFAULT: '#c46d7a', dark: '#b85b69' },
                red: '#b42318',
            },
            fontFamily: {
                serif: ['Playfair Display', 'Georgia', 'serif'],
                sans: ['Inter', 'system-ui', 'sans-serif'],
            },
        },
    },
};
