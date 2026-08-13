/** Tailwind CSS — back-office admin PROCOPE */
/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './views/**/*.php',
        './app/helpers.php',
        './public/assets/admin.js',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    navy: '#062a4d',
                    navy2: '#0a3a68',
                    orange: '#f5a623',
                    blue: '#06A3DA',
                },
            },
            fontFamily: {
                sans: [
                    'Segoe UI', 'system-ui', '-apple-system', 'Roboto',
                    'Helvetica Neue', 'Arial', 'sans-serif',
                ],
            },
            boxShadow: {
                soft: '0 1px 2px 0 rgb(6 42 77 / 0.05), 0 4px 16px -4px rgb(6 42 77 / 0.10)',
            },
        },
    },
    plugins: [],
};
