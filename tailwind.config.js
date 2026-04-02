import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                crea: {
                    primary: '#1e3a5f',
                    'primary-dark': '#152a45',
                    'primary-light': '#2d4a6f',
                    secondary: '#0891b2',
                    'secondary-dark': '#0e7490',
                    'secondary-light': '#22d3ee',
                    accent: '#7c3aed',
                    'accent-dark': '#6d28d9',
                    'accent-light': '#a78bfa',
                    link: '#2ba1bd',
                    'link-hover': '#344d6e',
                },
            },
        },
    },

    plugins: [forms, typography],
};
