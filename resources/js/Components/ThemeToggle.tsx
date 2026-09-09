import React from 'react';
import { Moon, Sun } from 'lucide-react';
import { useTheme } from '@/hooks/useTheme';

interface ThemeToggleProps {
    className?: string;
    showLabel?: boolean;
}

export default function ThemeToggle({ className = '', showLabel = false }: ThemeToggleProps) {
    const { isDark, toggleTheme } = useTheme();

    return (
        <button
            type="button"
            onClick={toggleTheme}
            title={isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
            aria-label={isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
            className={`p-2 border border-[#ccd1ca] hover:bg-white text-[#18221d] transition-all rounded-none flex items-center gap-2 cursor-pointer focus:outline-hidden ${className}`}
        >
            {isDark ? (
                <Sun className="w-4 h-4 text-[#d7f45b] transition-transform duration-300 hover:rotate-45" />
            ) : (
                <Moon className="w-4 h-4 text-[#18221d] transition-transform duration-300 hover:-rotate-12" />
            )}
            {showLabel && (
                <span className="text-xs font-medium">
                    {isDark ? 'Modo Claro' : 'Modo Oscuro'}
                </span>
            )}
        </button>
    );
}
