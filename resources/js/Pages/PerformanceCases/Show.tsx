import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { 
    ArrowLeft, Plus, RefreshCw, CheckCircle2, AlertTriangle, Shield, 
    Lock, Eye, Calendar, User, FileText, ChevronRight, TrendingUp,
    Clock, Check, X, ShieldAlert, BarChart3, History
} from 'lucide-react';

interface WorkforceMember {
    id: number;
    name: string;
    role: string;
    external_id?: string;
}

interface UpdateItem {
    id: number;
    session_date: string;
    type: string;
    summary: string;
    agreements?: string;
    action_items?: string[];
    created_by?: {
        name: string;
    };
    created_at: string;
    metrics_snapshot?: {
        survey_volume?: number;
        nps_score?: number | null;
        csat_score?: number | null;
        professionalism_score?: number | null;
    };
    disciplinary_details?: string;
}

interface DailyResult {
    date: string;
    has_sample: boolean;
    survey_volume: number;
    nps_score: number | null;
    csat_score: number | null;
    professionalism_score: number | null;
}

interface Recalculation {
    id: number;
    version_number: number;
    period_from: string;
    period_to: string;
    survey_volume: number;
    nps_score: number | null;
    csat_score: number | null;
    professionalism_score: number | null;
    recalculated_at: string;
    daily_results?: DailyResult[];
}

interface CaseItem {
    id: number;
    case_number: string;
    workforce_member: WorkforceMember;
    assigned_to?: { id: number; name: string };
    opened_by?: { id: number; name: string };
    closed_by?: { id: number; name: string };
    type: string;
    priority: string;
    status: string;
    reason: string;
    opened_at: string;
    next_review_at?: string;
    closed_at?: string;
    closure_reason?: string;
    baseline_metrics?: {
        nps_score?: number | null;
        csat_score?: number | null;
        professionalism_score?: number | null;
        survey_volume?: number;
    };
    latest_recalculation?: Recalculation;
    updates?: UpdateItem[];
    recalculations?: Recalculation[];
}

interface Props {
    caseItem?: CaseItem;
    performanceCase?: CaseItem;
    availableVersions?: Array<{
        id: number;
        version_number: number;
        recalculated_at?: string;
        reason?: string;
        survey_volume?: number;
        nps_score?: number | null;
        csat_score?: number | null;
    }>;
    canViewDisciplinary?: boolean;
    can_view_disciplinary?: boolean;
    canUpdate?: boolean;
    can_update?: boolean;
    canClose?: boolean;
    can_close?: boolean;
}

function formatScore(val: number | null | undefined, decimals = 2, prefixPlus = false): string {
    if (typeof val !== 'number' || isNaN(val)) {
        return '—';
    }
    const formatted = val.toFixed(decimals);
    return (prefixPlus && val > 0) ? `+${formatted}` : formatted;
}

function formatPercentage(val: number | null | undefined, decimals = 0): string {
    if (typeof val !== 'number' || isNaN(val)) {
        return '—';
    }
    return `${(val * 100).toFixed(decimals)}%`;
}

