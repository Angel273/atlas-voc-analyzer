import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { 
    FileText, Download, Sparkles, Filter, Calendar, Users, 
    RefreshCw, CheckCircle2, AlertTriangle, ChevronRight, Eye,
    Archive, Check, Clock, Layers, Target, SlidersHorizontal
} from 'lucide-react';
import KpiGoalsModal, { KpiGoalsMap } from '@/Components/KpiGoalsModal';

interface Team {
    id: number;
    name: string;
    code: string;
    supervisor?: {
        name: string;
    };
}

interface TeamReportItem {
    id: number;
    team: {
        id: number;
        name: string;
        code: string;
        supervisor?: {
            name: string;
        };
    };
    period_from: string;
    period_to: string;
    cutoff_date: string;
    data_version: string;
    status: string;
    progress?: number;
    stage?: string;
    file_path?: string;
    file_hash?: string;
    file_size?: number;
    tokens_used: number;
    created_at: string;
    created_by_user?: {
        name: string;
    };
}

interface Props {
    reports: {
        data: TeamReportItem[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    teams: Team[];
    kpi_goals?: KpiGoalsMap;
    filters: {
        team_id?: string;
        status?: string;
        from_date?: string;
        to_date?: string;
    };
}

export default function TeamReportsIndex({ reports, teams, filters, kpi_goals }: Props) {
    const today = new Date().toISOString().split('T')[0];
    const defaultStart = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    // KPI Goals State
    const [kpiGoals, setKpiGoals] = useState<KpiGoalsMap | null>(kpi_goals || null);
    const [showGoalsModal, setShowGoalsModal] = useState(false);
    const [customizeBatchGoals, setCustomizeBatchGoals] = useState(false);
    const [batchGoals, setBatchGoals] = useState({
        nps: { 
            target: kpi_goals?.nps?.target_percentage ?? 50, 
            warning: kpi_goals?.nps?.warning_percentage ?? 20 
        },
        csat: { 
            target: kpi_goals?.csat?.target_percentage ?? 80, 
            warning: kpi_goals?.csat?.warning_percentage ?? 70 
        },
        professionalism: { 
            target: kpi_goals?.professionalism?.target_percentage ?? 85, 
            warning: kpi_goals?.professionalism?.warning_percentage ?? 75 
        },
    });
    const [isGenerating, setIsGenerating] = useState(false);

    // Generation Form
    const { data: genData, setData: setGenData, processing: formGenerating } = useForm({
        team_ids: [] as number[],
        period_from: defaultStart,
        period_to: today,
        cutoff_date: today,
        compare_previous_period: true,
        include_verbatims: true,
        include_open_cases: true,
    });

    const generating = formGenerating || isGenerating;

    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewData, setPreviewData] = useState<any | null>(null);
    const [selectedReportIds, setSelectedReportIds] = useState<number[]>([]);

    const hasActiveReports = reports.data.some((r) => r.status === 'pending' || r.status === 'processing');

    React.useEffect(() => {
        if (!hasActiveReports) return;

        const interval = setInterval(() => {
            router.reload({ only: ['reports'] });
        }, 3000);

        return () => clearInterval(interval);
    }, [hasActiveReports]);

    const handleSelectAllTeams = () => {
        if (genData.team_ids.length === teams.length) {
            setGenData('team_ids', []);
        } else {
            setGenData('team_ids', teams.map((t) => t.id));
        }
    };

    const handleTeamCheckbox = (teamId: number) => {
        if (genData.team_ids.includes(teamId)) {
            setGenData('team_ids', genData.team_ids.filter((id) => id !== teamId));
        } else {
            setGenData('team_ids', [...genData.team_ids, teamId]);
        }
    };

    const handleFetchPreview = async () => {
        if (genData.team_ids.length === 0) {
            alert('Seleccione al menos un equipo para la vista previa.');
            return;
        }

        setPreviewLoading(true);
        setPreviewData(null);

        const goalsPayload = customizeBatchGoals ? {
            nps: { 
                target_value: Number(batchGoals.nps.target) / 100, 
                warning_threshold: Number(batchGoals.nps.warning) / 100 
            },
            csat: { 
                target_value: Number(batchGoals.csat.target) / 100, 
                warning_threshold: Number(batchGoals.csat.warning) / 100 
            },
            professionalism: { 
                target_value: Number(batchGoals.professionalism.target) / 100, 
                warning_threshold: Number(batchGoals.professionalism.warning) / 100 
            },
        } : undefined;

        try {
            const res = await fetch('/reports/teams/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    team_id: genData.team_ids[0],
                    period_from: genData.period_from,
                    period_to: genData.period_to,
                    cutoff_date: genData.cutoff_date,
                    compare_previous_period: genData.compare_previous_period,
                    include_verbatims: genData.include_verbatims,
                    include_open_cases: genData.include_open_cases,
                    goals: goalsPayload,
                }),
            });

