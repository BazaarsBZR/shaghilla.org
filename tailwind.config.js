import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Tajawal', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                canvas: 'rgb(var(--sh-bg) / <alpha-value>)',
                surface: 'rgb(var(--sh-surface) / <alpha-value>)',
                'surface-soft': 'rgb(var(--sh-surface-soft) / <alpha-value>)',
                ink: 'rgb(var(--sh-ink) / <alpha-value>)',
                'ink-muted': 'rgb(var(--sh-ink-muted) / <alpha-value>)',
                'ink-faint': 'rgb(var(--sh-ink-faint) / <alpha-value>)',
                line: 'rgb(var(--sh-line) / <alpha-value>)',
                accent: 'rgb(var(--sh-accent) / <alpha-value>)',
                'accent-strong': 'rgb(var(--sh-accent-strong) / <alpha-value>)',
                'accent-soft': 'rgb(var(--sh-accent-soft) / <alpha-value>)',
                success: 'rgb(var(--sh-success) / <alpha-value>)',
                warning: 'rgb(var(--sh-warning) / <alpha-value>)',
                danger: 'rgb(var(--sh-danger) / <alpha-value>)',
                night: 'rgb(var(--sh-night) / <alpha-value>)',
                'night-surface': 'rgb(var(--sh-night-surface) / <alpha-value>)',
                'night-line': 'rgb(var(--sh-night-line) / <alpha-value>)',
            },
            borderRadius: {
                control: '0.625rem',
                card: '0.75rem',
                pill: '9999px',
            },
            boxShadow: {
                surface: '0 1px 2px 0 rgb(15 23 42 / 0.06), 0 1px 1px -1px rgb(15 23 42 / 0.06)',
                overlay: '0 10px 24px -12px rgb(15 23 42 / 0.35)',
            },
            spacing: {
                'grid-1': '0.25rem',
                'grid-2': '0.5rem',
                'grid-3': '0.75rem',
                'grid-4': '1rem',
                'grid-5': '1.25rem',
                'grid-6': '1.5rem',
                'grid-8': '2rem',
                'grid-10': '2.5rem',
                'grid-12': '3rem',
            },
        },
    },
    plugins: [],
};
