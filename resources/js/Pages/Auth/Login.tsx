import React from 'react';
import { useForm, Head } from '@inertiajs/react';
import { Lock, ArrowRight, User } from 'lucide-react';
import ThemeToggle from '@/Components/ThemeToggle';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <div className="min-h-screen bg-[#f7f6f1] text-[#18221d] flex flex-col justify-between font-sans selection:bg-[#d7f45b] selection:text-[#18221d]">
            <Head title="Sign In — ATLAS VOC Analysis" />

            {/* Top Minimal Brand Bar */}
            <div className="h-[86px] border-b border-[#ccd1ca] px-[6vw] flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <div className="w-10 h-10 rounded-full bg-[#18221d] flex items-center justify-center relative">
                        <span className="font-serif text-2xl text-white select-none">A</span>
                        <span className="w-2.5 h-2.5 rounded-full bg-[#d7f45b] absolute -top-0.5 -right-0.5 border-2 border-[#f7f6f1]"></span>
                    </div>
                    <span className="font-serif text-xl tracking-tight text-[#18221d]">Atlas Tools</span>
                </div>
                <div className="flex items-center space-x-3">
                    <span className="text-[12px] font-bold tracking-[0.16em] uppercase text-[#687169] hidden sm:inline">
                        VOC ANALYSIS V1
                    </span>
                    <ThemeToggle />
                </div>
            </div>

            {/* Centered Login Card */}
            <div className="max-w-[440px] w-full mx-auto px-6 py-12">
                <div className="border border-[#ccd1ca] bg-white p-8 md:p-10 shadow-none">
                    <div className="mb-8">
                        <span className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#687169] block mb-2">
                            AUTHENTICATION
                        </span>
                        <h1 className="font-serif text-3xl md:text-4xl text-[#18221d] tracking-tight">
                            Sign In
                        </h1>
                        <p className="text-[14px] text-[#687169] mt-2">
                            Enter your credentials to access Voice of Customer analytics.
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <label className="block text-[12px] font-bold tracking-[0.08em] uppercase text-[#18221d] mb-1.5">
                                Email or Username
                            </label>
                            <input
                                type="text"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="name@company.com"
                                required
                                autoFocus
                                className="w-full px-4 py-2.5 bg-white border border-[#ccd1ca] text-[#18221d] text-sm focus:outline-none focus:border-[#18221d] rounded-none transition-colors"
                            />
                            {errors.email && (
                                <p className="text-[12px] text-[#943126] mt-1.5 font-medium">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="block text-[12px] font-bold tracking-[0.08em] uppercase text-[#18221d] mb-1.5">
                                Password
                            </label>
                            <input
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                required
                                className="w-full px-4 py-2.5 bg-white border border-[#ccd1ca] text-[#18221d] text-sm focus:outline-none focus:border-[#18221d] rounded-none transition-colors"
                            />
                            {errors.password && (
                                <p className="text-[12px] text-[#943126] mt-1.5 font-medium">
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center justify-between text-[13px] pt-1">
                            <label className="flex items-center space-x-2 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(e) => setData('remember', e.target.checked)}
                                    className="accent-[#18221d]"
                                />
                                <span className="text-[#687169]">Remember session</span>
                            </label>
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full mt-4 px-6 py-3 bg-[#18221d] text-white hover:bg-black font-medium text-[14px] flex items-center justify-center space-x-2 transition-colors disabled:opacity-50"
                        >
                            <span>Sign In</span>
                            <ArrowRight className="w-4 h-4" />
                        </button>
                    </form>
                </div>
            </div>

            {/* Footer */}
            <div className="py-6 px-[6vw] text-center text-xs text-[#687169] border-t border-[#ccd1ca]">
                Atlas Tools · Security & Privacy First VOC Analytics
            </div>
        </div>
    );
}
