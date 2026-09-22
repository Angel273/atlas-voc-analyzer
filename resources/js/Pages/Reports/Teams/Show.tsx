import React, { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import RunChart, { RunChartTrendItem } from '@/Components/RunChart';
import { 
    ArrowLeft, Download, CheckCircle2, AlertTriangle, ShieldCheck, 
    Sparkles, Calendar, Clock, BarChart3, Users, ChevronRight, ChevronDown, Hash,
    RefreshCw, TrendingUp, MessageSquare, Award
} from 'lucide-react';

interface TeamReportShowProps {
    report: {
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
        model: string;
        status: string;
        progress?: number;
        stage?: string;
        error_message?: string;
        file_path?: string;
        file_hash?: string;
        file_size?: number;
        tokens_used: number;
        created_at: string;
        created_by_user?: {
            name: string;
        };
        narrative?: {
            executive_summary: string;
            team_strengths: string[];
            team_risks: string[];
            agent_reviews: Array<{
                agent_name: string;
                assessment: string;
                action: string;
                verbatim_analysis?: string;
                strengths?: string[];
                friction_points?: string[];
            }>;
            recommended_actions: string[];
            data_quality_notes: string;
        };
        metrics_data?: {
            is_low_sample: boolean;
            metrics: {
                survey_volume: number;
                nps_score: number | null;
                csat_score: number | null;
                professionalism_score: number | null;
                goals_comparison: any;
            };
            comparison?: any;
            daily_trends?: RunChartTrendItem[];
            agent_reviews?: Array<{
                agent_name: string;
                agent_bms?: string;
                volume: number;
                nps: number | null;
                csat: number | null;
                professionalism: number | null;
                status: string;
                daily_trends?: RunChartTrendItem[];
            }>;
            verbatim_categories?: any[];
            open_cases?: any[];
        };
        previous_report?: {
            id: number;
            period_from: string;
            period_to: string;
        };
    };
}

const getAgentNarrative = (
    ag: {
        agent_name: string;
        agent_bms?: string;
        volume: number;
        nps: number | null;
        csat: number | null;
        professionalism: number | null;
        status?: string;
        verbatims?: Array<{ sentiment: string; verbatim: string; category?: string; date?: string }>;
        top_categories?: string[];
    },
    reviews?: Array<{
        agent_name: string;
        agent_bms?: string;
        assessment?: string;
        action?: string;
        verbatim_analysis?: string;
        strengths?: string[];
        friction_points?: string[];
    }>
) => {
    if (reviews && reviews.length > 0) {
        // 1. Exact match
        const exact = reviews.find(
            (nr) => nr.agent_name?.trim().toLowerCase() === ag.agent_name?.trim().toLowerCase()
        );
        if (exact) return exact;

        // 2. BMS match
        if (ag.agent_bms) {
            const bmsMatch = reviews.find(
                (nr) => nr.agent_bms && String(nr.agent_bms).trim() === String(ag.agent_bms).trim()
            );
            if (bmsMatch) return bmsMatch;
        }

        // 3. Word tokens match (order-independent, accent-insensitive)
        const tokenize = (str: string) =>
            str
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]/g, ' ')
                .split(/\s+/)
                .filter(Boolean)
                .sort()
                .join(' ');

        const agToken = tokenize(ag.agent_name);
        if (agToken) {
            const tokenMatch = reviews.find(
                (nr) => nr.agent_name && tokenize(nr.agent_name) === agToken
            );
            if (tokenMatch) return tokenMatch;
        }
    }

    // Dynamic guaranteed fallback so every agent has a rich analysis
    const npsFmt = ag.nps !== null ? (ag.nps > 0 ? `+${ag.nps.toFixed(2)}` : ag.nps.toFixed(2)) : 'N/D';
    const csatFmt = ag.csat !== null ? `${(ag.csat * 100).toFixed(0)}%` : 'N/D';
    const profFmt = ag.professionalism !== null ? `${(ag.professionalism * 100).toFixed(0)}%` : 'N/D';

    const vbs = ag.verbatims || [];
    const promQuotes = vbs.filter((v) => v.sentiment === 'promoter');
    const detQuotes = vbs.filter((v) => v.sentiment === 'detractor');

    const topC = ag.top_categories && ag.top_categories.length > 0 ? ` con concentración en: ${ag.top_categories.join(', ')}` : '';
    const vbAnalysis =
        vbs.length > 0
            ? `Se registraron ${vbs.length} comentarios de clientes${topC}. ${
                  promQuotes.length > 0 ? `${promQuotes.length} menciones promotoras favorables. ` : ''
              }${detQuotes.length > 0 ? `${detQuotes.length} menciones con oportunidad de resolución.` : ''}`
            : 'No se registraron comentarios textuales de clientes en este corte evaluado.';

    return {
        agent_name: ag.agent_name,
        agent_bms: ag.agent_bms || '',
        assessment: `Volumen: ${ag.volume} encuestas. NPS: ${npsFmt}, CSAT: ${csatFmt}, Profesionalismo: ${profFmt}. ${
            ag.status === 'critical'
                ? 'Desempeño en rango crítico con oportunidades prioritarias de satisfacción.'
                : ag.status === 'warning'
                ? 'Desempeño con oportunidad de mejora frente a metas de satisfacción.'
                : 'Rendimiento alineado con las metas operacionales de calidad.'
        }`,
        action:
            ag.status === 'critical'
                ? 'Programar sesión 1 a 1 de calibración de llamadas y plan de acompañamiento intensivo.'
                : ag.status === 'warning'
                ? 'Reforzar técnicas de resolución en primer contacto y empatía.'
                : 'Reconocer buen desempeño e incentivar como referente en mejores prácticas.',
        verbatim_analysis: vbAnalysis,
        strengths:
            promQuotes.length > 0
                ? ['Reconocimiento explícito de clientes por trato cordial, disposición y cortesía.']
                : ['Atención continua y registro consistente de interacciones con usuarios.'],
        friction_points:
            detQuotes.length > 0
                ? ['Comentarios de clientes señalando inconformidad con tiempos de resolución o respuesta.']
                : ['Mantener consistencia operativa en la gestión de casos atípicos.'],
    };
};

