import React, { ReactNode } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { LogOut, ShieldCheck, UserCheck, Activity } from 'lucide-react';
import FloatingDslChat from '@/Components/FloatingDslChat';
import ThemeToggle from '@/Components/ThemeToggle';

interface Props {
    children: ReactNode;
    title?: string;
    kicker?: string;
    description?: string;
    actions?: ReactNode;
}

interface SharedAuth {
    user: {
        id: number;
        name: string;
        email: string;
        roles: string[];
        permissions: string[];
        can_view_identity: boolean;
        can_view_audit: boolean;
        can_manage_system: boolean;
    } | null;
}

export default function AppLayout({ children, title, kicker = 'TOOL 003 · VOC ANALYSIS', description, actions }: Props) {
    const page = usePage<{ auth: SharedAuth; flash: { success?: string; error?: string; warning?: string } }>();
    const { auth, flash } = page.props;
    const user = auth.user;
    const currentUrl = page.url || window.location.pathname;

    const handleLogout = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/logout');
    };

    const navItems = [
        { name: 'Dashboard', href: '/dashboard', active: currentUrl.startsWith('/dashboard') },
        { name: 'Assistant', href: '/assistant', active: currentUrl.startsWith('/assistant') },
        { name: 'Forecast', href: '/forecast', active: currentUrl.startsWith('/forecast') },
        { name: 'Data', href: '/data', active: currentUrl.startsWith('/data') },
    ];

    if (user?.permissions.includes('cases.view')) {
        navItems.push({ name: 'Seguimiento', href: '/performance-cases', active: currentUrl.startsWith('/performance-cases') });
    }

    if (user?.permissions.includes('reports.view')) {
        navItems.push({ name: 'Reportes', href: '/reports/teams', active: currentUrl.startsWith('/reports/teams') });
    }

    if (user?.permissions.includes('teams.manage')) {
        navItems.push({ name: 'Equipos', href: '/teams', active: currentUrl.startsWith('/teams') });
    }

    if (user?.permissions.includes('categories.manage')) {
        navItems.push({ name: 'Categories', href: '/categories', active: currentUrl.startsWith('/categories') });
    }

    if (user?.can_view_audit || user?.permissions.includes('audit.view')) {
        navItems.push({ name: 'Audit', href: '/audit', active: currentUrl.startsWith('/audit') });
    }

    if (user?.permissions.includes('users.manage')) {
        navItems.push({ name: 'Administration', href: '/admin', active: currentUrl.startsWith('/admin') });
    }

    return (
        <div className="min-h-screen bg-[#f7f6f1] text-[#18221d] flex flex-col font-sans selection:bg-[#d7f45b] selection:text-[#18221d]">
            {/* Global Header (86px, editorial brand mark, flat line) */}
            <header className="h-[86px] border-b border-[#ccd1ca] px-[4vw] md:px-[6vw] flex items-center justify-between bg-[#f7f6f1] sticky top-0 z-30">
                <div className="flex items-center space-x-8">
                    <Link href="/dashboard" className="flex items-center space-x-3 group">
                        <div className="w-10 h-10 rounded-full bg-[#18221d] flex items-center justify-center relative shadow-sm transition-transform group-hover:scale-105">
                            <span className="font-serif text-2xl text-white select-none">A</span>
                            <span className="w-2.5 h-2.5 rounded-full bg-[#d7f45b] absolute -top-0.5 -right-0.5 border-2 border-[#f7f6f1]"></span>
                        </div>
                        <div className="flex flex-col">
                            <span className="font-serif text-xl tracking-tight leading-none text-[#18221d]">Atlas Tools</span>
                            <span className="text-[10px] uppercase font-bold tracking-[0.16em] text-[#687169] mt-0.5">VOC Analyzer</span>
                        </div>
                    </Link>

                    {/* Desktop Navigation */}
                    <nav className="hidden md:flex items-center space-x-6">
                        {navItems.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`text-[14px] font-medium transition-colors pb-1 ${
                                    item.active
                                        ? 'text-[#18221d] border-b-2 border-[#18221d] font-semibold'
                                        : 'text-[#687169] hover:text-[#18221d]'
                                }`}
                            >
                                {item.name}
                            </Link>
                        ))}
                    </nav>
                </div>

                {/* Theme Toggle, User status & Logout */}
                <div className="flex items-center space-x-3">
                    <ThemeToggle />

                    {user ? (
                        <div className="flex items-center space-x-3">
                            <div className="hidden sm:flex flex-col items-end text-right">
                                <span className="text-[13px] font-medium text-[#18221d] flex items-center gap-1.5">
                                    {user.name}
                                    {user.can_view_identity && (
                                        <span title="Real Identity View Enabled" className="inline-flex items-center text-[10px] bg-[#dce4d8] text-[#18221d] px-1.5 py-0.5 rounded font-mono">
                                            ID:VIEW
                                        </span>
                                    )}
                                </span>
                                <span className="text-[11px] text-[#687169]">{user.email}</span>
                            </div>
                            <button
                                onClick={handleLogout}
                                title="Sign out"
                                className="p-2 border border-[#ccd1ca] hover:bg-white text-[#18221d] transition-colors rounded-none cursor-pointer"
                            >
                                <LogOut className="w-4 h-4" />
                            </button>
                        </div>
                    ) : (
                        <Link
                            href="/login"
                            className="px-4 py-2 text-sm font-medium bg-[#18221d] text-white hover:bg-black transition-colors"
                        >
                            Sign In
                        </Link>
                    )}
                </div>
            </header>

            {/* Flash notifications */}
            {flash.success && (
                <div className="bg-[#eef7e8] border-b border-[#aac69c] text-[#18221d] px-[6vw] py-3 text-sm flex items-center justify-between">
                    <span>{flash.success}</span>
                </div>
            )}
            {flash.error && (
                <div className="bg-[#fff0ed] border-b border-[#e1a89e] text-[#18221d] px-[6vw] py-3 text-sm flex items-center justify-between">
                    <span>{flash.error}</span>
                </div>
            )}

            {/* Hero Section (design.md Tool Hero in #dce4d8) */}
            {title && (
                <section className="bg-[#dce4d8] border-b border-[#ccd1ca] py-10 md:py-14 px-[4vw] md:px-[6vw]">
                    <div className="max-w-[1240px] mx-auto flex flex-col md:flex-row md:items-end justify-between gap-6">
                        <div className="max-w-[720px]">
                            <span className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#18221d]/70 block mb-2">
                                {kicker}
                            </span>
                            <h1 className="font-serif text-3xl sm:text-4xl md:text-5xl text-[#18221d] tracking-tight leading-[1.1]">
                                {title}
                            </h1>
                            {description && (
                                <p className="mt-3 text-[15px] md:text-[16px] text-[#18221d]/85 leading-relaxed font-sans">
                                    {description}
                                </p>
                            )}
                        </div>

                        {actions && (
                            <div className="flex items-center space-x-3 flex-shrink-0">
                                {actions}
                            </div>
                        )}
                    </div>
                </section>
            )}

            {/* Admin Sub-navigation */}
            {currentUrl.startsWith('/admin') && (
                <div className="bg-white border-b border-[#ccd1ca] px-[4vw] md:px-[6vw]">
                    <div className="max-w-[1400px] mx-auto flex items-center space-x-6 text-[13px] font-medium">
                        <Link
                            href="/admin"
                            className={`py-3 transition-colors border-b-2 flex items-center gap-1.5 ${
                                currentUrl === '/admin'
                                    ? 'border-[#18221d] text-[#18221d] font-semibold'
                                    : 'border-transparent text-[#687169] hover:text-[#18221d]'
                            }`}
                        >
                            <span>Usuarios & Accesos</span>
                        </Link>
                        <Link
                            href="/admin/requests"
                            className={`py-3 transition-colors border-b-2 flex items-center gap-1.5 ${
                                currentUrl.startsWith('/admin/requests')
                                    ? 'border-[#18221d] text-[#18221d] font-semibold'
                                    : 'border-transparent text-[#687169] hover:text-[#18221d]'
                            }`}
                        >
                            <span>Histórico de Requests & Tokens</span>
                        </Link>
                        <Link
                            href="/admin/tools"
                            className={`py-3 transition-colors border-b-2 flex items-center gap-1.5 ${
                                currentUrl.startsWith('/admin/tools')
                                    ? 'border-[#18221d] text-[#18221d] font-semibold'
                                    : 'border-transparent text-[#687169] hover:text-[#18221d]'
                            }`}
                        >
                            <span>Gestión de Tools DSL</span>
                        </Link>
                    </div>
                </div>
            )}

            {/* Main Content Workspace */}
            <main className="flex-1 w-full max-w-[1400px] mx-auto px-4 sm:px-6 md:px-8 py-8 md:py-10">
                {children}
            </main>

            {/* Minimal Editorial Footer */}
            <footer className="border-t border-[#ccd1ca] py-6 px-[6vw] text-center md:text-left flex flex-col md:flex-row items-center justify-between text-xs text-[#687169]">
                <span>Atlas Tools — ATLAS VOC Analysis (v1.0.0) · Confidential VOC & Analytics</span>
                <span className="mt-2 md:mt-0">Privacy by Design · Cryptographically Sealed Audit</span>
            </footer>

            {/* Floating DSL Assistant Copilot (Exclusivo en pestaña de Administración) */}
            {user && currentUrl.startsWith('/admin') && (
                <FloatingDslChat />
            )}
        </div>
    );
}
