import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // A tap leaves hover styles stuck on touch screens (Discord mobile).
    future: {
        hoverOnlyWhenSupported: true,
    },

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js}',
    ],

    theme: {
        extend: {
            fontFamily: {
                oswald: ['"Oswald Variable"', 'Oswald', 'sans-serif'],
                inter: ['"Inter Variable"', 'Inter', 'sans-serif'],
            },
            screens: {
                // Landscape phones and the Discord Activity picture-in-picture
                // view: keep the timer and its controls on screen.
                short: { raw: '(max-height: 500px)' },
            },
        },
    },

    plugins: [forms],
};
