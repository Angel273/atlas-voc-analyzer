import React, { useState, useEffect } from 'react';
import { Target, X, Check, AlertCircle, Save, Sparkles, SlidersHorizontal, Info } from 'lucide-react';
import { router } from '@inertiajs/react';

export interface KpiGoalItem {
    metric: string;
    name: string;
    target_value: number;
    target_percentage: number;
    warning_threshold: number;
    warning_percentage: number;
    unit: string;
    scale: string;
    description?: string;
    updated_at?: string;
}

export type KpiGoalsMap = Record<'nps' | 'csat' | 'professionalism' | string, KpiGoalItem>;

interface Props {
    isOpen: boolean;
    onClose: () => void;
    initialGoals?: KpiGoalsMap | null;
    onGoalsSaved?: (updated: KpiGoalsMap) => void;
}

const PRESETS = [
    {
        label: 'Estándar Corporativo',
        description: 'NPS +0.50 (50%), CSAT 80%, Profesionalismo 85%',
        nps: { target: 0.50, warning: 0.20 },
        csat: { target: 0.80, warning: 0.70 },
        professionalism: { target: 0.85, warning: 0.75 },
    },
    {
        label: 'Alto Rendimiento',
        description: 'NPS +0.65 (65%), CSAT 88%, Profesionalismo 92%',
        nps: { target: 0.65, warning: 0.40 },
        csat: { target: 0.88, warning: 0.80 },
        professionalism: { target: 0.92, warning: 0.85 },
    },
    {
        label: 'Conservador / Recuperación',
        description: 'NPS +0.35 (35%), CSAT 72%, Profesionalismo 78%',
        nps: { target: 0.35, warning: 0.10 },
        csat: { target: 0.72, warning: 0.62 },
        professionalism: { target: 0.78, warning: 0.68 },
    },
];