export default function PerformanceCaseShow(props: Props) {
    const caseItem = props.caseItem || props.performanceCase;
    const canViewDisciplinary = Boolean(props.canViewDisciplinary || props.can_view_disciplinary);
    const canUpdate = Boolean(props.canUpdate || props.can_update);
    const canClose = Boolean(props.canClose || props.can_close);

    if (!caseItem) {
        return (
            <AppLayout title="Caso no encontrado">
                <div className="p-8 text-center text-[#687169]">Cargando expediente o caso no encontrado...</div>
            </AppLayout>
        );
    }

    const [activeTab, setActiveTab] = useState<'timeline' | 'daily' | 'versions'>('timeline');
    const [showUpdateModal, setShowUpdateModal] = useState(false);
    const [showCloseModal, setShowCloseModal] = useState(false);
    const [selectedVersion, setSelectedVersion] = useState<number>(
        caseItem.latest_recalculation?.version_number || (caseItem.recalculations?.[0]?.version_number ?? 1)
    );
    const [disciplinaryModalData, setDisciplinaryModalData] = useState<{ id: number; details: string | null; loading: boolean } | null>(null);
    const [recalculating, setRecalculating] = useState(false);

    // Version Comparison state
    const [compareVerA, setCompareVerA] = useState<number | null>(
        caseItem.recalculations && caseItem.recalculations.length >= 2 ? caseItem.recalculations[caseItem.recalculations.length - 1]?.version_number ?? 1 : null
    );
    const [compareVerB, setCompareVerB] = useState<number | null>(
        caseItem.latest_recalculation?.version_number ?? caseItem.recalculations?.[0]?.version_number ?? null
    );
    const [comparisonResult, setComparisonResult] = useState<any | null>(null);
    const [comparing, setComparing] = useState(false);
    const [comparisonError, setComparisonError] = useState<string | null>(null);

    const handleCompareVersions = async () => {
        if (!compareVerA || !compareVerB) return;
        setComparing(true);
        setComparisonError(null);
        try {
            const res = await fetch(`/performance-cases/${caseItem.id}/versions?version_a=${compareVerA}&version_b=${compareVerB}`);
            const data = await res.json();
            if (data.success) {
                setComparisonResult(data.comparison);
            } else {
                setComparisonError(data.error || 'Error al comparar versiones');
            }
        } catch (err: any) {
            setComparisonError(err?.message || 'Error de conexión al comparar versiones');
        } finally {
            setComparing(false);
        }
    };

    // Update Form
    const { data: updateData, setData: setUpdateData, post: postUpdate, processing: updating, reset: resetUpdate } = useForm({
        session_date: new Date().toISOString().split('T')[0],
        type: 'coaching',
        summary: '',
        agreements: '',
        action_items: '',
        disciplinary_details: '',
        next_review_at: caseItem.next_review_at || '',
    });

    // Close Form
    const { data: closeData, setData: setCloseData, post: postClose, processing: closing } = useForm({
        resolution_status: 'resolved',
        closure_reason: '',
    });

    const handleSaveUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        postUpdate(`/performance-cases/${caseItem.id}/updates`, {
            onSuccess: () => {
                setShowUpdateModal(false);
                resetUpdate();
            },
        });
    };

    const handleCloseCase = (e: React.FormEvent) => {
        e.preventDefault();
        router.put(`/performance-cases/${caseItem.id}/close`, closeData, {
            onSuccess: () => setShowCloseModal(false),
        });
    };

    const handleRecalculate = () => {
        setRecalculating(true);
        router.post(`/performance-cases/${caseItem.id}/recalculate`, {}, {
            onFinish: () => setRecalculating(false),
        });
    };

    const fetchDisciplinary = async (updateId: number) => {
        setDisciplinaryModalData({ id: updateId, details: null, loading: true });
        try {
            const res = await fetch(`/performance-cases/${caseItem.id}/updates/${updateId}/disciplinary`);
            const json = await res.json();
            setDisciplinaryModalData({ id: updateId, details: json.disciplinary_details, loading: false });
        } catch {
            setDisciplinaryModalData({ id: updateId, details: 'Error al recuperar información confidencial.', loading: false });
        }
    };

    const activeRecalculation = caseItem.recalculations?.find((r) => r.version_number === selectedVersion) || caseItem.latest_recalculation;

    return (
        <AppLayout
            title={`Caso ${caseItem.case_number}`}
            kicker="HERRAMIENTA 004 · EXPEDIENTE DE SEGUIMIENTO"
            description={`Acompañamiento y evolución de ${caseItem.workforce_member.name} (${caseItem.workforce_member.role.toUpperCase()})`}
            actions={
                <div className="flex items-center gap-2">
                    <Link
                        href="/performance-cases"
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#ccd1ca] bg-white text-xs font-semibold text-[#18221d] rounded hover:bg-[#f2f1ea] transition-colors"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" />
                        Volver
                    </Link>

                    {canUpdate && caseItem.status !== 'closed' && (
                        <>
                            <button
                                onClick={handleRecalculate}
                                disabled={recalculating}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#ccd1ca] bg-white text-xs font-semibold text-[#18221d] rounded hover:bg-[#f2f1ea] transition-colors disabled:opacity-50"
                            >
                                <RefreshCw className={`w-3.5 h-3.5 ${recalculating ? 'animate-spin' : ''}`} />
                                {recalculating ? 'Recalculando...' : 'Recalcular Métricas'}
                            </button>

                            <button
                                onClick={() => setShowUpdateModal(true)}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider rounded shadow-sm hover:bg-[#283830] transition-colors"
                            >
                                <Plus className="w-3.5 h-3.5" />
                                Registrar Sesión
                            </button>
                        </>
                    )}

                    {canClose && caseItem.status !== 'closed' && (
                        <button
                            onClick={() => setShowCloseModal(true)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-800 text-white text-xs font-semibold uppercase tracking-wider rounded hover:bg-emerald-900 transition-colors"
                        >
                            <CheckCircle2 className="w-3.5 h-3.5" />
                            Cerrar Caso
                        </button>
                    )}
                </div>
            }
        >
            <Head title={`Caso ${caseItem.case_number} - ${caseItem.workforce_member.name}`} />

            <div className="space-y-6">
                {/* Header Information Grid */}
                <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-sm p-6">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 pb-5 border-b border-[#ccd1ca]">
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-xl font-serif font-bold text-[#18221d]">{caseItem.case_number}</h1>
                                <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider ${
                                    caseItem.status === 'open' 
                                        ? 'bg-amber-100 text-amber-900 border border-amber-300' 
                                        : (caseItem.status === 'under_review' 
                                            ? 'bg-blue-100 text-blue-900 border border-blue-300' 
                                            : (caseItem.status === 'resolved' 
                                                ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' 
                                                : 'bg-gray-100 text-gray-800 border border-gray-300'))
                                }`}>
                                    {caseItem.status.replace('_', ' ')}
                                </span>
                                <span className={`text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded ${
                                    caseItem.priority === 'high' ? 'bg-red-100 text-red-800' : (caseItem.priority === 'medium' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800')
                                }`}>
                                    Prioridad {caseItem.priority}
                                </span>
                            </div>
                            <p className="text-xs text-[#687169] mt-1">
                                Apertura: {caseItem.opened_at} · Responsable: {caseItem.assigned_to?.name || 'Sin asignar'}
                                {caseItem.next_review_at && ` · Próxima revisión: ${caseItem.next_review_at}`}
                            </p>
                        </div>

                        <div className="text-right">
                            <div className="text-xs font-bold text-[#18221d]">{caseItem.workforce_member.name}</div>
                            <div className="text-[11px] text-[#687169] uppercase font-semibold">
                                {caseItem.workforce_member.role} {caseItem.workforce_member.external_id ? `· BMS ${caseItem.workforce_member.external_id}` : ''}
                            </div>
                        </div>
                    </div>

                    {/* Reason box */}
                    <div className="mt-4 p-3 bg-[#f7f6f1] rounded border border-[#e4e2d8] text-xs text-[#334139]">
                        <strong className="text-[#18221d] uppercase tracking-wider text-[10px]">Motivo de Apertura:</strong> {caseItem.reason}
                    </div>

                    {/* Baseline vs Current Metrics comparison */}
                    <div className="mt-5 grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div className="p-3 bg-[#fafaf8] border border-[#ccd1ca] rounded">
                            <div className="text-[10px] uppercase font-bold text-[#687169]">Volumen Encuestas</div>
                            <div className="text-xl font-bold text-[#18221d] mt-1">
                                {caseItem.latest_recalculation?.survey_volume ?? caseItem.baseline_metrics?.survey_volume ?? 0}
                            </div>
                            <div className="text-[10px] text-[#687169]">
                                Línea base: {caseItem.baseline_metrics?.survey_volume ?? 0}
                            </div>
                        </div>

                        <div className="p-3 bg-[#fafaf8] border border-[#ccd1ca] rounded">
                            <div className="text-[10px] uppercase font-bold text-[#687169]">NPS</div>
                            <div className="text-xl font-bold text-[#18221d] mt-1 font-mono">
                                {formatScore(caseItem.latest_recalculation?.nps_score ?? caseItem.baseline_metrics?.nps_score, 2, true)}
                            </div>
                            <div className="text-[10px] text-[#687169]">
                                Línea base: {formatScore(caseItem.baseline_metrics?.nps_score, 2, true)}
                            </div>
                        </div>

                        <div className="p-3 bg-[#fafaf8] border border-[#ccd1ca] rounded">
                            <div className="text-[10px] uppercase font-bold text-[#687169]">CSAT (Satisfacción)</div>
                            <div className="text-xl font-bold text-[#18221d] mt-1 font-mono">
                                {formatPercentage(caseItem.latest_recalculation?.csat_score ?? caseItem.baseline_metrics?.csat_score, 1)}
                            </div>
                            <div className="text-[10px] text-[#687169]">
                                Línea base: {formatPercentage(caseItem.baseline_metrics?.csat_score, 1)}
                            </div>
                        </div>

                        <div className="p-3 bg-[#fafaf8] border border-[#ccd1ca] rounded">
                            <div className="text-[10px] uppercase font-bold text-[#687169]">Profesionalismo</div>
                            <div className="text-xl font-bold text-[#18221d] mt-1 font-mono">
                                {formatPercentage(caseItem.latest_recalculation?.professionalism_score ?? caseItem.baseline_metrics?.professionalism_score, 1)}
                            </div>
                            <div className="text-[10px] text-[#687169]">
                                Línea base: {formatPercentage(caseItem.baseline_metrics?.professionalism_score, 1)}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Tabs: Timeline vs Daily Results vs Version Recalculations */}
                <div className="border-b border-[#ccd1ca] flex gap-6 text-sm font-semibold text-[#687169]">
                    <button
                        onClick={() => setActiveTab('timeline')}
                        className={`pb-2.5 transition-colors border-b-2 flex items-center gap-1.5 ${
                            activeTab === 'timeline'
                                ? 'border-[#18221d] text-[#18221d]'
                                : 'border-transparent hover:text-[#18221d]'
                        }`}
                    >
                        <Clock className="w-4 h-4" />
                        Historial de Sesiones y Acuerdos ({caseItem.updates?.length || 0})
                    </button>

                    <button
                        onClick={() => setActiveTab('daily')}
                        className={`pb-2.5 transition-colors border-b-2 flex items-center gap-1.5 ${
                            activeTab === 'daily'
                                ? 'border-[#18221d] text-[#18221d]'
                                : 'border-transparent hover:text-[#18221d]'
                        }`}
                    >
                        <BarChart3 className="w-4 h-4" />
                        Resultados Diarios
                    </button>

                    <button
                        onClick={() => setActiveTab('versions')}
                        className={`pb-2.5 transition-colors border-b-2 flex items-center gap-1.5 ${
                            activeTab === 'versions'
                                ? 'border-[#18221d] text-[#18221d]'
                                : 'border-transparent hover:text-[#18221d]'
                        }`}
                    >
                        <History className="w-4 h-4" />
                        Versiones de Recálculo ({caseItem.recalculations?.length || 0})
                    </button>
                </div>

                {/* TAB 1: TIMELINE OF SESSIONS */}
                {activeTab === 'timeline' && (
                    <div className="space-y-4">
                        {(!caseItem.updates || caseItem.updates.length === 0) ? (
                            <div className="bg-white border border-[#ccd1ca] rounded-lg p-12 text-center text-[#687169]">
                                <FileText className="w-8 h-8 mx-auto text-[#ccd1ca] mb-2" />
                                <p className="text-sm font-semibold">No se han registrado sesiones aún.</p>
                                <p className="text-xs mt-1">Utilice el botón "Registrar Sesión" para documentar coaching, acuerdos o medidas disciplinarias.</p>
                            </div>
                        ) : (
                            caseItem.updates.map((up) => (
                                <div key={up.id} className="bg-white border border-[#ccd1ca] rounded-lg p-5 shadow-sm space-y-3">
                                    <div className="flex items-center justify-between border-b border-[#ecebe4] pb-3">
                                        <div className="flex items-center gap-2.5">
                                            <span className={`text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded ${
                                                up.type === 'disciplinary' ? 'bg-red-100 text-red-900 border border-red-300' : 'bg-[#edf0ee] text-[#18221d]'
                                            }`}>
                                                {up.type === 'coaching' ? 'Coaching 1 a 1' : (up.type === 'disciplinary' ? 'Medida Disciplinaria' : up.type.replace('_', ' '))}
                                            </span>
                                            <span className="text-xs font-semibold text-[#18221d]">
                                                {up.session_date}
                                            </span>
                                            <span className="text-xs text-[#687169]">
                                                por {up.created_by?.name || 'Sistema'}
                                            </span>
                                        </div>

                                        {up.metrics_snapshot && (
                                            <div className="hidden sm:flex items-center gap-3 text-[11px] font-mono text-[#334139] bg-[#f7f6f1] px-2.5 py-1 rounded border border-[#e4e2d8]">
                                                <span>Enc: <strong>{up.metrics_snapshot.survey_volume ?? 0}</strong></span>
                                                <span>NPS: <strong>{formatScore(up.metrics_snapshot.nps_score, 2, true)}</strong></span>
                                                <span>CSAT: <strong>{formatPercentage(up.metrics_snapshot.csat_score, 0)}</strong></span>
                                            </div>
                                        )}
                                    </div>

                                    <div>
                                        <div className="text-xs text-[#18221d] leading-relaxed whitespace-pre-line">{up.summary}</div>
                                    </div>

                                    {up.agreements && (
                                        <div className="p-3 bg-[#f2f7f3] border-l-3 border-[#16a34a] rounded text-xs text-[#14532d]">
                                            <strong className="block text-[10px] uppercase tracking-wider mb-0.5">Acuerdos y Compromisos:</strong>
                                            {up.agreements}
                                        </div>
                                    )}

                                    {/* Protected Disciplinary Trigger */}
                                    {up.type === 'disciplinary' && (
                                        <div className="mt-2 p-3 bg-red-50 border border-red-200 rounded flex items-center justify-between">
                                            <div className="flex items-center gap-2 text-xs text-red-900 font-semibold">
                                                <Lock className="w-4 h-4 text-red-700" />
                                                Información disciplinaria protegida con cifrado en reposo.
                                            </div>
                                            {canViewDisciplinary && (
                                                <button
                                                    onClick={() => fetchDisciplinary(up.id)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1 bg-white border border-red-300 text-red-900 text-xs font-bold rounded shadow-2xs hover:bg-red-100 transition-colors"
                                                >
                                                    <Eye className="w-3.5 h-3.5" />
                                                    Ver Detalle Cifrado
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ))
                        )}
                    </div>
                )}

                {/* TAB 2: DAILY RESULTS TABLE */}
                {activeTab === 'daily' && (
                    <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-sm overflow-hidden">
                        <div className="p-4 bg-[#f7f6f1] border-b border-[#ccd1ca] flex items-center justify-between">
                            <span className="text-xs font-bold text-[#18221d] uppercase tracking-wider">
                                Evolución diaria desde apertura ({caseItem.opened_at})
                            </span>
                            <span className="text-xs text-[#687169]">
                                Versión de cálculo activa: #{activeRecalculation?.version_number ?? 1}
                            </span>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr className="border-b border-[#ccd1ca] bg-[#f2f1ea] text-[#687169] uppercase font-semibold">
                                        <th className="py-2.5 px-4">Fecha</th>
                                        <th className="py-2.5 px-4 text-center">Encuestas</th>
                                        <th className="py-2.5 px-4 text-center">NPS</th>
                                        <th className="py-2.5 px-4 text-center">CSAT</th>
                                        <th className="py-2.5 px-4 text-center">Profesionalismo</th>
                                        <th className="py-2.5 px-4 text-center">Estado de Muestra</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#ecebe4]">
                                    {(!activeRecalculation?.daily_results || activeRecalculation.daily_results.length === 0) ? (
                                        <tr>
                                            <td colSpan={6} className="py-8 text-center text-[#687169]">
                                                No hay resultados diarios calculados para esta versión. Ejecute "Recalcular Métricas".
                                            </td>
                                        </tr>
                                    ) : (
                                        activeRecalculation.daily_results.map((day, idx) => (
                                            <tr key={idx} className={day.has_sample ? 'hover:bg-[#fafaf8]' : 'bg-[#fafafa] text-[#8c948e]'}>
                                                <td className="py-2 px-4 font-semibold">{day.date}</td>
                                                <td className="py-2 px-4 text-center font-mono">{day.survey_volume}</td>
                                                <td className="py-2 px-4 text-center font-mono">
                                                    {day.has_sample && typeof day.nps_score === 'number'
                                                        ? formatScore(day.nps_score, 2, true)
                                                        : <span className="text-gray-400 italic font-sans text-[11px]">Sin encuestas</span>}
                                                </td>
                                                <td className="py-2 px-4 text-center font-mono">
                                                    {day.has_sample && typeof day.csat_score === 'number'
                                                        ? formatPercentage(day.csat_score, 0)
                                                        : <span className="text-gray-400 italic font-sans text-[11px]">Sin encuestas</span>}
                                                </td>
                                                <td className="py-2 px-4 text-center font-mono">
                                                    {day.has_sample && typeof day.professionalism_score === 'number'
                                                        ? formatPercentage(day.professionalism_score, 0)
                                                        : <span className="text-gray-400 italic font-sans text-[11px]">Sin encuestas</span>}
                                                </td>
                                                <td className="py-2 px-4 text-center">
                                                    <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-semibold ${
                                                        day.has_sample ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'
                                                    }`}>
                                                        {day.has_sample ? 'Con Muestra' : 'Sin Encuestas (Null)'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* TAB 3: VERSIONS OF RECALCULATION */}
                {activeTab === 'versions' && (
                    <div className="space-y-6">
                        <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-sm p-5">
                            <div className="flex items-center justify-between mb-4 border-b border-[#ecebe4] pb-3">
                                <div>
                                    <h3 className="text-xs font-bold uppercase tracking-wider text-[#18221d]">
                                        Histórico Inmutable de Recálculos de Métricas
                                    </h3>
                                    <p className="text-xs text-[#687169] mt-0.5">
                                        Cada versión preserva el cálculo exacto según las encuestas asignadas en el período.
                                    </p>
                                </div>
                                <span className="text-xs font-mono text-[#687169] bg-[#f2f1ea] px-2.5 py-1 rounded border border-[#ccd1ca]">
                                    {caseItem.recalculations?.length || 0} versión(es)
                                </span>
                            </div>

                            {(!caseItem.recalculations || caseItem.recalculations.length === 0) ? (
                                <div className="text-center py-8 text-xs text-[#687169]">
                                    No hay versiones de recálculo registradas para este expediente.
                                </div>
                            ) : (
                                <div className="divide-y divide-[#ecebe4]">
                                    {caseItem.recalculations.map((recalc) => (
                                        <div key={recalc.id} className="py-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                            <div className="flex items-start gap-3">
                                                <span className="font-mono text-xs font-bold bg-[#18221d] text-white px-2.5 py-1 rounded shrink-0">
                                                    v{recalc.version_number}
                                                </span>
                                                <div className="space-y-0.5">
                                                    <div className="text-xs font-semibold text-[#18221d] flex items-center gap-2">
                                                        <span>
                                                            Período: {recalc.period_from && recalc.period_to ? `${recalc.period_from} al ${recalc.period_to}` : 'Desde apertura a la fecha'}
                                                        </span>
                                                        <span className="text-[10px] uppercase font-bold px-2 py-0.5 bg-[#f2f1ea] text-[#687169] rounded">
                                                            {recalc.reason === 'case_creation' ? 'Apertura' : (recalc.reason === 'manual' ? 'Recálculo manual' : recalc.reason)}
                                                        </span>
                                                    </div>
                                                    <div className="text-[11px] text-[#687169]">
                                                        Recalculado el: {recalc.recalculated_at ? new Date(recalc.recalculated_at).toLocaleString('es-ES') : '—'} · {recalc.survey_volume ?? 0} encuestas procesadas
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-4 text-xs font-mono">
                                                <div className="flex items-center gap-3 bg-[#fafaf8] border border-[#e4e2d8] px-3 py-1.5 rounded">
                                                    <span>NPS: <strong className="text-[#18221d]">{formatScore(recalc.nps_score, 2, true)}</strong></span>
                                                    <span className="text-[#ccd1ca]">|</span>
                                                    <span>CSAT: <strong className="text-[#18221d]">{formatPercentage(recalc.csat_score, 0)}</strong></span>
                                                    <span className="text-[#ccd1ca]">|</span>
                                                    <span>Prof: <strong className="text-[#18221d]">{formatPercentage(recalc.professionalism_score, 0)}</strong></span>
                                                </div>
                                                <button
                                                    onClick={() => {
                                                        setSelectedVersion(recalc.version_number);
                                                        setActiveTab('daily');
                                                    }}
                                                    className="px-3 py-1.5 text-xs font-bold border border-[#ccd1ca] rounded hover:bg-[#f2f1ea] transition-colors whitespace-nowrap"
                                                >
                                                    Ver Resultados Diarios
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Version Comparison Section */}
                        {caseItem.recalculations && caseItem.recalculations.length >= 2 && (
                            <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-sm p-5 space-y-4">
                                <div className="border-b border-[#ecebe4] pb-3">
                                    <h3 className="text-xs font-bold uppercase tracking-wider text-[#18221d]">
                                        Comparador de Versiones de Recálculo
                                    </h3>
                                    <p className="text-xs text-[#687169] mt-0.5">
                                        Identifique variaciones día a día en encuestas o métricas entre dos versiones del cálculo.
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center gap-3">
                                    <div className="flex items-center gap-2">
                                        <label className="text-xs font-semibold text-[#18221d]">Versión Base (A):</label>
                                        <select
                                            value={compareVerA ?? ''}
                                            onChange={(e) => setCompareVerA(Number(e.target.value))}
                                            className="px-2.5 py-1 text-xs border border-[#ccd1ca] rounded bg-white"
                                        >
                                            <option value="">Seleccione versión...</option>
                                            {caseItem.recalculations.map((r) => (
                                                <option key={r.id} value={r.version_number}>
                                                    v{r.version_number} ({r.reason === 'case_creation' ? 'Apertura' : r.reason})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <label className="text-xs font-semibold text-[#18221d]">Versión Comparada (B):</label>
                                        <select
                                            value={compareVerB ?? ''}
                                            onChange={(e) => setCompareVerB(Number(e.target.value))}
                                            className="px-2.5 py-1 text-xs border border-[#ccd1ca] rounded bg-white"
                                        >
                                            <option value="">Seleccione versión...</option>
                                            {caseItem.recalculations.map((r) => (
                                                <option key={r.id} value={r.version_number}>
                                                    v{r.version_number} ({r.reason === 'case_creation' ? 'Apertura' : r.reason})
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <button
                                        onClick={handleCompareVersions}
                                        disabled={!compareVerA || !compareVerB || compareVerA === compareVerB || comparing}
                                        className="px-3.5 py-1 bg-[#18221d] text-white text-xs font-bold rounded hover:bg-[#2c3d34] disabled:opacity-50 transition-colors"
                                    >
                                        {comparing ? 'Comparando...' : 'Comparar Versiones'}
                                    </button>
                                </div>

                                {comparisonError && (
                                    <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded">
                                        {comparisonError}
                                    </div>
                                )}

                                {comparisonResult && (
                                    <div className="space-y-3 pt-2">
                                        <div className="p-3 bg-[#fafaf8] border border-[#ccd1ca] rounded flex items-center justify-between text-xs">
                                            <span className="font-semibold text-[#18221d]">
                                                Comparando v{comparisonResult.version_a.version_number} vs v{comparisonResult.version_b.version_number}
                                            </span>
                                            <span className={`px-2 py-0.5 rounded font-bold text-[11px] ${
                                                comparisonResult.has_differences ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-emerald-100 text-emerald-900 border border-emerald-300'
                                            }`}>
                                                {comparisonResult.has_differences ? 'Existen diferencias entre versiones' : 'Sin discrepancias (Cálculos idénticos)'}
                                            </span>
                                        </div>

                                        <div className="overflow-x-auto border border-[#ccd1ca] rounded">
                                            <table className="w-full text-left text-xs border-collapse">
                                                <thead>
                                                    <tr className="bg-[#f2f1ea] border-b border-[#ccd1ca] text-[#687169] uppercase font-semibold">
                                                        <th className="py-2 px-3">Fecha</th>
                                                        <th className="py-2 px-3 text-center">Volumen v{comparisonResult.version_a.version_number}</th>
                                                        <th className="py-2 px-3 text-center">Volumen v{comparisonResult.version_b.version_number}</th>
                                                        <th className="py-2 px-3 text-center">Δ Encuestas</th>
                                                        <th className="py-2 px-3 text-center">Δ NPS</th>
                                                        <th className="py-2 px-3 text-center">Δ CSAT</th>
                                                        <th className="py-2 px-3 text-center">Estado</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-[#ecebe4]">
                                                    {comparisonResult.days.map((d: any, i: number) => (
                                                        <tr key={i} className={d.is_different ? 'bg-amber-50/60 font-semibold' : 'hover:bg-[#fafaf8]'}>
                                                            <td className="py-1.5 px-3">{d.date}</td>
                                                            <td className="py-1.5 px-3 text-center font-mono">{d.version_a.volume}</td>
                                                            <td className="py-1.5 px-3 text-center font-mono">{d.version_b.volume}</td>
                                                            <td className="py-1.5 px-3 text-center font-mono">
                                                                {d.diff.volume_delta !== 0 ? (
                                                                    <span className={d.diff.volume_delta > 0 ? 'text-emerald-700' : 'text-red-700'}>
                                                                        {d.diff.volume_delta > 0 ? `+${d.diff.volume_delta}` : d.diff.volume_delta}
                                                                    </span>
                                                                ) : '0'}
                                                            </td>
                                                            <td className="py-1.5 px-3 text-center font-mono">
                                                                {d.diff.nps_delta !== null && typeof d.diff.nps_delta === 'number' ? (
                                                                    <span className={d.diff.nps_delta > 0 ? 'text-emerald-700' : (d.diff.nps_delta < 0 ? 'text-red-700' : '')}>
                                                                        {formatScore(d.diff.nps_delta, 2, true)}
                                                                    </span>
                                                                ) : '—'}
                                                            </td>
                                                            <td className="py-1.5 px-3 text-center font-mono">
                                                                {d.diff.csat_delta !== null && typeof d.diff.csat_delta === 'number' ? (
                                                                    <span className={d.diff.csat_delta > 0 ? 'text-emerald-700' : (d.diff.csat_delta < 0 ? 'text-red-700' : '')}>
                                                                        {d.diff.csat_delta > 0 ? `+${formatPercentage(d.diff.csat_delta, 0)}` : formatPercentage(d.diff.csat_delta, 0)}
                                                                    </span>
                                                                ) : '—'}
                                                            </td>
                                                            <td className="py-1.5 px-3 text-center">
                                                                <span className={`text-[10px] px-1.5 py-0.5 rounded ${
                                                                    d.is_different ? 'bg-amber-100 text-amber-800 font-bold' : 'text-gray-400'
                                                                }`}>
                                                                    {d.is_different ? 'Variación detectada' : 'Sin cambio'}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* MODAL: REGISTRAR SESIÓN */}
            {showUpdateModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-lg max-w-xl w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-[#ccd1ca] pb-3">
                            <h3 className="font-serif font-bold text-[#18221d]">Registrar Sesión / Actualización</h3>
                            <button onClick={() => setShowUpdateModal(false)} className="text-[#687169] hover:text-[#18221d]">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveUpdate} className="space-y-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-[11px] font-bold uppercase text-[#18221d] mb-1">Fecha de Sesión</label>
                                    <input
                                        type="date"
                                        value={updateData.session_date}
                                        onChange={(e) => setUpdateData('session_date', e.target.value)}
                                        className="w-full px-3 py-1.5 border border-[#ccd1ca] rounded text-xs"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-[11px] font-bold uppercase text-[#18221d] mb-1">Tipo de Sesión</label>
                                    <select
                                        value={updateData.type}
                                        onChange={(e) => setUpdateData('type', e.target.value)}
                                        className="w-full px-3 py-1.5 border border-[#ccd1ca] rounded text-xs bg-white"
                                    >
                                        <option value="coaching">Sesión de Coaching 1 a 1</option>
                                        <option value="observation">Monitoreo y Observación</option>
                                        <option value="review">Revisión de Avance y Métricas</option>
                                        <option value="disciplinary">Medida Disciplinaria / Acta</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-[11px] font-bold uppercase text-[#18221d] mb-1">
                                    Resumen de la Sesión <span className="text-red-600">*</span>
                                </label>
                                <textarea
                                    rows={3}
                                    value={updateData.summary}
                                    onChange={(e) => setUpdateData('summary', e.target.value)}
                                    placeholder="Temas tratados, retroalimentación brindada y hallazgos en llamadas..."
                                    className="w-full px-3 py-1.5 border border-[#ccd1ca] rounded text-xs"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-[11px] font-bold uppercase text-[#18221d] mb-1">Acuerdos y Compromisos</label>
                                <textarea
                                    rows={2}
                                    value={updateData.agreements}
                                    onChange={(e) => setUpdateData('agreements', e.target.value)}
                                    placeholder="Compromisos asumidos por el colaborador y supervisor..."
                                    className="w-full px-3 py-1.5 border border-[#ccd1ca] rounded text-xs"
                                />
                            </div>

                            {/* Disciplinary Details Field */}
                            {updateData.type === 'disciplinary' && (
                                <div className="p-3 bg-red-50 border border-red-200 rounded space-y-2">
                                    <label className="block text-[11px] font-bold uppercase text-red-900 flex items-center gap-1.5">
                                        <Lock className="w-3.5 h-3.5" />
                                        Detalle Disciplinario Confidencial (Protegido con Cifrado)
                                    </label>
                                    <p className="text-[10px] text-red-700">
                                        Esta sección se almacena cifrada en base de datos y solo será visible para roles autorizados. Queda excluida de reportes PDF y analíticas de IA.
                                    </p>
                                    <textarea
                                        rows={3}
                                        value={updateData.disciplinary_details}
                                        onChange={(e) => setUpdateData('disciplinary_details', e.target.value)}
                                        placeholder="Descripción de la sanción, apercibimiento formal o acta administrativa..."
                                        className="w-full px-3 py-1.5 border border-red-300 rounded text-xs bg-white text-red-950"
                                    />
                                </div>
                            )}

                            <div className="pt-3 border-t border-[#ccd1ca] flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowUpdateModal(false)}
                                    className="px-3 py-1.5 border border-[#ccd1ca] rounded text-xs text-[#687169]"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={updating}
                                    className="px-4 py-1.5 bg-[#18221d] text-white rounded text-xs font-bold uppercase tracking-wider disabled:opacity-50"
                                >
                                    {updating ? 'Guardando...' : 'Guardar Sesión'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MODAL: VER DETALLE DISCIPLINARIO */}
            {disciplinaryModalData && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div className="bg-white border-2 border-red-300 rounded-lg shadow-xl max-w-lg w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-red-200 pb-3 text-red-900">
                            <div className="flex items-center gap-2 font-bold text-sm">
                                <ShieldAlert className="w-5 h-5 text-red-700" />
                                Información Disciplinaria Confidencial
                            </div>
                            <button onClick={() => setDisciplinaryModalData(null)} className="text-gray-400 hover:text-gray-600">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="p-4 bg-red-50/60 rounded border border-red-200 text-xs text-red-950 min-h-[100px] whitespace-pre-line font-serif leading-relaxed">
                            {disciplinaryModalData.loading ? (
                                <div className="text-center py-6 text-red-700 font-sans">Desencriptando y auditando acceso seguro...</div>
                            ) : (
                                disciplinaryModalData.details || 'No se registró texto disciplinario en esta actualización.'
                            )}
                        </div>

                        <div className="text-[10px] text-gray-500 italic">
                            * Este acceso ha sido registrado en la bitácora de auditoría inmutable del sistema (DISCIPLINARY_DETAIL_ACCESSED).
                        </div>

                        <div className="text-right">
                            <button
                                onClick={() => setDisciplinaryModalData(null)}
                                className="px-4 py-1.5 bg-[#18221d] text-white rounded text-xs font-semibold"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL: CERRAR CASO */}
            {showCloseModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-lg max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-[#ccd1ca] pb-3">
                            <h3 className="font-serif font-bold text-[#18221d]">Cerrar Expediente de Desempeño</h3>
                            <button onClick={() => setShowCloseModal(false)} className="text-[#687169] hover:text-[#18221d]">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleCloseCase} className="space-y-4">
                            <div>
                                <label className="block text-[11px] font-bold uppercase text-[#18221d] mb-1">Estado de Resolución</label>
                                <select
                                    value={closeData.resolution_status}
                                    onChange={(e) => setCloseData('resolution_status', e.target.value)}
                                    className="w-full px-3 py-1.5 border border-[#ccd1ca] rounded text-xs bg-white"
                                >
                                    <option value="resolved">Resuelto (Objetivos alcanzados)</option>
                                    <option value="closed">Cerrado (Transferido o cancelado)</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-[11px] font-bold uppercase text-[#18221d] mb-1">
                                    Motivo y Resumen de Cierre <span className="text-red-600">*</span>
                                </label>
                                <textarea
                                    rows={3}
                                    value={closeData.closure_reason}
                                    onChange={(e) => setCloseData('closure_reason', e.target.value)}
                                    placeholder="Detalle la justificación del cierre, mejoras evidenciadas o resolución final..."
                                    className="w-full px-3 py-1.5 border border-[#ccd1ca] rounded text-xs"
                                    required
                                />
                            </div>

                            <div className="pt-3 border-t border-[#ccd1ca] flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowCloseModal(false)}
                                    className="px-3 py-1.5 border border-[#ccd1ca] rounded text-xs text-[#687169]"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={closing}
                                    className="px-4 py-1.5 bg-emerald-800 text-white rounded text-xs font-bold uppercase tracking-wider disabled:opacity-50"
                                >
                                    {closing ? 'Cerrando...' : 'Confirmar Cierre'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