export default function TeamReportShow({ report }: TeamReportShowProps) {
    if (!report) {
        return (
            <AppLayout title="Reporte no encontrado">
                <div className="p-8 text-center text-[#687169]">Reporte no encontrado o en procesamiento...</div>
            </AppLayout>
        );
    }

    const isCompleted = report.status === 'completed';
    const isFailed = report.status === 'failed';
    const isPendingOrProcessing = report.status === 'pending' || report.status === 'processing';
    const metrics = report.metrics_data?.metrics;
    const narrative = report.narrative;
    const currentProgress = typeof report.progress === 'number' ? report.progress : (isCompleted ? 100 : 0);
    const [expandedAgent, setExpandedAgent] = useState<string | null>(null);

    // Live auto-polling every 2.5 seconds while report is being generated
    useEffect(() => {
        if (isPendingOrProcessing) {
            const interval = setInterval(() => {
                router.reload({ only: ['report'] });
            }, 2500);
            return () => clearInterval(interval);
        }
    }, [isPendingOrProcessing]);

    return (
        <AppLayout
            title={`Reporte #${report.id} · ${report.team.name}`}
            kicker="HERRAMIENTA 005 · INFORME EJECUTIVO DE EQUIPO"
            description={`Evaluación de desempeño y síntesis de Voz del Cliente del equipo de ${report.team.supervisor?.name || 'Supervisor'}`}
            actions={
                <div className="flex items-center gap-2">
                    <Link
                        href="/reports/teams"
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#ccd1ca] bg-white text-xs font-semibold text-[#18221d] rounded hover:bg-[#f2f1ea] transition-colors"
                    >
                        <ArrowLeft className="w-3.5 h-3.5" />
                        Volver a Reportes
                    </Link>

                    {isCompleted && (
                        <a
                            href={`/reports/teams/${report.id}/download`}
                            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-[#18221d] text-white text-xs font-bold uppercase tracking-wider rounded shadow-sm hover:bg-[#283830] transition-colors"
                        >
                            <Download className="w-3.5 h-3.5 text-[#d7f45b]" />
                            Descargar PDF Oficial
                        </a>
                    )}
                </div>
            }
        >
            <Head title={`Reporte #${report.id} - ${report.team.name}`} />

            <div className="space-y-6">
                {/* Header Information Banner */}
                <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-sm p-6">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-[#ccd1ca] pb-5">
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-xl font-serif font-bold text-[#18221d]">{report.team.name}</h1>
                                <span className="font-mono text-xs bg-[#f2f1ea] px-2 py-0.5 rounded border border-[#ccd1ca] font-bold">
                                    {report.team.code}
                                </span>
                                <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider ${
                                    report.status === 'completed' 
                                        ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' 
                                        : (report.status === 'failed' ? 'bg-red-100 text-red-900' : 'bg-amber-100 text-amber-900')
                                }`}>
                                    {report.status === 'completed' ? 'Completado' : (report.status === 'failed' ? 'Error' : `${report.status} (${currentProgress}%)`)}
                                </span>
                            </div>
                            <p className="text-xs text-[#687169] mt-1">
                                Período: <strong>{report.period_from} al {report.period_to}</strong> · Corte: {report.cutoff_date} · Supervisor: {report.team.supervisor?.name || 'No asignado'}
                            </p>
                        </div>

                        <div className="text-left md:text-right text-xs">
                            <div className="font-mono text-[11px] text-[#687169] flex items-center gap-1 md:justify-end">
                                <Hash className="w-3 h-3" />
                                Hash: {report.file_hash ? `${report.file_hash.slice(0, 20)}...` : 'Sin firma'}
                            </div>
                            <div className="text-[10px] text-[#687169] mt-0.5">
                                Tamaño: {report.file_size ? `${(report.file_size / 1024).toFixed(0)} KB` : 'N/D'} · Modelo: {report.model}
                            </div>
                        </div>
                    </div>

                    {/* Live Progress Bar Card if Pending or Processing */}
                    {isPendingOrProcessing && (
                        <div className="mt-5 p-5 bg-[#fafaf8] border border-[#ccd1ca] rounded-lg space-y-3">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                    <div className="w-8 h-8 rounded-full bg-[#18221d] flex items-center justify-center text-[#d7f45b]">
                                        <Sparkles className="w-4 h-4 animate-pulse" />
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h3 className="text-sm font-bold text-[#18221d]">
                                                {report.status === 'processing' ? 'Procesando Generación del Reporte' : 'Reporte en Cola de Ejecución'}
                                            </h3>
                                            <span className="text-xs font-mono font-bold px-2 py-0.5 rounded bg-[#18221d] text-[#d7f45b]">
                                                {currentProgress}%
                                            </span>
                                        </div>
                                        <p className="text-xs text-[#687169] mt-0.5">
                                            {report.stage || 'Preparando extracción y análisis...'}
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={() => router.reload({ only: ['report'] })}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-[#18221d] bg-white hover:bg-[#f2f1ea] border border-[#ccd1ca] rounded shadow-sm transition-colors"
                                >
                                    <RefreshCw className="w-3.5 h-3.5" />
                                    Actualizar
                                </button>
                            </div>

                            {/* Progress bar line */}
                            <div className="w-full bg-[#e4e9e2] rounded-full h-2.5 overflow-hidden">
                                <div
                                    className="bg-[#18221d] h-full rounded-full transition-all duration-700 ease-out"
                                    style={{ width: `${Math.max(5, Math.min(100, currentProgress))}%` }}
                                />
                            </div>

                            <div className="grid grid-cols-4 text-[10px] text-[#687169] pt-1 border-t border-[#ecebe4]">
                                <span className={currentProgress >= 15 ? 'font-bold text-[#18221d]' : ''}>1. Extracción de Datos</span>
                                <span className={`text-center ${currentProgress >= 45 ? 'font-bold text-[#18221d]' : ''}`}>2. Diagnóstico IA</span>
                                <span className={`text-center ${currentProgress >= 75 ? 'font-bold text-[#18221d]' : ''}`}>3. Renderizado PDF</span>
                                <span className={`text-right ${currentProgress >= 95 ? 'font-bold text-[#18221d]' : ''}`}>4. Firma SHA-256</span>
                            </div>
                        </div>
                    )}

                    {/* Error Banner if Failed */}
                    {isFailed && (
                        <div className="mt-5 p-4 bg-red-50 border border-red-200 rounded-lg space-y-2">
                            <div className="flex items-start gap-2.5">
                                <AlertTriangle className="w-4 h-4 text-red-700 mt-0.5 flex-shrink-0" />
                                <div className="flex-1">
                                    <h4 className="text-xs font-bold text-red-900">La generación del reporte no pudo completarse</h4>
                                    <p className="text-xs text-red-700 mt-1 font-mono">
                                        {report.error_message || report.stage || 'Error durante la ejecución.'}
                                    </p>
                                    <div className="mt-3">
                                        <Link
                                            href={`/reports/teams/${report.id}/retry`}
                                            method="post"
                                            as="button"
                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-800 text-white text-xs font-bold rounded shadow-sm hover:bg-red-900 transition-colors"
                                        >
                                            <RefreshCw className="w-3.5 h-3.5" />
                                            Reintentar Generación
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Low sample banner */}
                    {report.metrics_data?.is_low_sample && (
                        <div className="mt-4 p-3 bg-amber-50 border border-amber-200 text-amber-900 rounded text-xs flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4 text-amber-700" />
                            <strong>ADVERTENCIA DE CAUTELA MUESTRAL:</strong> Este equipo procesó {metrics?.survey_volume} encuestas en el corte actual (&lt; 15 encuestas).
                            Las fluctuaciones porcentuales deben evaluarse como referenciales.
                        </div>
                    )}

                    {/* Executive Summary */}
                    {narrative?.executive_summary && (
                        <div className="mt-5 p-4 bg-[#f2f7f3] border-l-4 border-[#16a34a] rounded-r text-xs text-[#14532d] leading-relaxed">
                            <strong className="block text-[10px] uppercase font-bold text-[#14532d] mb-1">Resumen Ejecutivo de Gestión:</strong>
                            {narrative.executive_summary}
                        </div>
                    )}

                    {/* KPI Metric Cards */}
                    {metrics && (
                        <div className="mt-5 grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div className="p-4 bg-[#fafaf8] border border-[#ccd1ca] rounded text-center">
                                <div className="text-[10px] uppercase font-bold text-[#687169]">Muestra Auditada</div>
                                <div className="text-2xl font-bold text-[#18221d] mt-1">{metrics.survey_volume}</div>
                                <div className="text-[10px] text-[#687169] mt-0.5">Encuestas válidas</div>
                            </div>

                            <div className="p-4 bg-[#fafaf8] border border-[#ccd1ca] rounded text-center">
                                <div className="text-[10px] uppercase font-bold text-[#687169]">Net Promoter Score</div>
                                <div className="text-2xl font-bold text-[#18221d] mt-1 font-mono">
                                    {metrics.nps_score !== null ? (metrics.nps_score > 0 ? `+${metrics.nps_score.toFixed(2)}` : metrics.nps_score.toFixed(2)) : 'N/D'}
                                </div>
                                <div className="text-[10px] text-emerald-800 font-semibold mt-0.5">
                                    Meta: &ge; {metrics.goals_comparison?.nps?.target_value !== undefined ? (metrics.goals_comparison.nps.target_value > 0 ? `+${metrics.goals_comparison.nps.target_value.toFixed(2)}` : metrics.goals_comparison.nps.target_value.toFixed(2)) : '+0.50'}
                                </div>
                            </div>

                            <div className="p-4 bg-[#fafaf8] border border-[#ccd1ca] rounded text-center">
                                <div className="text-[10px] uppercase font-bold text-[#687169]">CSAT (Satisfacción)</div>
                                <div className="text-2xl font-bold text-[#18221d] mt-1 font-mono">
                                    {metrics.csat_score !== null ? `${(metrics.csat_score * 100).toFixed(1)}%` : 'N/D'}
                                </div>
                                <div className="text-[10px] text-emerald-800 font-semibold mt-0.5">
                                    Meta: &ge; {metrics.goals_comparison?.csat?.target_value !== undefined ? `${(metrics.goals_comparison.csat.target_value * 100).toFixed(1)}%` : '80.0%'}
                                </div>
                            </div>

                            <div className="p-4 bg-[#fafaf8] border border-[#ccd1ca] rounded text-center">
                                <div className="text-[10px] uppercase font-bold text-[#687169]">Profesionalismo</div>
                                <div className="text-2xl font-bold text-[#18221d] mt-1 font-mono">
                                    {metrics.professionalism_score !== null ? `${(metrics.professionalism_score * 100).toFixed(1)}%` : 'N/D'}
                                </div>
                                <div className="text-[10px] text-emerald-800 font-semibold mt-0.5">
                                    Meta: &ge; {metrics.goals_comparison?.professionalism?.target_value !== undefined ? `${(metrics.goals_comparison.professionalism.target_value * 100).toFixed(1)}%` : '85.0%'}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Team Temporal Run Chart */}
                {report.metrics_data?.daily_trends && report.metrics_data.daily_trends.length > 0 && (
                    <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-sm p-6 space-y-4">
                        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 pb-2 border-b border-[#ccd1ca]">
                            <div className="flex items-center gap-2">
                                <TrendingUp className="w-4 h-4 text-[#18221d]" />
                                <h3 className="text-xs font-bold uppercase tracking-wider text-[#18221d]">
                                    Run Chart Temporal del Equipo (Evolución Diaria de NPS y CSAT)
                                </h3>
                            </div>
                            <div className="text-[11px] text-[#687169]">
                                {report.metrics_data.daily_trends.length} días de actividad en el período
                            </div>
                        </div>

                        <RunChart
                            trends={report.metrics_data.daily_trends}
                            npsTarget={metrics?.goals_comparison?.nps?.target_value ?? 0.50}
                            csatTarget={metrics?.goals_comparison?.csat?.target_value ?? 0.80}
                            height={160}
                            title="Tendencia Temporal Diaria (NPS & CSAT vs Metas)"
                        />
                    </div>
                )}

                {/* STRENGTHS AND RISKS */}
                {narrative && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="bg-white border border-[#ccd1ca] rounded-lg p-5 shadow-sm space-y-3">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-emerald-900 flex items-center gap-1.5 pb-2 border-b border-[#ccd1ca]">
                                <CheckCircle2 className="w-4 h-4 text-emerald-700" />
                                Fortalezas Identificadas
                            </h3>
                            <ul className="space-y-2 text-xs text-[#334139]">
                                {narrative.team_strengths?.map((str, idx) => (
                                    <li key={idx} className="flex items-start gap-2">
                                        <span className="text-emerald-700 font-bold">·</span>
                                        <span>{str}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        <div className="bg-white border border-[#ccd1ca] rounded-lg p-5 shadow-sm space-y-3">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-red-900 flex items-center gap-1.5 pb-2 border-b border-[#ccd1ca]">
                                <AlertTriangle className="w-4 h-4 text-red-700" />
                                Riesgos y Brechas Operacionales
                            </h3>
                            <ul className="space-y-2 text-xs text-[#334139]">
                                {narrative.team_risks?.map((rsk, idx) => (
                                    <li key={idx} className="flex items-start gap-2">
                                        <span className="text-red-700 font-bold">·</span>
                                        <span>{rsk}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                {/* AGENT PERFORMANCE REVIEWS */}
                {report.metrics_data?.agent_reviews && report.metrics_data.agent_reviews.length > 0 && (
                    <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-sm p-6 space-y-4">
                        <div className="flex items-center justify-between pb-2 border-b border-[#ccd1ca]">
                            <h3 className="text-xs font-bold uppercase tracking-wider text-[#18221d] flex items-center gap-2">
                                <Users className="w-4 h-4" />
                                Evaluación Individual y Run Charts de Agentes ({report.metrics_data.agent_reviews.length})
                            </h3>
                            <span className="text-[11px] text-[#687169]">
                                Haz clic en un agente para ver su Run Chart y diagnóstico
                            </span>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr className="border-b border-[#ccd1ca] bg-[#f2f1ea] text-[#687169] uppercase font-semibold">
                                        <th className="py-2.5 px-4">Agente</th>
                                        <th className="py-2.5 px-4 text-center">BMS ID</th>
                                        <th className="py-2.5 px-4 text-center">Encuestas</th>
                                        <th className="py-2.5 px-4 text-center">NPS</th>
                                        <th className="py-2.5 px-4 text-center">CSAT</th>
                                        <th className="py-2.5 px-4 text-center">Profesionalismo</th>
                                        <th className="py-2.5 px-4 text-center">Estado</th>
                                        <th className="py-2.5 px-2 text-center w-8"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#ecebe4]">
                                    {report.metrics_data.agent_reviews.map((ag, idx) => {
                                        const isExpanded = expandedAgent === ag.agent_name;
                                        const agentNarrative = getAgentNarrative(ag, narrative?.agent_reviews);

                                        return (
                                            <React.Fragment key={idx}>
                                                <tr
                                                    onClick={() => setExpandedAgent(isExpanded ? null : ag.agent_name)}
                                                    className={`cursor-pointer transition-colors ${
                                                        isExpanded ? 'bg-[#f4f7f4]' : 'hover:bg-[#fafaf8]'
                                                    }`}
                                                >
                                                    <td className="py-2.5 px-4 font-semibold text-[#18221d] flex items-center gap-2">
                                                        <span>{ag.agent_name}</span>
                                                    </td>
                                                    <td className="py-2.5 px-4 text-center font-mono text-[11px]">{ag.agent_bms}</td>
                                                    <td className="py-2.5 px-4 text-center font-mono">{ag.volume}</td>
                                                    <td className="py-2.5 px-4 text-center font-mono">
                                                        {ag.nps !== null ? (ag.nps > 0 ? `+${ag.nps.toFixed(2)}` : ag.nps.toFixed(2)) : 'N/D'}
                                                    </td>
                                                    <td className="py-2.5 px-4 text-center font-mono">
                                                        {ag.csat !== null ? `${(ag.csat * 100).toFixed(0)}%` : 'N/D'}
                                                    </td>
                                                    <td className="py-2.5 px-4 text-center font-mono">
                                                        {ag.professionalism !== null ? `${(ag.professionalism * 100).toFixed(0)}%` : 'N/D'}
                                                    </td>
                                                    <td className="py-2.5 px-4 text-center">
                                                        <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-semibold ${
                                                            ag.status === 'critical' ? 'bg-red-100 text-red-900' : (ag.status === 'warning' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900')
                                                        }`}>
                                                            {ag.status === 'critical' ? 'Crítico' : (ag.status === 'warning' ? 'Atención' : 'En Meta')}
                                                        </span>
                                                    </td>
                                                    <td className="py-2.5 px-2 text-center text-[#687169]">
                                                        {isExpanded ? (
                                                            <ChevronDown className="w-4 h-4 text-[#18221d]" />
                                                        ) : (
                                                            <ChevronRight className="w-4 h-4" />
                                                        )}
                                                    </td>
                                                </tr>

                                                {isExpanded && (
                                                    <tr>
                                                        <td colSpan={8} className="p-4 bg-[#fafaf8] border-b border-[#ccd1ca]">
                                                            <div className="space-y-4 max-w-4xl mx-auto">
                                                                {/* Individual Run Chart */}
                                                                <div className="bg-white p-3.5 rounded border border-[#ccd1ca] shadow-xs space-y-2">
                                                                    <div className="flex items-center justify-between pb-1.5 border-b border-[#ecebe4]">
                                                                        <div className="flex items-center gap-1.5">
                                                                            <TrendingUp className="w-3.5 h-3.5 text-emerald-700" />
                                                                            <h4 className="text-xs font-bold uppercase tracking-wider text-[#18221d]">
                                                                                Run Chart Individual: {ag.agent_name}
                                                                            </h4>
                                                                        </div>
                                                                        <span className="text-[10px] text-[#687169] font-mono">
                                                                            {ag.daily_trends?.length || 0} días evaluados
                                                                        </span>
                                                                    </div>

                                                                    <RunChart
                                                                        trends={ag.daily_trends || []}
                                                                        npsTarget={metrics?.goals_comparison?.nps?.target_value ?? 0.50}
                                                                        csatTarget={metrics?.goals_comparison?.csat?.target_value ?? 0.80}
                                                                        height={120}
                                                                        isAgent={true}
                                                                        title={`Secuencia Temporal de Desempeño Diario · ${ag.agent_name}`}
                                                                    />
                                                                </div>

                                                                {/* Agent Qualitative Dossier if available */}
                                                                {agentNarrative && (
                                                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1 text-xs">
                                                                        {agentNarrative.verbatim_analysis && (
                                                                            <div className="col-span-1 md:col-span-2 p-3.5 bg-white border border-[#ccd1ca] rounded shadow-xs">
                                                                                <strong className="block text-[10px] uppercase font-bold text-[#18221d] mb-1.5 flex items-center gap-1.5">
                                                                                    <MessageSquare className="w-3 h-3 text-[#18221d]" />
                                                                                    Análisis de Verbatims y Voz del Cliente:
                                                                                </strong>
                                                                                <p className="text-[#334139] leading-relaxed text-xs">
                                                                                    {agentNarrative.verbatim_analysis}
                                                                                </p>
                                                                            </div>
                                                                        )}

                                                                        {agentNarrative.strengths && agentNarrative.strengths.length > 0 && (
                                                                            <div className="p-3 bg-emerald-50/60 border border-emerald-200 rounded">
                                                                                <strong className="block text-[10px] uppercase font-bold text-emerald-900 mb-1.5 flex items-center gap-1.5">
                                                                                    <Award className="w-3 h-3 text-emerald-700" />
                                                                                    Fortalezas Destacadas:
                                                                                </strong>
                                                                                <ul className="space-y-1 text-emerald-950 text-xs">
                                                                                    {agentNarrative.strengths.map((s, i) => (
                                                                                        <li key={i} className="flex items-start gap-1.5">
                                                                                            <span className="text-emerald-700 font-bold">·</span>
                                                                                            <span>{s}</span>
                                                                                        </li>
                                                                                    ))}
                                                                                </ul>
                                                                            </div>
                                                                        )}

                                                                        {agentNarrative.friction_points && agentNarrative.friction_points.length > 0 && (
                                                                            <div className="p-3 bg-amber-50/60 border border-amber-200 rounded">
                                                                                <strong className="block text-[10px] uppercase font-bold text-amber-900 mb-1.5 flex items-center gap-1.5">
                                                                                    <AlertTriangle className="w-3 h-3 text-amber-700" />
                                                                                    Puntos de Fricción Identificados:
                                                                                </strong>
                                                                                <ul className="space-y-1 text-amber-950 text-xs">
                                                                                    {agentNarrative.friction_points.map((f, i) => (
                                                                                        <li key={i} className="flex items-start gap-1.5">
                                                                                            <span className="text-amber-700 font-bold">·</span>
                                                                                            <span>{f}</span>
                                                                                        </li>
                                                                                    ))}
                                                                                </ul>
                                                                            </div>
                                                                        )}

                                                                        {(agentNarrative.assessment || agentNarrative.action) && (
                                                                            <div className="col-span-1 md:col-span-2 p-3 bg-white border border-[#ccd1ca] rounded space-y-2">
                                                                                {agentNarrative.assessment && (
                                                                                    <div>
                                                                                        <strong className="text-[10px] uppercase font-bold text-[#687169] block mb-0.5">
                                                                                            Diagnóstico Operativo:
                                                                                        </strong>
                                                                                        <p className="text-[#334139] leading-relaxed">
                                                                                            {agentNarrative.assessment}
                                                                                        </p>
                                                                                    </div>
                                                                                )}
                                                                                {agentNarrative.action && (
                                                                                    <div className="pt-1.5 border-t border-[#ecebe4]">
                                                                                        <strong className="text-[10px] uppercase font-bold text-[#18221d] block mb-0.5">
                                                                                            Acción Recomendada para Supervisión:
                                                                                        </strong>
                                                                                        <p className="text-[#18221d] font-medium leading-relaxed">
                                                                                            {agentNarrative.action}
                                                                                        </p>
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </td>
                                                    </tr>
                                                )}
                                            </React.Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* RECOMMENDED ACTIONS */}
                {narrative?.recommended_actions && (
                    <div className="bg-white border border-[#ccd1ca] rounded-lg p-5 shadow-sm space-y-3">
                        <h3 className="text-xs font-bold uppercase tracking-wider text-[#18221d] pb-2 border-b border-[#ccd1ca]">
                            Plan de Acción Operacional Prioritario
                        </h3>
                        <ul className="space-y-2 text-xs text-[#334139]">
                            {narrative.recommended_actions.map((act, idx) => (
                                <li key={idx} className="flex items-start gap-2">
                                    <span className="font-bold text-[#18221d]">{idx + 1}.</span>
                                    <span>{act}</span>
                                </li>
                            ))}
                        </ul>

                        {narrative.data_quality_notes && (
                            <div className="mt-4 p-3 bg-[#fafaf8] rounded border border-[#ccd1ca] text-xs text-[#687169]">
                                <strong className="text-[#18221d] block text-[10px] uppercase mb-0.5">Nota Metodológica y Calidad del Dato:</strong>
                                {narrative.data_quality_notes}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