            if (!res.ok) {
                throw new Error('Error al obtener la vista previa');
            }

            const json = await res.json();
            setPreviewData(json.data);
        } catch (err: any) {
            alert(err.message || 'No fue posible cargar la vista previa.');
        } finally {
            setPreviewLoading(false);
        }
    };

    const handleGenerate = (e: React.FormEvent) => {
        e.preventDefault();
        if (genData.team_ids.length === 0) {
            alert('Seleccione al menos un equipo.');
            return;
        }

        const goalsPayload = customizeBatchGoals ? {
            nps: { 
                target_value: Number(batchGoals.nps.target) / 100, 
                warning_threshold: Number(batchGoals.nps.warning) / 100 
            },
            csat: { 
                target_value: Number(batchGoals.csat.target) / 100, 
                warning_threshold: Number(batchGoals.csat.warning) / 100 
            },
            professionalism: { 
                target_value: Number(batchGoals.professionalism.target) / 100, 
                warning_threshold: Number(batchGoals.professionalism.warning) / 100 
            },
        } : null;

        setIsGenerating(true);
        router.post('/reports/teams/generate', {
            ...genData,
            goals: goalsPayload,
        }, {
            onSuccess: () => {
                setPreviewData(null);
            },
            onFinish: () => {
                setIsGenerating(false);
            },
        });
    };

    const toggleReportSelection = (reportId: number) => {
        if (selectedReportIds.includes(reportId)) {
            setSelectedReportIds(selectedReportIds.filter((id) => id !== reportId));
        } else {
            setSelectedReportIds([...selectedReportIds, reportId]);
        }
    };

    const handleSelectAllReports = () => {
        const completedIds = reports.data.filter((r) => r.status === 'completed').map((r) => r.id);
        if (selectedReportIds.length === completedIds.length) {
            setSelectedReportIds([]);
        } else {
            setSelectedReportIds(completedIds);
        }
    };

    const handleDownloadZip = () => {
        if (selectedReportIds.length === 0) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/reports/teams/download-zip';

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        selectedReportIds.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'report_ids[]';
            input.value = id.toString();
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    };

    return (
        <AppLayout
            title="Constructor de Reportes de Desempeño"
            kicker="HERRAMIENTA 005 · REPORTES EJECUTIVOS"
            description="Generador en lote de informes PDF analíticos por equipo de supervisor, con narrativa gerencial redactada por IA."
            actions={
                <button
                    type="button"
                    onClick={() => setShowGoalsModal(true)}
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#ccd1ca] bg-white text-xs font-semibold text-[#18221d] rounded hover:bg-[#f2f1ea] transition-colors shadow-2xs"
                >
                    <Target className="w-3.5 h-3.5 text-emerald-800" />
                    Definir Metas Operacionales
                </button>
            }
        >
            <Head title="Reportes de Desempeño por Equipo" />

            <div className="space-y-6">
                {/* OPERATIONAL KPI GOALS BANNER */}
                <div className="bg-[#fafaf8] border border-[#ccd1ca] rounded-lg p-4 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <div className="w-8 h-8 rounded bg-emerald-950/10 border border-emerald-900/20 flex items-center justify-center text-emerald-900 shrink-0 mt-0.5">
                            <Target className="w-4 h-4" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-bold uppercase tracking-wider text-[#18221d]">
                                    Metas Operacionales Activas (Globales)
                                </span>
                                <span className="text-[10px] bg-emerald-100 text-emerald-800 font-semibold px-1.5 py-0.5 rounded">
                                    Vigentes
                                </span>
                            </div>
                            <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-[#334139]">
                                <span>
                                    <strong>NPS:</strong> &ge; {kpiGoals?.nps?.target_value !== undefined ? (kpiGoals.nps.target_value > 0 ? `+${kpiGoals.nps.target_value.toFixed(2)}` : kpiGoals.nps.target_value.toFixed(2)) : '+0.50'} 
                                    <span className="text-[#687169]"> ({kpiGoals?.nps?.target_percentage ?? 50}%)</span>
                                </span>
                                <span className="text-[#ccd1ca]">&bull;</span>
                                <span>
                                    <strong>CSAT:</strong> &ge; {kpiGoals?.csat?.target_percentage ?? 80}%
                                </span>
                                <span className="text-[#ccd1ca]">&bull;</span>
                                <span>
                                    <strong>Profesionalismo:</strong> &ge; {kpiGoals?.professionalism?.target_percentage ?? 85}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={() => setShowGoalsModal(true)}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#ccd1ca] text-xs font-semibold text-[#18221d] rounded hover:bg-[#f2f1ea] transition-colors shadow-2xs shrink-0"
                    >
                        <SlidersHorizontal className="w-3.5 h-3.5 text-emerald-800" />
                        Configurar Metas Globales
                    </button>
                </div>

                {/* STUDIO: CONFIGURATION & GENERATOR */}
                <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-sm p-6 space-y-6">
                    <div className="flex items-center justify-between border-b border-[#ccd1ca] pb-4">
                        <div className="flex items-center gap-2.5">
                            <div className="w-8 h-8 rounded bg-[#18221d] flex items-center justify-center text-[#d7f45b]">
                                <Sparkles className="w-4 h-4" />
                            </div>
                            <div>
                                <h2 className="text-base font-serif font-bold text-[#18221d]">
                                    Estudio de Emisión y Síntesis IA
                                </h2>
                                <p className="text-xs text-[#687169]">
                                    Configure el corte temporal, equipos y parámetros de inclusión para generar PDFs oficiales con hash inmutable.
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            onClick={handleSelectAllTeams}
                            className="text-xs font-semibold text-[#18221d] hover:underline"
                        >
                            {genData.team_ids.length === teams.length ? 'Deseleccionar todos' : 'Seleccionar todos los equipos'}
                        </button>
                    </div>

                    <form onSubmit={handleGenerate} className="space-y-6">
                        {/* Team Picker Badges */}
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d]">
                                    Equipos de Supervisores a Incluir ({genData.team_ids.length} seleccionados) <span className="text-red-600">*</span>
                                </label>
                                <Link
                                    href="/teams"
                                    className="text-xs text-[#18221d] font-semibold hover:underline flex items-center gap-1"
                                >
                                    <Users className="w-3.5 h-3.5" />
                                    Definir / Administrar Equipos
                                </Link>
                            </div>

                            {teams.length === 0 ? (
                                <div className="p-6 bg-[#fafaf8] border border-dashed border-[#ccd1ca] rounded text-center text-xs text-[#687169] space-y-2">
                                    <p className="font-semibold text-[#18221d]">No hay equipos activos disponibles.</p>
                                    <p>Puede definir nuevos equipos o sincronizarlos automáticamente desde los registros de encuestas.</p>
                                    <Link
                                        href="/teams"
                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#18221d] text-white text-xs font-semibold rounded"
                                    >
                                        Ir a Definición de Equipos
                                    </Link>
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 max-h-48 overflow-y-auto p-2 border border-[#ccd1ca] rounded bg-[#fafaf8]">
                                    {teams.map((t) => {
                                        const isSelected = genData.team_ids.includes(t.id);
                                        return (
                                            <div
                                                key={t.id}
                                                onClick={() => handleTeamCheckbox(t.id)}
                                                className={`p-2.5 rounded border text-xs cursor-pointer transition-all flex flex-col justify-between ${
                                                    isSelected 
                                                        ? 'bg-[#18221d] text-white border-[#18221d] shadow-xs' 
                                                        : 'bg-white border-[#ccd1ca] text-[#334139] hover:bg-[#f2f1ea]'
                                                }`}
                                            >
                                                <div className="font-bold flex items-center justify-between">
                                                    <span>{t.name}</span>
                                                    {isSelected && <Check className="w-3.5 h-3.5 text-[#d7f45b]" />}
                                                </div>
                                                <div className={`text-[10px] mt-1 ${isSelected ? 'text-[#cbd5e1]' : 'text-[#687169]'}`}>
                                                    Sup: {t.supervisor?.name || 'No asignado'} ({t.code})
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Date Range & Cutoff */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Fecha Inicio <span className="text-red-600">*</span>
                                </label>
                                <input
                                    type="date"
                                    value={genData.period_from}
                                    onChange={(e) => setGenData('period_from', e.target.value)}
                                    className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm bg-white"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Fecha Fin <span className="text-red-600">*</span>
                                </label>
                                <input
                                    type="date"
                                    value={genData.period_to}
                                    onChange={(e) => setGenData('period_to', e.target.value)}
                                    className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm bg-white"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Fecha de Corte
                                </label>
                                <input
                                    type="date"
                                    value={genData.cutoff_date}
                                    onChange={(e) => setGenData('cutoff_date', e.target.value)}
                                    className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm bg-white"
                                />
                            </div>
                        </div>

                        {/* Options Checks */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-[#ccd1ca]">
                            <label className="flex items-center gap-2.5 text-xs text-[#18221d] cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={genData.compare_previous_period}
                                    onChange={(e) => setGenData('compare_previous_period', e.target.checked)}
                                    className="rounded border-[#ccd1ca] text-[#18221d] focus:ring-0"
                                />
                                <span>Comparar con período anterior</span>
                            </label>

                            <label className="flex items-center gap-2.5 text-xs text-[#18221d] cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={genData.include_verbatims}
                                    onChange={(e) => setGenData('include_verbatims', e.target.checked)}
                                    className="rounded border-[#ccd1ca] text-[#18221d] focus:ring-0"
                                />
                                <span>Desglose de motivos / verbatims</span>
                            </label>

                            <label className="flex items-center gap-2.5 text-xs text-[#18221d] cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={genData.include_open_cases}
                                    onChange={(e) => setGenData('include_open_cases', e.target.checked)}
                                    className="rounded border-[#ccd1ca] text-[#18221d] focus:ring-0"
                                />
                                    <span>Listar casos abiertos (sin datos disciplinarios)</span>
                            </label>
                        </div>

                        {/* Optional Batch Goals Override */}
                        <div className="pt-3 border-t border-[#ccd1ca] space-y-3">
                            <div className="flex items-center justify-between">
                                <label className="flex items-center gap-2 text-xs font-bold text-[#18221d] cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={customizeBatchGoals}
                                        onChange={(e) => setCustomizeBatchGoals(e.target.checked)}
                                        className="rounded border-[#ccd1ca] text-[#18221d] focus:ring-0"
                                    />
                                    <span className="flex items-center gap-1.5">
                                        <Target className="w-3.5 h-3.5 text-emerald-800" />
                                        Personalizar metas para este lote de generación
                                    </span>
                                </label>
                                {customizeBatchGoals && (
                                    <span className="text-[10px] text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded font-medium">
                                        Sobrescribirá temporalmente las metas globales en estos reportes
                                    </span>
                                )}
                            </div>

                            {customizeBatchGoals && (
                                <div className="p-3.5 bg-[#fafaf8] border border-[#ccd1ca] rounded-md grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                                    {/* NPS */}
                                    <div className="space-y-1.5 bg-white p-2.5 rounded border border-[#ccd1ca]">
                                        <div className="font-bold text-[#18221d] flex items-center justify-between">
                                            <span>NPS (%)</span>
                                            <span className="text-[10px] font-mono text-[#687169]">
                                                Meta: {Number(batchGoals.nps.target) >= 0 ? `+${batchGoals.nps.target}` : batchGoals.nps.target}%
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div>
                                                <label className="text-[10px] text-[#687169] block">Meta %</label>
                                                <input
                                                    type="number"
                                                    step="1"
                                                    value={batchGoals.nps.target}
                                                    onChange={(e) => setBatchGoals({
                                                        ...batchGoals,
                                                        nps: { ...batchGoals.nps, target: Number(e.target.value) }
                                                    })}
                                                    className="w-full px-2 py-1 text-xs border border-[#ccd1ca] rounded bg-white"
                                                />
                                            </div>
                                            <div>
                                                <label className="text-[10px] text-[#687169] block">Alerta %</label>
                                                <input
                                                    type="number"
                                                    step="1"
                                                    value={batchGoals.nps.warning}
                                                    onChange={(e) => setBatchGoals({
                                                        ...batchGoals,
                                                        nps: { ...batchGoals.nps, warning: Number(e.target.value) }
                                                    })}
                                                    className="w-full px-2 py-1 text-xs border border-[#ccd1ca] rounded bg-white"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {/* CSAT */}
                                    <div className="space-y-1.5 bg-white p-2.5 rounded border border-[#ccd1ca]">
                                        <div className="font-bold text-[#18221d] flex items-center justify-between">
                                            <span>CSAT (%)</span>
                                            <span className="text-[10px] font-mono text-[#687169]">
                                                Meta: {batchGoals.csat.target}%
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div>
                                                <label className="text-[10px] text-[#687169] block">Meta %</label>
                                                <input
                                                    type="number"
                                                    step="1"
                                                    value={batchGoals.csat.target}
                                                    onChange={(e) => setBatchGoals({
                                                        ...batchGoals,
                                                        csat: { ...batchGoals.csat, target: Number(e.target.value) }
                                                    })}
                                                    className="w-full px-2 py-1 text-xs border border-[#ccd1ca] rounded bg-white"
                                                />
                                            </div>
                                            <div>
                                                <label className="text-[10px] text-[#687169] block">Alerta %</label>
                                                <input
                                                    type="number"
                                                    step="1"
                                                    value={batchGoals.csat.warning}
                                                    onChange={(e) => setBatchGoals({
                                                        ...batchGoals,
                                                        csat: { ...batchGoals.csat, warning: Number(e.target.value) }
                                                    })}
                                                    className="w-full px-2 py-1 text-xs border border-[#ccd1ca] rounded bg-white"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {/* Profesionalismo */}
                                    <div className="space-y-1.5 bg-white p-2.5 rounded border border-[#ccd1ca]">
                                        <div className="font-bold text-[#18221d] flex items-center justify-between">
                                            <span>Profesionalismo (%)</span>
                                            <span className="text-[10px] font-mono text-[#687169]">
                                                Meta: {batchGoals.professionalism.target}%
                                            </span>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div>
                                                <label className="text-[10px] text-[#687169] block">Meta %</label>
                                                <input
                                                    type="number"
                                                    step="1"
                                                    value={batchGoals.professionalism.target}
                                                    onChange={(e) => setBatchGoals({
                                                        ...batchGoals,
                                                        professionalism: { ...batchGoals.professionalism, target: Number(e.target.value) }
                                                    })}
                                                    className="w-full px-2 py-1 text-xs border border-[#ccd1ca] rounded bg-white"
                                                />
                                            </div>
                                            <div>
                                                <label className="text-[10px] text-[#687169] block">Alerta %</label>
                                                <input
                                                    type="number"
                                                    step="1"
                                                    value={batchGoals.professionalism.warning}
                                                    onChange={(e) => setBatchGoals({
                                                        ...batchGoals,
                                                        professionalism: { ...batchGoals.professionalism, warning: Number(e.target.value) }
                                                    })}
                                                    className="w-full px-2 py-1 text-xs border border-[#ccd1ca] rounded bg-white"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Action Buttons */}
                        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t border-[#ccd1ca]">
                            <button
                                type="button"
                                onClick={handleFetchPreview}
                                disabled={previewLoading || genData.team_ids.length === 0}
                                className="inline-flex items-center gap-1.5 px-4 py-2 border border-[#ccd1ca] bg-white text-xs font-semibold rounded hover:bg-[#f2f1ea] transition-colors disabled:opacity-50"
                            >
                                <Eye className="w-3.5 h-3.5" />
                                {previewLoading ? 'Calculando Vista Previa...' : 'Vista Previa Rápida'}
                            </button>

                            <button
                                type="submit"
                                disabled={generating || genData.team_ids.length === 0}
                                className="inline-flex items-center gap-2 px-6 py-2.5 bg-[#18221d] text-white text-xs font-bold uppercase tracking-wider rounded shadow-sm hover:bg-[#283830] transition-colors disabled:opacity-50"
                            >
                                <Sparkles className="w-4 h-4 text-[#d7f45b]" />
                                {generating ? 'Encolando Reportes...' : `Generar ${genData.team_ids.length} Reporte(s) PDF con IA`}
                            </button>
                        </div>
                    </form>

                    {/* LIVE PREVIEW BOX */}
                    {previewData && (
                        <div className="mt-6 p-5 bg-[#fafaf8] border-2 border-dashed border-[#ccd1ca] rounded-lg space-y-4">
                            <div className="flex items-center justify-between border-b border-[#ccd1ca] pb-3">
                                <div>
                                    <span className="text-[10px] uppercase font-bold text-[#687169]">Vista Previa Rápida</span>
                                    <h3 className="text-sm font-bold text-[#18221d]">
                                        {previewData.team.name} ({previewData.team.code}) · Sup: {previewData.team.supervisor_name}
                                    </h3>
                                </div>
                                <span className="font-mono text-[11px] text-[#687169]">
                                    Hash: {previewData.data_version.slice(0, 16)}...
                                </span>
                            </div>

                            {previewData.is_low_sample && (
                                <div className="p-2.5 bg-amber-50 border border-amber-200 text-amber-900 rounded text-xs flex items-center gap-2">
                                    <AlertTriangle className="w-4 h-4 text-amber-700" />
                                    Muestra limitada ({previewData.metrics.survey_volume} encuestas). Se activará la advertencia de cautela en el PDF.
                                </div>
                            )}

                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                                <div className="p-3 bg-white border border-[#ccd1ca] rounded">
                                    <div className="text-[10px] text-[#687169] uppercase font-bold">Muestra</div>
                                    <div className="text-xl font-bold text-[#18221d] mt-0.5">{previewData.metrics.survey_volume}</div>
                                </div>

                                <div className="p-3 bg-white border border-[#ccd1ca] rounded">
                                    <div className="text-[10px] text-[#687169] uppercase font-bold">NPS Score</div>
                                    <div className="text-xl font-bold text-[#18221d] mt-0.5 font-mono">
                                        {previewData.metrics.nps_score !== null ? (previewData.metrics.nps_score > 0 ? `+${previewData.metrics.nps_score.toFixed(2)}` : previewData.metrics.nps_score.toFixed(2)) : 'N/D'}
                                    </div>
                                    <div className="text-[9px] text-[#687169]">
                                        Meta: {previewData.goals?.nps?.target_value !== undefined 
                                            ? (previewData.goals.nps.target_value > 0 ? `+${previewData.goals.nps.target_value.toFixed(2)}` : previewData.goals.nps.target_value.toFixed(2)) 
                                            : (previewData.metrics.goals_comparison?.nps?.target_value !== undefined 
                                                ? (previewData.metrics.goals_comparison.nps.target_value > 0 ? `+${previewData.metrics.goals_comparison.nps.target_value.toFixed(2)}` : previewData.metrics.goals_comparison.nps.target_value.toFixed(2)) 
                                                : '+0.50')}
                                    </div>
                                </div>

                                <div className="p-3 bg-white border border-[#ccd1ca] rounded">
                                    <div className="text-[10px] text-[#687169] uppercase font-bold">CSAT</div>
                                    <div className="text-xl font-bold text-[#18221d] mt-0.5 font-mono">
                                        {previewData.metrics.csat_score !== null ? `${(previewData.metrics.csat_score * 100).toFixed(1)}%` : 'N/D'}
                                    </div>
                                    <div className="text-[9px] text-[#687169]">
                                        Meta: {previewData.goals?.csat?.target_value !== undefined 
                                            ? `${(previewData.goals.csat.target_value * 100).toFixed(1)}%` 
                                            : (previewData.metrics.goals_comparison?.csat?.target_value !== undefined 
                                                ? `${(previewData.metrics.goals_comparison.csat.target_value * 100).toFixed(1)}%` 
                                                : '80.0%')}
                                    </div>
                                </div>

                                <div className="p-3 bg-white border border-[#ccd1ca] rounded">
                                    <div className="text-[10px] text-[#687169] uppercase font-bold">Profesionalismo</div>
                                    <div className="text-xl font-bold text-[#18221d] mt-0.5 font-mono">
                                        {previewData.metrics.professionalism_score !== null ? `${(previewData.metrics.professionalism_score * 100).toFixed(1)}%` : 'N/D'}
                                    </div>
                                    <div className="text-[9px] text-[#687169]">
                                        Meta: {previewData.goals?.professionalism?.target_value !== undefined 
                                            ? `${(previewData.goals.professionalism.target_value * 100).toFixed(1)}%` 
                                            : (previewData.metrics.goals_comparison?.professionalism?.target_value !== undefined 
                                                ? `${(previewData.metrics.goals_comparison.professionalism.target_value * 100).toFixed(1)}%` 
                                                : '85.0%')}
                                    </div>
                                </div>
                            </div>

                            <div className="text-xs text-[#687169] flex justify-between">
                                <span>Agentes evaluados: {previewData.agent_reviews.length}</span>
                                <span>Casos abiertos vigentes: {previewData.open_cases.length}</span>
                            </div>
                        </div>
                    )}
                </div>

                {/* HISTORICAL GENERATED REPORTS ARCHIVE */}
                <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-sm space-y-4 p-6">
                    <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 border-b border-[#ccd1ca] pb-4">
                        <div>
                            <h2 className="text-base font-serif font-bold text-[#18221d]">
                                Archivo de Reportes Generados
                            </h2>
                            <p className="text-xs text-[#687169]">
                                Histórico inmutable de PDFs emitidos con firma SHA-256 y descarga individual o comprimida.
                            </p>
                        </div>

                        {selectedReportIds.length > 0 && (
                            <button
                                onClick={handleDownloadZip}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#18221d] text-white text-xs font-semibold rounded hover:bg-[#283830] transition-colors shadow-xs"
                            >
                                <Archive className="w-3.5 h-3.5 text-[#d7f45b]" />
                                Descargar {selectedReportIds.length} Reporte(s) en ZIP
                            </button>
                        )}
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr className="border-b border-[#ccd1ca] bg-[#f2f1ea] text-[#687169] uppercase font-semibold">
                                    <th className="py-2.5 px-4 w-8">
                                        <input
                                            type="checkbox"
                                            onChange={handleSelectAllReports}
                                            checked={selectedReportIds.length > 0 && selectedReportIds.length === reports.data.filter((r) => r.status === 'completed').length}
                                            className="rounded border-[#ccd1ca] text-[#18221d]"
                                        />
                                    </th>
                                    <th className="py-2.5 px-4">Equipo / Supervisor</th>
                                    <th className="py-2.5 px-4">Período Evaluado</th>
                                    <th className="py-2.5 px-4">Data Version (Hash)</th>
                                    <th className="py-2.5 px-4 text-center">Estado</th>
                                    <th className="py-2.5 px-4 text-center">Tamaño</th>
                                    <th className="py-2.5 px-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#ecebe4]">
                                {reports.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="py-10 text-center text-[#687169]">
                                            No se han generado reportes de equipo aún. Utilice el generador superior.
                                        </td>
                                    </tr>
                                ) : (
                                    reports.data.map((rep) => {
                                        const isCompleted = rep.status === 'completed';
                                        return (
                                            <tr key={rep.id} className="hover:bg-[#fafaf8] transition-colors">
                                                <td className="py-3 px-4">
                                                    {isCompleted && (
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedReportIds.includes(rep.id)}
                                                            onChange={() => toggleReportSelection(rep.id)}
                                                            className="rounded border-[#ccd1ca] text-[#18221d]"
                                                        />
                                                    )}
                                                </td>
                                                <td className="py-3 px-4">
                                                    <div className="font-bold text-[#18221d]">{rep.team.name}</div>
                                                    <div className="text-[10px] text-[#687169]">
                                                        Sup: {rep.team.supervisor?.name || 'No asignado'} ({rep.team.code})
                                                    </div>
                                                </td>
                                                <td className="py-3 px-4">
                                                    <div className="text-[#18221d]">{rep.period_from?.split('T')[0]} al {rep.period_to?.split('T')[0]}</div>
                                                    <div className="text-[10px] text-[#687169]">Corte: {rep.cutoff_date?.split('T')[0]}</div>
                                                </td>
                                                <td className="py-3 px-4 font-mono text-[10px] text-[#687169]">
                                                    {rep.data_version ? `${rep.data_version.slice(0, 16)}...` : 'Pendiente'}
                                                </td>
                                                <td className="py-3 px-4 text-center">
                                                    <div className="flex flex-col items-center gap-1">
                                                        <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                                                            rep.status === 'completed'
                                                                ? 'bg-emerald-100 text-emerald-900 border border-emerald-300'
                                                                : (rep.status === 'processing'
                                                                    ? 'bg-blue-100 text-blue-900 border border-blue-300'
                                                                    : (rep.status === 'failed' ? 'bg-red-100 text-red-900' : 'bg-amber-100 text-amber-900'))
                                                        }`}>
                                                            {rep.status === 'completed' 
                                                                ? 'Completado' 
                                                                : (rep.status === 'processing' 
                                                                    ? `Procesando (${rep.progress || 0}%)` 
                                                                    : (rep.status === 'failed' ? 'Error' : `En cola (${rep.progress || 0}%)`))}
                                                        </span>
                                                        {rep.status !== 'completed' && rep.stage && (
                                                            <span className="text-[9px] text-[#687169] max-w-[150px] truncate" title={rep.stage}>
                                                                {rep.stage}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-4 text-center text-[#687169]">
                                                    {rep.file_size ? `${(rep.file_size / 1024).toFixed(0)} KB` : '—'}
                                                </td>
                                                <td className="py-3 px-4 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Link
                                                            href={`/reports/teams/${rep.id}`}
                                                            className="inline-flex items-center gap-1 text-[11px] font-bold text-[#18221d] hover:underline"
                                                        >
                                                            Ver <ChevronRight className="w-3.5 h-3.5" />
                                                        </Link>

                                                        {isCompleted && (
                                                            <a
                                                                href={`/reports/teams/${rep.id}/download`}
                                                                className="inline-flex items-center gap-1 px-2.5 py-1 bg-[#f2f1ea] border border-[#ccd1ca] rounded text-[11px] font-semibold text-[#18221d] hover:bg-[#18221d] hover:text-white transition-colors"
                                                            >
                                                                <Download className="w-3 h-3" />
                                                                PDF
                                                            </a>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {reports.last_page > 1 && (
                        <div className="pt-3 border-t border-[#ccd1ca] flex items-center justify-between text-xs text-[#687169]">
                            <div>
                                Página {reports.current_page} de {reports.last_page} ({reports.total} reportes)
                            </div>
                            <div className="flex gap-1">
                                {reports.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1 rounded border text-xs ${
                                            link.active
                                                ? 'bg-[#18221d] text-white border-[#18221d]'
                                                : (!link.url ? 'text-gray-400 border-transparent cursor-default' : 'bg-white border-[#ccd1ca] hover:bg-[#f2f1ea] text-[#18221d]')
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* KPI GOALS MODAL */}
            <KpiGoalsModal
                isOpen={showGoalsModal}
                onClose={() => setShowGoalsModal(false)}
                initialGoals={kpiGoals}
                onGoalsSaved={(saved) => {
                    setKpiGoals(saved);
                    setBatchGoals({
                        nps: { 
                            target: saved.nps?.target_percentage ?? 50, 
                            warning: saved.nps?.warning_percentage ?? 20 
                        },
                        csat: { 
                            target: saved.csat?.target_percentage ?? 80, 
                            warning: saved.csat?.warning_percentage ?? 70 
                        },
                        professionalism: { 
                            target: saved.professionalism?.target_percentage ?? 85, 
                            warning: saved.professionalism?.warning_percentage ?? 75 
                        },
                    });
                }}
            />
        </AppLayout>
    );
}
