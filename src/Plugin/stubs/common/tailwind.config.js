/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/assets/**/*.{js,css}",
        "./app/**/*.php",
        "./resources/views/**/*.blade.php"
    ],
    theme: {
        extend: {
            // Plugin-specific theme extensions
        },
    },
    plugins: [
        // Add Tailwind plugins here if needed
        // require('@tailwindcss/forms'),
        // require('@tailwindcss/typography'),
    ],
}
