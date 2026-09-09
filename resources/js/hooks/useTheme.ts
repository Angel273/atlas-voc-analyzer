import { useState, useEffect, useCallback } from 'react';

export type Theme = 'light' | 'dark';

const THEME_STORAGE_KEY = 'atlas_theme';

function getSystemTheme(): Theme {
    if (typeof window === 'undefined') return 'light';
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function getInitialTheme(): Theme {
    if (typeof window === 'undefined') return 'light';
    const saved = localStorage.getItem(THEME_STORAGE_KEY) || localStorage.getItem('theme');
    if (saved === 'light' || saved === 'dark') {
        return saved;
    }
    return getSystemTheme();
}

export function useTheme() {
    const [theme, setThemeState] = useState<Theme>(getInitialTheme);

    const applyTheme = useCallback((newTheme: Theme) => {
        if (typeof window === 'undefined') return;
        const root = document.documentElement;
        if (newTheme === 'dark') {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
        localStorage.setItem(THEME_STORAGE_KEY, newTheme);
        window.dispatchEvent(new CustomEvent('atlas-theme-changed', { detail: { theme: newTheme } }));
    }, []);

    const setTheme = useCallback((newTheme: Theme) => {
        setThemeState(newTheme);
        applyTheme(newTheme);
    }, [applyTheme]);

    const toggleTheme = useCallback(() => {
        const nextTheme = theme === 'light' ? 'dark' : 'light';
        setTheme(nextTheme);
    }, [theme, setTheme]);

    useEffect(() => {
        // Sync on mount
        applyTheme(theme);

        // Listen for system theme changes if no manual preference is saved
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleSystemChange = (e: MediaQueryListEvent) => {
            const hasSaved = localStorage.getItem(THEME_STORAGE_KEY) || localStorage.getItem('theme');
            if (!hasSaved) {
                const nextTheme = e.matches ? 'dark' : 'light';
                setThemeState(nextTheme);
                applyTheme(nextTheme);
            }
        };

        // Listen for storage events across tabs
        const handleStorage = (e: StorageEvent) => {
            if (e.key === THEME_STORAGE_KEY && (e.newValue === 'light' || e.newValue === 'dark')) {
                setThemeState(e.newValue);
                applyTheme(e.newValue);
            }
        };

        mediaQuery.addEventListener('change', handleSystemChange);
        window.addEventListener('storage', handleStorage);

        return () => {
            mediaQuery.removeEventListener('change', handleSystemChange);
            window.removeEventListener('storage', handleStorage);
        };
    }, [theme, applyTheme]);

    return {
        theme,
        isDark: theme === 'dark',
        setTheme,
        toggleTheme,
    };
}