export default function KpiGoalsModal({ isOpen, onClose, initialGoals, onGoalsSaved }: Props) {
    if (!isOpen) return null;

    // Local form state
    const [npsTarget, setNpsTarget] = useState<number>(initialGoals?.nps?.target_value ?? 0.50);
    const [npsWarning, setNpsWarning] = useState<number>(initialGoals?.nps?.warning_threshold ?? 0.20);
    const [npsDesc, setNpsDesc] = useState<string>(initialGoals?.nps?.description ?? 'Meta de lealtad neta NPS (+0.50 en escala -1 a 1, equiv. +50%).');

    const [csatTarget, setCsatTarget] = useState<number>(initialGoals?.csat?.target_value ?? 0.80);
    const [csatWarning, setCsatWarning] = useState<number>(initialGoals?.csat?.warning_threshold ?? 0.70);
    const [csatDesc, setCsatDesc] = useState<string>(initialGoals?.csat?.description ?? 'Meta de satisfacción general CSAT (0.80, equiv. 80%).');

    const [profTarget, setProfTarget] = useState<number>(initialGoals?.professionalism?.target_value ?? 0.85);
    const [profWarning, setProfWarning] = useState<number>(initialGoals?.professionalism?.warning_threshold ?? 0.75);
    const [profDesc, setProfDesc] = useState<string>(initialGoals?.professionalism?.description ?? 'Meta de profesionalismo en la atención (0.85, equiv. 85%).');

    const [saving, setSaving] = useState<boolean>(false);
    const [errorMsg, setErrorMsg] = useState<string | null>(null);
    const [successMsg, setSuccessMsg] = useState<string | null>(null);

    // Sync state if initialGoals updates
    useEffect(() => {
        if (initialGoals) {
            if (initialGoals.nps) {
                setNpsTarget(initialGoals.nps.target_value);
                setNpsWarning(initialGoals.nps.warning_threshold);
                setNpsDesc(initialGoals.nps.description || '');
            }
            if (initialGoals.csat) {
                setCsatTarget(initialGoals.csat.target_value);
                setCsatWarning(initialGoals.csat.warning_threshold);
                setCsatDesc(initialGoals.csat.description || '');
            }
            if (initialGoals.professionalism) {
                setProfTarget(initialGoals.professionalism.target_value);
                setProfWarning(initialGoals.professionalism.warning_threshold);
                setProfDesc(initialGoals.professionalism.description || '');
            }
        }
    }, [initialGoals]);

    const applyPreset = (preset: typeof PRESETS[0]) => {
        setNpsTarget(preset.nps.target);
        setNpsWarning(preset.nps.warning);
        setCsatTarget(preset.csat.target);
        setCsatWarning(preset.csat.warning);
        setProfTarget(preset.professionalism.target);
        setProfWarning(preset.professionalism.warning);
    };

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrorMsg(null);
        setSuccessMsg(null);

        try {
            const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
            const res = await fetch('/kpi-goals', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    goals: {
                        nps: {
                            target_value: npsTarget,
                            warning_threshold: npsWarning,
                            description: npsDesc,
                        },
                        csat: {
                            target_value: csatTarget,
                            warning_threshold: csatWarning,
                            description: csatDesc,
                        },
                        professionalism: {
                            target_value: profTarget,
                            warning_threshold: profWarning,
                            description: profDesc,
                        },
                    },
                }),
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Error al guardar las metas de KPIs.');
            }

            setSuccessMsg('Metas actualizadas correctamente. El Dashboard y el Chat de IA ya cuentan con este contexto.');
            if (onGoalsSaved && data.goals) {
                onGoalsSaved(data.goals);
            }
            router.reload({ only: ['kpi_goals'] });

            setTimeout(() => {
                onClose();
            }, 1200);
        } catch (err: any) {
            setErrorMsg(err.message || 'Error de comunicación al actualizar metas.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-white border border-[#ccd1ca] shadow-2xl max-w-2xl w-full flex flex-col text-[#18221d] animate-in fade-in zoom-in-95 duration-150">
                {/* Modal Header */}
                <div className="p-6 border-b border-[#ccd1ca] flex items-center justify-between bg-[#f7f6f1]">
                    <div className="flex items-center gap-3">
                        <div className="w-9 h-9 rounded-full bg-[#18221d] text-[#d7f45b] flex items-center justify-center border border-[#18221d]">
                            <Target className="w-5 h-5" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h2 className="font-serif text-xl tracking-tight leading-none text-[#18221d]">
                                    Definición de Metas Operacionales
                                </h2>
                                <span className="text-[10px] uppercase font-bold tracking-widest bg-[#d7f45b] text-[#18221d] px-2 py-0.5 border border-[#18221d]/20">
                                    KPI GOALS
                                </span>
                            </div>
                            <p className="text-xs text-[#687169] mt-1">
                                Establece los objetivos para NPS, CSAT y Profesionalismo. Se reflejarán en el Dashboard y como contexto de referencia en el Chat de IA.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-1.5 text-[#687169] hover:text-[#18221d] hover:bg-[#eceae2] transition-colors"
                        title="Cerrar modal"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Feedback Messages */}
                {errorMsg && (
                    <div className="mx-6 mt-4 p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center gap-2">
                        <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" />
                        <span>{errorMsg}</span>
                    </div>
                )}

                {successMsg && (
                    <div className="mx-6 mt-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2">
                        <Check className="w-4 h-4 shrink-0 text-emerald-600" />
                        <span>{successMsg}</span>
                    </div>
                )}

                {/* Modal Body */}
                <form onSubmit={handleSave} className="p-6 space-y-6 text-xs">
                    {/* Presets Bar */}
                    <div className="bg-[#f7f6f1] p-3.5 border border-[#ccd1ca]">
                        <div className="flex items-center justify-between mb-2">
                            <span className="font-bold uppercase tracking-wider text-[11px] text-[#687169] flex items-center gap-1.5">
                                <Sparkles className="w-3.5 h-3.5 text-[#18221d]" />
                                Plantillas Rápidas de Metas
                            </span>
                            <span className="text-[10px] text-[#687169]">Haz clic para aplicar valores</span>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            {PRESETS.map((p, idx) => (
                                <button
                                    key={idx}
                                    type="button"
                                    onClick={() => applyPreset(p)}
                                    className="p-2 bg-white border border-[#ccd1ca] hover:border-[#18221d] text-left transition-colors cursor-pointer group"
                                >
                                    <div className="font-semibold text-[#18221d] text-xs group-hover:text-black">
                                        {p.label}
                                    </div>
                                    <div className="text-[10px] text-[#687169] mt-0.5 leading-tight">
                                        {p.description}
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* Scale Notice Alert */}
                    <div className="p-3 bg-amber-50/70 border border-amber-200/80 text-amber-900 text-[11px] flex items-start gap-2">
                        <Info className="w-4 h-4 shrink-0 text-amber-700 mt-0.5" />
                        <div>
                            <strong>Distinción de escala métrica:</strong> En ATLAS VOC, el <strong>NPS va de -1.00 a +1.00</strong> (donde <code className="font-mono bg-amber-100/60 px-1 py-0.2">+0.50</code> representa <code className="font-mono bg-amber-100/60 px-1 py-0.2">+50%</code> y <code className="font-mono bg-amber-100/60 px-1 py-0.2">-0.20</code> representa <code className="font-mono bg-amber-100/60 px-1 py-0.2">-20%</code>). Puedes ingresar el valor como decimal (ej. 0.50) o porcentaje (ej. 50).
                        </div>
                    </div>

                    <div className="space-y-4">
                        {/* 1. NPS Target */}
                        <div className="border border-[#ccd1ca] p-4 bg-white space-y-3">
                            <div className="flex items-center justify-between border-b border-[#ccd1ca]/60 pb-2">
                                <div>
                                    <span className="font-serif text-sm font-semibold text-[#18221d]">
                                        1. Net Promoter Score (NPS)
                                    </span>
                                    <span className="text-[11px] text-[#687169] ml-2">
                                        Escala decimal: <code className="font-mono bg-[#f7f6f1] px-1 py-0.5">-1.00 a +1.00</code>
                                    </span>
                                </div>
                                <span className="font-mono text-xs px-2 py-0.5 bg-[#18221d] text-[#d7f45b] font-bold">
                                    {npsTarget >= 0 ? `+${(npsTarget * 100).toFixed(1)}%` : `${(npsTarget * 100).toFixed(1)}%`}
                                </span>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-[#18221d] font-semibold mb-1">
                                        Meta Objetivo (Decimal / %)
                                    </label>
                                    <div className="flex items-center">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="-1"
                                            max="1"
                                            value={npsTarget}
                                            onChange={(e) => setNpsTarget(parseFloat(e.target.value) || 0)}
                                            className="w-full border border-[#ccd1ca] px-3 py-1.5 text-xs font-mono focus:outline-none focus:border-[#18221d]"
                                            placeholder="0.50"
                                            required
                                        />
                                        <span className="ml-2 text-xs font-mono text-[#687169] w-14 text-right">
                                            {npsTarget >= 0 ? `+${(npsTarget * 100).toFixed(0)}%` : `${(npsTarget * 100).toFixed(0)}%`}
                                        </span>
                                    </div>
                                    <span className="text-[10px] text-[#687169] block mt-0.5">Ej: 0.50 (+50%), 0.00 (Neutro), -0.20 (-20%)</span>
                                </div>
                                <div>
                                    <label className="block text-[#18221d] font-semibold mb-1">
                                        Umbral de Alerta Preventiva
                                    </label>
                                    <div className="flex items-center">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="-1"
                                            max="1"
                                            value={npsWarning}
                                            onChange={(e) => setNpsWarning(parseFloat(e.target.value) || 0)}
                                            className="w-full border border-[#ccd1ca] px-3 py-1.5 text-xs font-mono focus:outline-none focus:border-[#18221d]"
                                            placeholder="0.20"
                                        />
                                        <span className="ml-2 text-xs font-mono text-[#687169] w-14 text-right">
                                            {npsWarning >= 0 ? `+${(npsWarning * 100).toFixed(0)}%` : `${(npsWarning * 100).toFixed(0)}%`}
                                        </span>
                                    </div>
                                    <span className="text-[10px] text-[#687169] block mt-0.5">Valores menores activarán semáforo rojo / crítico</span>
                                </div>
                            </div>
                            <div>
                                <input
                                    type="text"
                                    value={npsDesc}
                                    onChange={(e) => setNpsDesc(e.target.value)}
                                    placeholder="Nota u origen de la meta de NPS..."
                                    className="w-full border border-[#ccd1ca] px-2.5 py-1 text-[11px] text-[#687169] focus:outline-none focus:border-[#18221d]"
                                />
                            </div>
                        </div>

                        {/* 2. CSAT Target */}
                        <div className="border border-[#ccd1ca] p-4 bg-white space-y-3">
                            <div className="flex items-center justify-between border-b border-[#ccd1ca]/60 pb-2">
                                <div>
                                    <span className="font-serif text-sm font-semibold text-[#18221d]">
                                        2. Customer Satisfaction (CSAT)
                                    </span>
                                    <span className="text-[11px] text-[#687169] ml-2">
                                        Escala: <code className="font-mono bg-[#f7f6f1] px-1 py-0.5">0.00 a 1.00 (0% - 100%)</code>
                                    </span>
                                </div>
                                <span className="font-mono text-xs px-2 py-0.5 bg-[#aac69c] text-[#18221d] font-bold">
                                    {(csatTarget * 100).toFixed(1)}%
                                </span>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-[#18221d] font-semibold mb-1">
                                        Meta Objetivo (Decimal / %)
                                    </label>
                                    <div className="flex items-center">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="1"
                                            value={csatTarget}
                                            onChange={(e) => setCsatTarget(parseFloat(e.target.value) || 0)}
                                            className="w-full border border-[#ccd1ca] px-3 py-1.5 text-xs font-mono focus:outline-none focus:border-[#18221d]"
                                            placeholder="0.80"
                                            required
                                        />
                                        <span className="ml-2 text-xs font-mono text-[#687169] w-14 text-right">
                                            {(csatTarget * 100).toFixed(0)}%
                                        </span>
                                    </div>
                                    <span className="text-[10px] text-[#687169] block mt-0.5">Ej: 0.80 = 80% satisfacción</span>
                                </div>
                                <div>
                                    <label className="block text-[#18221d] font-semibold mb-1">
                                        Umbral de Alerta Preventiva
                                    </label>
                                    <div className="flex items-center">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="1"
                                            value={csatWarning}
                                            onChange={(e) => setCsatWarning(parseFloat(e.target.value) || 0)}
                                            className="w-full border border-[#ccd1ca] px-3 py-1.5 text-xs font-mono focus:outline-none focus:border-[#18221d]"
                                            placeholder="0.70"
                                        />
                                        <span className="ml-2 text-xs font-mono text-[#687169] w-14 text-right">
                                            {(csatWarning * 100).toFixed(0)}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <input
                                    type="text"
                                    value={csatDesc}
                                    onChange={(e) => setCsatDesc(e.target.value)}
                                    placeholder="Nota u origen de la meta de CSAT..."
                                    className="w-full border border-[#ccd1ca] px-2.5 py-1 text-[11px] text-[#687169] focus:outline-none focus:border-[#18221d]"
                                />
                            </div>
                        </div>

                        {/* 3. Professionalism Target */}
                        <div className="border border-[#ccd1ca] p-4 bg-white space-y-3">
                            <div className="flex items-center justify-between border-b border-[#ccd1ca]/60 pb-2">
                                <div>
                                    <span className="font-serif text-sm font-semibold text-[#18221d]">
                                        3. Profesionalismo (Professionalism Score)
                                    </span>
                                    <span className="text-[11px] text-[#687169] ml-2">
                                        Escala: <code className="font-mono bg-[#f7f6f1] px-1 py-0.5">0.00 a 1.00 (0% - 100%)</code>
                                    </span>
                                </div>
                                <span className="font-mono text-xs px-2 py-0.5 bg-[#d7bf70] text-[#18221d] font-bold">
                                    {(profTarget * 100).toFixed(1)}%
                                </span>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-[#18221d] font-semibold mb-1">
                                        Meta Objetivo (Decimal / %)
                                    </label>
                                    <div className="flex items-center">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="1"
                                            value={profTarget}
                                            onChange={(e) => setProfTarget(parseFloat(e.target.value) || 0)}
                                            className="w-full border border-[#ccd1ca] px-3 py-1.5 text-xs font-mono focus:outline-none focus:border-[#18221d]"
                                            placeholder="0.85"
                                            required
                                        />
                                        <span className="ml-2 text-xs font-mono text-[#687169] w-14 text-right">
                                            {(profTarget * 100).toFixed(0)}%
                                        </span>
                                    </div>
                                    <span className="text-[10px] text-[#687169] block mt-0.5">Ej: 0.85 = 85% profesionalismo</span>
                                </div>
                                <div>
                                    <label className="block text-[#18221d] font-semibold mb-1">
                                        Umbral de Alerta Preventiva
                                    </label>
                                    <div className="flex items-center">
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="1"
                                            value={profWarning}
                                            onChange={(e) => setProfWarning(parseFloat(e.target.value) || 0)}
                                            className="w-full border border-[#ccd1ca] px-3 py-1.5 text-xs font-mono focus:outline-none focus:border-[#18221d]"
                                            placeholder="0.75"
                                        />
                                        <span className="ml-2 text-xs font-mono text-[#687169] w-14 text-right">
                                            {(profWarning * 100).toFixed(0)}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <input
                                    type="text"
                                    value={profDesc}
                                    onChange={(e) => setProfDesc(e.target.value)}
                                    placeholder="Nota u origen de la meta de Profesionalismo..."
                                    className="w-full border border-[#ccd1ca] px-2.5 py-1 text-[11px] text-[#687169] focus:outline-none focus:border-[#18221d]"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Footer Actions */}
                    <div className="pt-4 border-t border-[#ccd1ca] flex items-center justify-between">
                        <span className="text-[11px] text-[#687169]">
                            Las metas se auditan con hash criptográfico encadenado.
                        </span>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={onClose}
                                disabled={saving}
                                className="px-4 py-2 border border-[#ccd1ca] text-[#18221d] hover:bg-[#f7f6f1] text-xs font-medium transition-colors cursor-pointer"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={saving}
                                className="px-5 py-2 bg-[#18221d] text-white hover:bg-black text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer disabled:opacity-50"
                            >
                                <Save className="w-3.5 h-3.5" />
                                <span>{saving ? 'Guardando...' : 'Guardar y Aplicar Metas'}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
}
