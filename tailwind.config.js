/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './*.php',
        './includes/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
            },
            colors: {
                brand: {
                    DEFAULT: '#00BCFF',
                    dark: '#228BBA',
                    deep: '#2963A2',
                    light: '#A7F5E9',
                    soft: '#D7EFF8',
                },
            },
        },
    },
    plugins: [],
};
