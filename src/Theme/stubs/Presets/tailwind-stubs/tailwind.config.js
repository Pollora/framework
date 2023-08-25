const defaultTheme = require("tailwindcss/defaultTheme");

module.exports = {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./%theme_path%/**/*.{blade.php,js,vue,ts}",
    ],

    theme: {
        extend: {
        },
    },

    variants: {
        extend: {
        },
    },

    plugins: [
        require('@tailwindcss/typography'),
        require("@tailwindcss/forms")
    ],
};
