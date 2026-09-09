import React, { useState, useEffect, useRef } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import EChartComponent from '@/Components/EChartComponent';
import MarkdownRenderer from '@/Components/MarkdownRenderer';
import {
    TrendingUp,
    Sparkles,
    Calculator,
    Send,
    Bot,
    User as UserIcon,
    AlertTriangle,
    ShieldCheck,
    History,
    ChevronDown,
    Layers,
    CheckCircle2,
    Info,
    BarChart2,
    ArrowRight,
    HelpCircle,
    Activity,
} from 'lucide-react';

interface ForecastResult {
    id?: number;
    date: string;
    actual_value: number | null;
    raw_forecast_value?: number | null;
    forecast_value: number;
    confidence_low: number | null;
    confidence_high: number | null;
    was_bounded?: boolean;
}

interface HistoricalPoint {
    date: string;
    value: number;
    sample_count: number;
    is_low_sample?: boolean;
    sample_status?: string;
    is_excluded?: boolean;
    exclusion_reason?: string | null;
    fitted_value?: number | null;
}

interface CandidateModel {
    code: string;
    name: string;
    mae: number;
    rmse: number;
    validation_predictions: number;
    is_selected: boolean;
    complexity_order: number;
    description: string;
    parameters?: any;
}

interface Forecast {
    id: number;
    metric: string;
    dimension?: string | null;
    dimension_value?: string | null;
    model: string;
    status?: string;
    reliability?: string;
    selection_reason?: string;
    parameters?: any;
    forecast_horizon: number;
    training_period_start: string;
    training_period_end: string;
    mae: number | null;
    rmse: number | null;
    r2: number | null;
    ai_interpretation?: string;
    results: ForecastResult[];
    historical_points?: HistoricalPoint[];
    created_at: string;
}

interface DriverItem {
    driver: string;
    variable_group: string;
    label: string;
    estimated_effect: string | number;
    percentage_points?: number;
    odds_ratio?: number;
    direction: 'Positive' | 'Negative' | 'Neutral';
    sample_size: number;
    confidence: 'SUPPORTED' | 'MODERATE' | 'LOW' | 'INSUFFICIENT';
    standard_error?: number;
    p_value?: number;
    reference_category?: string;
    ci_lower?: number;
    ci_upper?: number;
    explanation: string;
}

interface DriverAnalysisData {
    id?: number;
    status: string;
    target_metric: string;
    sample_size: number;
    controlled_variables: string[];
    reference_categories: Record<string, string>;
    drivers: DriverItem[];
    diagnostics: any;
    ai_interpretation?: string;
    created_at?: string;
}

interface Props {
    forecasts: { data: Forecast[]; links: any[] };
    supervisors: string[];
}

interface ChatMessage {
    id: string;
    role: 'assistant' | 'user';
    content: string;
    created_at: string;
}

const FORECAST_PROMPTS = [
    '¿Por qué se seleccionó este modelo estadístico específico?',
    '¿Cómo interpretar el MAE y RMSE obtenidos en la validación fuera de muestra?',
    '¿Qué significa el nivel de fiabilidad asignado por Atlas?',
    '¿Qué hipótesis operativas recomiendas investigar para la tendencia proyectada?',
];

const DRIVER_PROMPTS = [
    '¿Cuáles son las asociaciones negativas más fuertes con muestra soportada?',
    '¿Qué factores presentan muestra insuficiente y por qué no deben considerarse drivers confiables?',
    '¿Cómo se relacionan estos drivers con la tendencia general del pronóstico?',
    '¿Qué acciones operativas prácticas sugieres basadas en estas asociaciones?',
];

// Helper: Format date strings into clean short label (e.g. "01 Sep")
function formatDateLabel(dateStr: string): string {
    if (!dateStr) return '';
    const cleanStr = dateStr.includes('T') ? dateStr.split('T')[0] : dateStr.substring(0, 10);
    const parts = cleanStr.split('-');
    if (parts.length === 3) {
        const [, month, day] = parts;
        const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        const mIdx = parseInt(month, 10) - 1;
        return `${day} ${months[mIdx] || month}`;
    }
    return cleanStr;
}

// Helper: Format full date string (e.g. "1 de septiembre, 2026")
function formatFullDate(dateStr: string): string {
    if (!dateStr) return '';
    const cleanStr = dateStr.includes('T') ? dateStr.split('T')[0] : dateStr.substring(0, 10);
    const parts = cleanStr.split('-');
    if (parts.length === 3) {
        const [year, month, day] = parts;
        const months = [
            'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        ];
        const mIdx = parseInt(month, 10) - 1;
        return `${parseInt(day, 10)} de ${months[mIdx] || month}, ${year}`;
    }
    return cleanStr;
}

export default function ForecastIndex({ forecasts, supervisors }: Props) {
    // Active Tab: 'forecast' or 'drivers'
    const [activeTab, setActiveTab] = useState<'forecast' | 'drivers'>('forecast');

    // Forecast State (Model selector removed from UI per design rules)
    const [selectedMetric, setSelectedMetric] = useState<string>('nps');
    const [horizon, setHorizon] = useState<number>(14);
    const [selectedSupervisor, setSelectedSupervisor] = useState<string>('');
    const [calculating, setCalculating] = useState<boolean>(false);
    const [activeForecast, setActiveForecast] = useState<Forecast | null>(forecasts.data[0] || null);

    // Driver Analysis State
    const [driverMetric, setDriverMetric] = useState<string>('nps');
    const [driverSupervisor, setDriverSupervisor] = useState<string>('');
    const [driverAnalyzing, setDriverAnalyzing] = useState<boolean>(false);
    const [driverAnalysis, setDriverAnalysis] = useState<DriverAnalysisData | null>(null);
    const [showDiagnosticsAccordion, setShowDiagnosticsAccordion] = useState<boolean>(false);

    // Interactive Chat State
    const [chatMessages, setChatMessages] = useState<ChatMessage[]>([]);
    const [chatInput, setChatInput] = useState<string>('');
    const [chatLoading, setChatLoading] = useState<boolean>(false);
    const chatBottomRef = useRef<HTMLDivElement>(null);

    // Synchronize initial chat interpretation when activeForecast changes
    useEffect(() => {
        if (activeTab === 'forecast') {
            if (activeForecast?.ai_interpretation) {
                setChatMessages([
                    {
                        id: 'init_' + activeForecast.id,
                        role: 'assistant',
                        content: activeForecast.ai_interpretation,
                        created_at: activeForecast.created_at,
                    },
                ]);
            } else {
                setChatMessages([]);
            }
        } else if (activeTab === 'drivers') {
            if (driverAnalysis?.ai_interpretation) {
                setChatMessages([
                    {
                        id: 'driver_init_' + (driverAnalysis.id || Date.now()),
                        role: 'assistant',
                        content: driverAnalysis.ai_interpretation,
                        created_at: new Date().toISOString(),
                    },
                ]);
            } else {
                setChatMessages([]);
            }
        }
    }, [activeTab, activeForecast?.id, activeForecast?.ai_interpretation, driverAnalysis?.id, driverAnalysis?.ai_interpretation]);

    useEffect(() => {
        chatBottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [chatMessages]);

    // Calculate forecast (Automatic model selection)
    const handleRunForecast = async () => {
        setCalculating(true);
        try {
            const res = await fetch('/forecast/calculate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    metric: selectedMetric,
                    horizon: horizon,
                    dimension: selectedSupervisor ? 'supervisor' : null,
                    dimension_value: selectedSupervisor || null,
                }),
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Error al calcular el pronóstico');

            setActiveForecast(data.forecast);
        } catch (err: any) {
            alert(err.message);
        } finally {
            setCalculating(false);
        }
    };

    // Run Driver Analysis
    const handleRunDriverAnalysis = async () => {
        setDriverAnalyzing(true);
        try {
            const res = await fetch('/forecast/drivers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    metric: driverMetric,
                    supervisor: driverSupervisor || null,
                }),
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Error al calcular el análisis de drivers');

            setDriverAnalysis(data.analysis);
        } catch (err: any) {
            alert(err.message);
        } finally {
            setDriverAnalyzing(false);
        }
    };

    // Send chat message to Gemini
    const handleSendChatMessage = async (promptToSend?: string) => {
        const text = promptToSend || chatInput;
        if (!text.trim() || !activeForecast || chatLoading) return;

        const userMsg: ChatMessage = {
            id: 'user_' + Date.now(),
            role: 'user',
            content: text,
            created_at: new Date().toISOString(),
        };

        const updatedMessages = [...chatMessages, userMsg];
        setChatMessages(updatedMessages);
        setChatInput('');
        setChatLoading(true);

        try {
            const res = await fetch(`/forecast/${activeForecast.id}/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    message: text,
                    history: updatedMessages.map((m) => ({ role: m.role, content: m.content })),
                    driver_context: driverAnalysis ? {
                        target_metric: driverAnalysis.target_metric,
                        sample_size: driverAnalysis.sample_size,
                        drivers: driverAnalysis.drivers,
                    } : null,
                }),
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Error al obtener respuesta de Gemini');

            const aiReplyMsg: ChatMessage = {
                id: 'ai_' + Date.now(),
                role: 'assistant',
                content: data.reply,
                created_at: new Date().toISOString(),
            };
            setChatMessages([...updatedMessages, aiReplyMsg]);
        } catch (err: any) {
            setChatMessages([
                ...updatedMessages,
                {
                    id: 'err_' + Date.now(),
                    role: 'assistant',
                    content: `⚠️ ${err.message || 'No fue posible completar la consulta. Por favor intenta de nuevo.'}`,
                    created_at: new Date().toISOString(),
                },
            ]);
        } finally {
            setChatLoading(false);
        }
    };

    // Prepare unified timeline data (Historical Observed + Future Projected)
    const historicalPoints = activeForecast?.historical_points || [];
    const projectionResults = activeForecast?.results || [];
    const isInsufficientHistory = activeForecast?.status === 'insufficient_history';

    const histDates = historicalPoints.map((p) => p.date);
    const projDates = projectionResults.map((r) => r.date);
    const allDates = [...histDates, ...projDates];
    const xLabels = allDates.map((d) => formatDateLabel(d));

    // Data series arrays aligned with allDates
    const histData: (number | null)[] = [];
    const projData: (number | null)[] = [];
    const highData: (number | null)[] = [];
    const lowData: (number | null)[] = [];

    // Fill historical points
    historicalPoints.forEach((p) => {
        histData.push(+(p.value * 100).toFixed(1));
        projData.push(null);
        highData.push(null);
        lowData.push(null);
    });

    // Connect projection seamlessly from the last historical point if valid
    if (!isInsufficientHistory && historicalPoints.length > 0 && projectionResults.length > 0) {
        const lastHistIdx = historicalPoints.length - 1;
        const lastHistVal = +(historicalPoints[lastHistIdx].value * 100).toFixed(1);
        projData[lastHistIdx] = lastHistVal;
    }

    // Fill projected points (only if completed)
    if (!isInsufficientHistory) {
        projectionResults.forEach((r) => {
            histData.push(null);
            projData.push(+(r.forecast_value * 100).toFixed(1));
            highData.push(r.confidence_high !== null ? +(r.confidence_high * 100).toFixed(1) : null);
            lowData.push(r.confidence_low !== null ? +(r.confidence_low * 100).toFixed(1) : null);
        });
    }

    const cutIndex = historicalPoints.length > 0 ? historicalPoints.length - 1 : 0;
    const hasConfidence = projectionResults.some((r) => r.confidence_high !== null);

    const chartOptions = {
        tooltip: {
            trigger: 'axis',
            backgroundColor: 'rgba(255, 255, 255, 0.98)',
            borderColor: '#ccd1ca',
            borderWidth: 1,
            textStyle: { color: '#18221d', fontSize: 12 },
            formatter: (params: any[]) => {
                if (!params || params.length === 0) return '';
                const idx = params[0].dataIndex;
                const dateRaw = allDates[idx];
                const dateFormatted = formatFullDate(dateRaw);
                const isHistorical = idx < historicalPoints.length;

                let html = `<div class="font-bold text-xs border-b border-[#ccd1ca] pb-1 mb-1.5">${dateFormatted}</div>`;

                if (isHistorical) {
                    const hp = historicalPoints[idx];
                    const isLow = hp.is_low_sample || (hp.sample_count < 20);
                    const markerColor = isLow ? '#c09853' : '#18221d';

                    html += `<div class="flex items-center gap-1.5 text-xs text-[#18221d]">
                        <span class="inline-block w-2.5 h-2.5 rounded-full" style="background-color: ${markerColor}"></span>
                        <strong>Dato Histórico:</strong> ${(hp.value * 100).toFixed(1)}%
                    </div>`;
                    html += `<div class="text-[11px] text-[#687169] mt-0.5">Muestra: <strong>${hp.sample_count}</strong> encuestas</div>`;
                    if (isLow) {
                        html += `<div class="text-[10px] text-[#c09853] font-semibold mt-1 bg-[#fcf8e3] px-1.5 py-0.5 rounded">⚠️ Muestra Baja (&lt; 20 encuestas)</div>`;
                    }
                } else {
                    const projIdx = idx - historicalPoints.length;
                    const res = projectionResults[projIdx];
                    if (res) {
                        html += `<div class="flex items-center gap-1.5 text-xs text-[#2e5e33]">
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-[#2e5e33]"></span>
                            <strong>Proyección:</strong> ${(res.forecast_value * 100).toFixed(1)}%
                        </div>`;
                        if (res.was_bounded) {
                            html += `<div class="text-[10px] text-[#687169]">Valor acotado al rango métrico</div>`;
                        }
                        if (res.confidence_low !== null && res.confidence_high !== null) {
                            html += `<div class="text-[11px] text-[#687169] mt-0.5">
                                Intervalo 95%: [<strong>${(res.confidence_low * 100).toFixed(1)}%</strong> — <strong>${(res.confidence_high * 100).toFixed(1)}%</strong>]
                            </div>`;
                        }
                    }
                }
                return html;
            },
        },
        legend: {
            data: hasConfidence
                ? ['Histórico Observado', 'Proyección Automática', 'Límite Superior (95%)', 'Límite Inferior (95%)']
                : ['Histórico Observado', 'Proyección Automática'],
            bottom: 0,
            textStyle: { fontSize: 11, color: '#18221d' },
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '12%',
            top: '8%',
            containLabel: true,
        },
        xAxis: {
            type: 'category',
            data: xLabels,
            boundaryGap: false,
            axisLabel: {
                color: '#687169',
                fontSize: 11,
            },
            axisLine: { lineStyle: { color: '#ccd1ca' } },
        },
        yAxis: {
            type: 'value',
            name: `${(activeForecast?.metric || selectedMetric).toUpperCase()} (%)`,
            nameTextStyle: { color: '#687169', fontSize: 11 },
            axisLabel: {
                formatter: '{value}%',
                color: '#687169',
                fontSize: 11,
            },
            splitLine: { lineStyle: { color: '#f0f0ec' } },
        },
        series: [
            {
                name: 'Histórico Observado',
                type: 'line',
                data: histData,
                smooth: false,
                symbol: (val: any, params: any) => {
                    const hp = historicalPoints[params.dataIndex];
                    return (hp?.is_low_sample || (hp?.sample_count < 20)) ? 'triangle' : 'circle';
                },
                symbolSize: (val: any, params: any) => {
                    const hp = historicalPoints[params.dataIndex];
                    return (hp?.is_low_sample || (hp?.sample_count < 20)) ? 8 : 6;
                },
                itemStyle: {
                    color: (params: any) => {
                        const hp = historicalPoints[params.dataIndex];
                        return (hp?.is_low_sample || (hp?.sample_count < 20)) ? '#c09853' : '#18221d';
                    },
                    borderColor: '#ffffff',
                    borderWidth: 2,
                },
                lineStyle: { color: '#18221d', width: 3 },
            },
            ...(isInsufficientHistory ? [] : [
                {
                    name: 'Proyección Automática',
                    type: 'line',
                    data: projData,
                    smooth: true,
                    symbol: 'circle',
                    symbolSize: 6,
                    itemStyle: { color: '#2e5e33', borderColor: '#ffffff', borderWidth: 2 },
                    lineStyle: { color: '#2e5e33', width: 3, type: 'dashed' },
                    markLine: {
                        symbol: ['none', 'none'],
                        silent: false,
                        label: {
                            formatter: 'Corte Histórico / Proyección',
                            position: 'insideEndTop',
                            fontSize: 10,
                            color: '#2e5e33',
                            backgroundColor: '#eef7e8',
                            padding: [3, 6],
                            borderRadius: 2,
                            borderWidth: 1,
                            borderColor: '#aac69c',
                        },
                        lineStyle: {
                            type: 'dashed',
                            color: '#2e5e33',
                            width: 1.5,
                        },
                        data: [{ xAxis: cutIndex }],
                    },
                },
                ...(hasConfidence ? [
                    {
                        name: 'Límite Superior (95%)',
                        type: 'line',
                        data: highData,
                        symbol: 'none',
                        lineStyle: { type: 'dotted', color: '#8cb981', width: 1.5 },
                    },
                    {
                        name: 'Límite Inferior (95%)',
                        type: 'line',
                        data: lowData,
                        symbol: 'none',
                        lineStyle: { type: 'dotted', color: '#8cb981', width: 1.5 },
                    },
                ] : []),
            ]),
        ],
    };

    const candidateModels: CandidateModel[] = activeForecast?.parameters?.candidate_comparison || [];
    const reliabilityRating = activeForecast?.reliability || 'INSUFFICIENT';
    const totalHistoricalResponses = historicalPoints.reduce((acc, p) => acc + p.sample_count, 0);

    return (
        <AppLayout
            title="Predictive Forecasting & Driver Engine"
            kicker="MODELADO EXPLICABLE · VALIDACIÓN WALK-FORWARD & ANÁLISIS DE DRIVERS"
            description="Motor matemático determinista con selección automática de modelos fuera de muestra, Data Quality Gate y análisis multivariado de drivers independiente."
        >
            <Head title="Forecasting & Drivers — ATLAS VOC" />

            {/* Navigation Tabs (Atlas Editorial Philosophy) */}
            <div className="flex border-b border-[#ccd1ca] mb-6 bg-white">
                <button
                    onClick={() => setActiveTab('forecast')}
                    className={`px-6 py-3.5 text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2 border-b-2 ${
                        activeTab === 'forecast'
                            ? 'border-[#18221d] text-[#18221d] bg-[#f7f6f1]'
                            : 'border-transparent text-[#687169] hover:text-[#18221d]'
                    }`}
                >
                    <TrendingUp className="w-4 h-4" />
                    <span>Pronóstico Temporal (Forecast Engine)</span>
                </button>

                <button
                    onClick={() => {
                        setActiveTab('drivers');
                        if (!driverAnalysis) {
                            handleRunDriverAnalysis();
                        }
                    }}
                    className={`px-6 py-3.5 text-xs font-bold uppercase tracking-wider transition-colors flex items-center gap-2 border-b-2 ${
                        activeTab === 'drivers'
                            ? 'border-[#18221d] text-[#18221d] bg-[#f7f6f1]'
                            : 'border-transparent text-[#687169] hover:text-[#18221d]'
                    }`}
                >
                    <BarChart2 className="w-4 h-4" />
                    <span>Análisis de Drivers (Asociaciones)</span>
                </button>
            </div>

            {/* TAB 1: FORECAST ENGINE */}
            {activeTab === 'forecast' && (
                <div className="space-y-8">
                    {/* Forecast Configuration Panel (Model selection removed per rules) */}
                    <div className="border border-[#ccd1ca] bg-white p-6">
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                                    Métrica Objetivo
                                </label>
                                <select
                                    value={selectedMetric}
                                    onChange={(e) => setSelectedMetric(e.target.value)}
                                    className="w-full p-2.5 bg-white border border-[#ccd1ca] text-xs text-[#18221d] focus:outline-none focus:border-[#18221d]"
                                >
                                    <option value="nps">Net Promoter Score (NPS)</option>
                                    <option value="csat">Customer Satisfaction (CSAT)</option>
                                    <option value="professionalism">Professionalism Score</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                                    Horizonte de Proyección
                                </label>
                                <select
                                    value={horizon}
                                    onChange={(e) => setHorizon(parseInt(e.target.value, 10))}
                                    className="w-full p-2.5 bg-white border border-[#ccd1ca] text-xs text-[#18221d] focus:outline-none focus:border-[#18221d]"
                                >
                                    <option value={7}>7 días adelante</option>
                                    <option value={14}>14 días adelante</option>
                                    <option value={30}>30 días adelante</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                                    Segmentar por Supervisor
                                </label>
                                <select
                                    value={selectedSupervisor}
                                    onChange={(e) => setSelectedSupervisor(e.target.value)}
                                    className="w-full p-2.5 bg-white border border-[#ccd1ca] text-xs text-[#18221d] focus:outline-none focus:border-[#18221d]"
                                >
                                    <option value="">Toda la operación</option>
                                    {supervisors.map((s) => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between border-t border-[#ccd1ca]/60 pt-4 gap-3">
                            <div className="text-xs text-[#687169] flex items-center gap-2">
                                <History className="w-4 h-4 text-[#687169]" />
                                <span>
                                    {historicalPoints.length} periodos históricos disponibles ({totalHistoricalResponses} encuestas analizadas)
                                </span>
                            </div>

                            <button
                                onClick={handleRunForecast}
                                disabled={calculating}
                                className="px-6 py-2.5 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black transition-colors flex items-center gap-2 disabled:opacity-50"
                            >
                                <Calculator className="w-4 h-4" />
                                <span>{calculating ? 'Evaluando modelos...' : 'Calcular Pronóstico Automático'}</span>
                            </button>
                        </div>
                    </div>

                    {/* Quality Gate Alert if Insufficient History */}
                    {isInsufficientHistory && (
                        <div className="border-2 border-[#c09853] bg-[#fcf8e3] p-6 text-[#8a6d3b]">
                            <div className="flex items-start gap-4">
                                <div className="p-2 bg-[#fbeed5] rounded-full text-[#c09853] flex-shrink-0">
                                    <AlertTriangle className="w-6 h-6" />
                                </div>
                                <div className="flex-1">
                                    <div className="inline-block px-2 py-0.5 bg-[#c09853] text-white font-mono text-[10px] font-bold uppercase tracking-wider mb-2">
                                        Data Quality Gate · Insufficient History
                                    </div>
                                    <h3 className="font-serif text-xl text-[#18221d] font-bold mb-1">
                                        Atlas no cuenta con suficientes observaciones históricas para producir un pronóstico confiable.
                                    </h3>
                                    <p className="text-xs text-[#687169] leading-relaxed mb-4">
                                        Se registraron <strong>{historicalPoints.length} periodos históricos</strong> en el dataset. El estándar estadístico de gobernanza de Atlas requiere un mínimo de <strong>28 periodos diarios válidos</strong> con al menos 20 encuestas por periodo para evitar proyecciones espurias.
                                    </p>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <button
                                            onClick={() => {
                                                setActiveTab('drivers');
                                                if (!driverAnalysis) handleRunDriverAnalysis();
                                            }}
                                            className="px-4 py-2 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black transition-colors flex items-center gap-1.5"
                                        >
                                            <span>Explorar Análisis de Drivers</span>
                                            <ArrowRight className="w-3.5 h-3.5" />
                                        </button>
                                        <span className="text-[11px] text-[#8a6d3b]">
                                            * Nota: Driver Analysis está disponible de inmediato ya que opera sobre el volumen transaccional ({totalHistoricalResponses} encuestas).
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* KPI Cards (Clean Explainable Forecast Summary) */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div className="border border-[#ccd1ca] bg-white p-5">
                            <span className="text-[11px] font-bold uppercase tracking-wider text-[#687169]">Modelo Seleccionado</span>
                            <div className="font-serif text-2xl text-[#18221d] mt-1 capitalize">
                                {isInsufficientHistory
                                    ? 'No Disponible'
                                    : (activeForecast?.parameters?.selected_model?.name || activeForecast?.model || 'Automático')}
                            </div>
                            <span className="text-xs text-[#687169] mt-0.5 block">
                                {isInsufficientHistory ? 'Requiere 28 periodos' : 'Menor MAE out-of-sample'}
                            </span>
                        </div>

                        <div className="border border-[#ccd1ca] bg-white p-5">
                            <span className="text-[11px] font-bold uppercase tracking-wider text-[#687169]">Validation MAE</span>
                            <div className="font-serif text-2xl text-[#18221d] mt-1 font-mono">
                                {activeForecast?.mae !== null && activeForecast?.mae !== undefined
                                    ? `${(activeForecast.mae * 100).toFixed(1)} pp`
                                    : 'N/A'}
                            </div>
                            <span className="text-xs text-[#687169] mt-0.5 block">Desvío medio fuera de muestra</span>
                        </div>

                        <div className="border border-[#ccd1ca] bg-white p-5">
                            <span className="text-[11px] font-bold uppercase tracking-wider text-[#687169]">Validation RMSE</span>
                            <div className="font-serif text-2xl text-[#18221d] mt-1 font-mono">
                                {activeForecast?.rmse !== null && activeForecast?.rmse !== undefined
                                    ? `${(activeForecast.rmse * 100).toFixed(1)} pp`
                                    : 'N/A'}
                            </div>
                            <span className="text-xs text-[#687169] mt-0.5 block">Penalización de errores cuadráticos</span>
                        </div>

                        <div className="border border-[#ccd1ca] bg-white p-5">
                            <span className="text-[11px] font-bold uppercase tracking-wider text-[#687169]">Fiabilidad del Pronóstico</span>
                            <div className="mt-1 flex items-center gap-2">
                                <span className={`inline-block px-2.5 py-1 text-xs font-bold uppercase font-mono tracking-wider ${
                                    reliabilityRating === 'HIGH'
                                        ? 'bg-[#eef7e8] text-[#2e5e33] border border-[#aac69c]'
                                        : reliabilityRating === 'MODERATE'
                                        ? 'bg-[#f7f6f1] text-[#18221d] border border-[#ccd1ca]'
                                        : 'bg-[#fcf8e3] text-[#8a6d3b] border border-[#fbeed5]'
                                }`}>
                                    {reliabilityRating}
                                </span>
                            </div>
                            <span className="text-xs text-[#687169] mt-1 block">
                                {activeForecast?.parameters?.reliability_evaluation?.signals?.avg_daily_surveys
                                    ? `${activeForecast.parameters.reliability_evaluation.signals.avg_daily_surveys} enc/día prom`
                                    : 'Evaluación determinista'}
                            </span>
                        </div>
                    </div>

                    {/* Chart Container */}
                    <div className="border border-[#ccd1ca] bg-white p-6">
                        <div className="flex flex-col md:flex-row md:items-center justify-between mb-4 pb-3 border-b border-[#ccd1ca]/60">
                            <div>
                                <h3 className="font-serif text-2xl text-[#18221d]">
                                    Curva Temporal: {(activeForecast?.metric || selectedMetric).toUpperCase()}
                                    {!isInsufficientHistory && ` (+${activeForecast?.forecast_horizon || horizon} días)`}
                                </h3>
                                <p className="text-xs text-[#687169] mt-0.5">
                                    Serie histórica observada con marcadores diferenciados de baja muestra (&lt; 20 encuestas).
                                </p>
                            </div>

                            <div className="mt-3 md:mt-0 flex items-center gap-4 text-xs text-[#687169]">
                                <div className="flex items-center gap-1.5">
                                    <span className="w-2.5 h-2.5 rounded-full bg-[#18221d]"></span>
                                    <span>Muestra Normal (&ge; 20)</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="w-3 h-3 text-[#c09853] font-bold">▲</span>
                                    <span>Baja Muestra (&lt; 20)</span>
                                </div>
                            </div>
                        </div>

                        <EChartComponent options={chartOptions} height={360} />
                    </div>

                    {/* Model Comparison Table (Walk-Forward Validation Results) */}
                    {candidateModels.length > 0 && (
                        <div className="border border-[#ccd1ca] bg-white p-6">
                            <div className="flex items-center justify-between mb-4 pb-3 border-b border-[#ccd1ca]/60">
                                <div>
                                    <h4 className="font-serif text-xl text-[#18221d]">
                                        Comparativa de Modelos Candidatos (Walk-Forward Validation)
                                    </h4>
                                    <p className="text-xs text-[#687169] mt-0.5">
                                        Evaluación determinista fuera de muestra (*rolling-origin*). La selección prioriza el menor MAE y desempata por simplicidad.
                                    </p>
                                </div>
                                <div className="text-right">
                                    <span className="text-[11px] font-mono text-[#2e5e33] bg-[#eef7e8] px-2 py-0.5 border border-[#aac69c]">
                                        Selección Matemática Determinista
                                    </span>
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr className="border-b border-[#ccd1ca] bg-[#f7f6f1] text-[#18221d]">
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider">Modelo Evaluado</th>
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider font-mono">MAE Out-of-Sample</th>
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider font-mono">RMSE</th>
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider">Ventanas</th>
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider">Resultado</th>
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider">Fundamento Estadístico</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#ccd1ca]/50">
                                        {candidateModels.map((m) => (
                                            <tr key={m.code} className={m.is_selected ? 'bg-[#eef7e8]/40 font-medium' : 'hover:bg-[#fafaf8]'}>
                                                <td className="py-3 px-3 font-semibold text-[#18221d]">
                                                    {m.name}
                                                </td>
                                                <td className="py-3 px-3 font-mono">
                                                    {(m.mae * 100).toFixed(2)} pp ({m.mae})
                                                </td>
                                                <td className="py-3 px-3 font-mono">
                                                    {(m.rmse * 100).toFixed(2)} pp ({m.rmse})
                                                </td>
                                                <td className="py-3 px-3 text-[#687169]">
                                                    {m.validation_predictions} pasos
                                                </td>
                                                <td className="py-3 px-3">
                                                    {m.is_selected ? (
                                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-[#2e5e33] text-white text-[10px] font-bold uppercase tracking-wider rounded-none">
                                                            <CheckCircle2 className="w-3 h-3" />
                                                            SELECCIONADO
                                                        </span>
                                                    ) : (
                                                        <span className="text-[10px] uppercase tracking-wider text-[#687169]">
                                                            Descartado
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 px-3 text-[#687169] text-[11px]">
                                                    {m.description}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {activeForecast?.selection_reason && (
                                <div className="mt-4 p-3 bg-[#f7f6f1] border border-[#ccd1ca] text-xs text-[#18221d] flex items-center gap-2">
                                    <Info className="w-4 h-4 text-[#2e5e33] flex-shrink-0" />
                                    <span>
                                        <strong>Razón de Selección:</strong> {activeForecast.selection_reason}
                                    </span>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}

            {/* TAB 2: DRIVER ANALYSIS ENGINE */}
            {activeTab === 'drivers' && (
                <div className="space-y-8">
                    {/* Drivers Configuration Panel */}
                    <div className="border border-[#ccd1ca] bg-white p-6">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                            <div>
                                <span className="text-[11px] font-bold uppercase tracking-wider text-[#687169] block mb-1">
                                    Módulo Analítico Desacoplado
                                </span>
                                <h3 className="font-serif text-2xl text-[#18221d]">
                                    Análisis de Drivers (Asociaciones Estadísticas)
                                </h3>
                                <p className="text-xs text-[#687169] mt-0.5">
                                    Identifica qué factores (categorías, antigüedad, ola, supervisor) presentan una relación estadísticamente significativa con la métrica objetivo.
                                </p>
                            </div>

                            <div className="text-right">
                                <span className="inline-block px-2.5 py-1 bg-[#eef7e8] border border-[#aac69c] text-[#2e5e33] text-[11px] font-mono font-medium">
                                    Muestra: {driverAnalysis?.sample_size || totalHistoricalResponses} Encuestas
                                </span>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-[#ccd1ca]/60 pt-4 mb-4">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                                    Métrica Objetivo
                                </label>
                                <select
                                    value={driverMetric}
                                    onChange={(e) => setDriverMetric(e.target.value)}
                                    className="w-full p-2.5 bg-white border border-[#ccd1ca] text-xs text-[#18221d] focus:outline-none focus:border-[#18221d]"
                                >
                                    <option value="nps">Net Promoter Score (NPS) — Regresión OLS</option>
                                    <option value="csat">Customer Satisfaction (CSAT) — Regresión Logística</option>
                                    <option value="professionalism">Professionalism — Regresión Logística</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                                    Filtrar por Supervisor
                                </label>
                                <select
                                    value={driverSupervisor}
                                    onChange={(e) => setDriverSupervisor(e.target.value)}
                                    className="w-full p-2.5 bg-white border border-[#ccd1ca] text-xs text-[#18221d] focus:outline-none focus:border-[#18221d]"
                                >
                                    <option value="">Toda la operación</option>
                                    {supervisors.map((s) => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                </select>
                            </div>

                            <div className="flex items-end">
                                <button
                                    onClick={handleRunDriverAnalysis}
                                    disabled={driverAnalyzing}
                                    className="w-full py-2.5 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black transition-colors flex items-center justify-center gap-2 disabled:opacity-50"
                                >
                                    <Activity className="w-4 h-4" />
                                    <span>{driverAnalyzing ? 'Calculando regresión...' : 'Ejecutar Análisis de Drivers'}</span>
                                </button>
                            </div>
                        </div>

                        {/* Critical Methodology Notice */}
                        <div className="p-3 bg-[#f7f6f1] border border-[#ccd1ca] text-xs text-[#687169] flex items-center gap-2">
                            <Info className="w-4 h-4 text-[#18221d] flex-shrink-0" />
                            <span>
                                <strong>Principio Metodológico:</strong> Los coeficientes reflejan asociaciones estadísticas estimadas controlando simultáneamente por las demás variables. <strong>No implican causalidad directa.</strong>
                            </span>
                        </div>
                    </div>

                    {/* Drivers Results Table */}
                    {driverAnalysis && (
                        <div className="border border-[#ccd1ca] bg-white p-6">
                            <div className="flex items-center justify-between mb-4 pb-3 border-b border-[#ccd1ca]/60">
                                <div>
                                    <h4 className="font-serif text-xl text-[#18221d]">
                                        Asociaciones Clave (Drivers Detectados)
                                    </h4>
                                    <p className="text-xs text-[#687169] mt-0.5">
                                        Ordenados por magnitud del impacto estimado respecto a la categoría de referencia.
                                    </p>
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr className="border-b border-[#ccd1ca] bg-[#f7f6f1] text-[#18221d]">
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider">Factor / Driver</th>
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider font-mono">Efecto Estimado</th>
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider">Dirección</th>
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider font-mono">Muestra (n)</th>
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider">Soporte Muestral</th>
                                            <th className="py-2.5 px-3 font-bold uppercase tracking-wider">Interpretación Operativa</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[#ccd1ca]/50">
                                        {driverAnalysis.drivers.map((d, i) => {
                                            const isInsufficient = d.confidence === 'INSUFFICIENT';
                                            const isLow = d.confidence === 'LOW';
                                            const isSupported = d.confidence === 'SUPPORTED';

                                            return (
                                                <tr
                                                    key={i}
                                                    className={
                                                        isInsufficient
                                                            ? 'bg-[#fcf8e3]/30 opacity-75'
                                                            : isSupported
                                                            ? 'hover:bg-[#f7f6f1]'
                                                            : 'hover:bg-[#fafaf8]'
                                                    }
                                                >
                                                    <td className="py-3 px-3 font-semibold text-[#18221d]">
                                                        {d.driver}
                                                    </td>
                                                    <td className="py-3 px-3 font-mono font-bold text-sm">
                                                        {typeof d.estimated_effect === 'number'
                                                            ? (d.estimated_effect >= 0 ? `+${d.estimated_effect}` : d.estimated_effect)
                                                            : d.estimated_effect}
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        <span
                                                            className={`inline-block px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${
                                                                d.direction === 'Positive'
                                                                    ? 'bg-[#eef7e8] text-[#2e5e33]'
                                                                    : d.direction === 'Negative'
                                                                    ? 'bg-[#fae8e8] text-[#9c2e2e]'
                                                                    : 'bg-[#f7f6f1] text-[#687169]'
                                                            }`}
                                                        >
                                                            {d.direction}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-3 font-mono text-[#687169]">
                                                        {d.sample_size}
                                                    </td>
                                                    <td className="py-3 px-3">
                                                        <span
                                                            className={`inline-block px-2 py-0.5 text-[10px] font-mono font-bold uppercase tracking-wider ${
                                                                isSupported
                                                                    ? 'bg-[#eef7e8] text-[#2e5e33] border border-[#aac69c]'
                                                                    : isInsufficient
                                                                    ? 'bg-[#fcf8e3] text-[#8a6d3b] border border-[#fbeed5]'
                                                                    : isLow
                                                                    ? 'bg-[#fae8e8] text-[#9c2e2e] border border-[#f5c6cb]'
                                                                    : 'bg-[#f7f6f1] text-[#18221d] border border-[#ccd1ca]'
                                                            }`}
                                                        >
                                                            {d.confidence}
                                                        </span>
                                                    </td>
                                                    <td className="py-3 px-3 text-[#18221d] text-[11px] leading-relaxed">
                                                        {d.explanation}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>

                            {/* Progressive Disclosure: Technical Model Diagnostics */}
                            <div className="mt-6 border border-[#ccd1ca]/80 bg-[#f7f6f1]">
                                <button
                                    onClick={() => setShowDiagnosticsAccordion(!showDiagnosticsAccordion)}
                                    className="w-full p-3.5 flex items-center justify-between text-xs font-bold uppercase tracking-wider text-[#18221d] hover:bg-[#eae8e1] transition-colors"
                                >
                                    <div className="flex items-center gap-2">
                                        <Layers className="w-4 h-4 text-[#687169]" />
                                        <span>Especificaciones Técnicas del Modelo & Diagnósticos</span>
                                    </div>
                                    <ChevronDown
                                        className={`w-4 h-4 text-[#687169] transition-transform ${
                                            showDiagnosticsAccordion ? 'rotate-180' : ''
                                        }`}
                                    />
                                </button>

                                {showDiagnosticsAccordion && (
                                    <div className="p-4 border-t border-[#ccd1ca] bg-white text-xs space-y-3">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <span className="font-bold text-[#18221d] block mb-1">Especificación:</span>
                                                <p className="text-[#687169] leading-relaxed">
                                                    Tipo: <strong>{driverAnalysis.diagnostics?.model_type}</strong>
                                                </p>
                                                <p className="text-[#687169] leading-relaxed">
                                                    Variables controladas: <code>{driverAnalysis.controlled_variables.join(', ')}</code>
                                                </p>
                                            </div>

                                            <div>
                                                <span className="font-bold text-[#18221d] block mb-1">Categorías de Referencia (Línea Base):</span>
                                                <ul className="text-[#687169] list-disc list-inside space-y-0.5 font-mono text-[11px]">
                                                    {Object.entries(driverAnalysis.reference_categories || {}).map(([dim, ref]) => (
                                                        <li key={dim}>
                                                            {dim}: <strong>{ref}</strong>
                                                        </li>
                                                    ))}
                                                </ul>
                                            </div>
                                        </div>

                                        <div className="border-t border-[#ccd1ca]/60 pt-2 flex flex-wrap gap-4 text-[#687169] text-[11px] font-mono">
                                            <span>Muestra: <strong>{driverAnalysis.sample_size}</strong></span>
                                            {driverAnalysis.diagnostics?.r_squared && (
                                                <span>R²: <strong>{(driverAnalysis.diagnostics.r_squared * 100).toFixed(1)}%</strong></span>
                                            )}
                                            {driverAnalysis.diagnostics?.degrees_of_freedom && (
                                                <span>Grados de Libertad: <strong>{driverAnalysis.diagnostics.degrees_of_freedom}</strong></span>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* SHARED INTERACTIVE GEMINI CONSULTANT PANEL */}
            <div className="border border-[#ccd1ca] bg-white shadow-sm flex flex-col overflow-hidden mt-8">
                {/* Chat Header */}
                <div className="p-4 border-b border-[#ccd1ca] bg-[#f7f6f1] flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center space-x-2.5">
                        <div className="w-7 h-7 rounded bg-[#18221d] flex items-center justify-center text-white">
                            <Sparkles className="w-4 h-4" />
                        </div>
                        <div>
                            <h4 className="text-xs font-bold uppercase tracking-wider text-[#18221d]">
                                Consultor Analítico Gemini (IA)
                            </h4>
                            <span className="text-[10px] text-[#687169]">
                                {activeTab === 'forecast'
                                    ? 'Interpretación de tendencias, modelos estadísticos y diagnóstico de fiabilidad'
                                    : 'Interpretación de asociaciones estadísticas y contraste de representatividad muestral'}
                            </span>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-[#eef7e8] border border-[#aac69c] text-[#2e5e33] text-[10px] font-mono font-medium">
                            <ShieldCheck className="w-3 h-3" />
                            Gobernanza Matemática Activa
                        </span>
                    </div>
                </div>

                {/* Chat Messages Area */}
                <div className="p-6 space-y-5 max-h-[520px] overflow-y-auto bg-[#fafaf8]">
                    {chatMessages.map((msg) => {
                        const isUser = msg.role === 'user';
                        return (
                            <div
                                key={msg.id}
                                className={`flex flex-col ${isUser ? 'items-end' : 'items-start'}`}
                            >
                                <div className="flex items-center space-x-1.5 mb-1 text-[11px] text-[#687169]">
                                    {isUser ? (
                                        <>
                                            <UserIcon className="w-3 h-3 text-[#687169]" />
                                            <span className="font-bold">Usted</span>
                                        </>
                                    ) : (
                                        <>
                                            <Bot className="w-3.5 h-3.5 text-[#2e5e33]" />
                                            <span className="font-bold text-[#2e5e33]">Atlas Assistant (Gemini)</span>
                                        </>
                                    )}
                                </div>

                                <div
                                    className={`p-4 text-xs leading-relaxed border ${
                                        isUser
                                            ? 'bg-[#f7f6f1] border-[#ccd1ca] text-[#18221d] max-w-[85%] whitespace-pre-wrap'
                                            : 'bg-white border-[#ccd1ca] text-[#18221d] shadow-xs max-w-[95%] md:max-w-[92%]'
                                    }`}
                                >
                                    {isUser ? (
                                        msg.content
                                    ) : (
                                        <MarkdownRenderer content={msg.content} />
                                    )}
                                </div>
                            </div>
                        );
                    })}

                    {chatLoading && (
                        <div className="flex flex-col items-start">
                            <div className="flex items-center space-x-1.5 mb-1 text-[11px] text-[#2e5e33]">
                                <Bot className="w-3.5 h-3.5" />
                                <span className="font-bold">Atlas Assistant (Gemini)</span>
                            </div>
                            <div className="p-3 bg-white border border-[#ccd1ca] text-xs text-[#687169] flex items-center space-x-2">
                                <div className="w-2 h-2 rounded-full bg-[#2e5e33] animate-ping" />
                                <span>Analizando factores matemáticos y preparando respuesta...</span>
                            </div>
                        </div>
                    )}

                    <div ref={chatBottomRef} />
                </div>

                {/* Suggested Prompts Pills */}
                <div className="px-6 py-2.5 bg-white border-t border-[#ccd1ca]/60 flex flex-wrap items-center gap-2">
                    <span className="text-[10px] font-bold uppercase tracking-wider text-[#687169] mr-1">
                        Preguntas Sugeridas:
                    </span>
                    {(activeTab === 'forecast' ? FORECAST_PROMPTS : DRIVER_PROMPTS).map((prompt, i) => (
                        <button
                            key={i}
                            onClick={() => handleSendChatMessage(prompt)}
                            disabled={chatLoading}
                            className="text-[11px] px-2.5 py-1 bg-[#f7f6f1] hover:bg-[#dce4d8] border border-[#ccd1ca] text-[#18221d] transition-colors disabled:opacity-50"
                        >
                            &rarr; {prompt}
                        </button>
                    ))}
                </div>

                {/* Interactive Composer Bar */}
                <div className="p-4 border-t border-[#ccd1ca] bg-[#f7f6f1]">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleSendChatMessage();
                        }}
                        className="flex items-center space-x-2"
                    >
                        <input
                            type="text"
                            value={chatInput}
                            onChange={(e) => setChatInput(e.target.value)}
                            placeholder={
                                activeTab === 'forecast'
                                    ? 'Haz una pregunta a Gemini sobre este pronóstico o su fiabilidad...'
                                    : 'Pregunta a Gemini sobre las asociaciones de drivers y recomendaciones operativas...'
                            }
                            disabled={chatLoading}
                            className="flex-1 px-4 py-2.5 bg-white border border-[#ccd1ca] text-xs text-[#18221d] focus:outline-none focus:border-[#18221d]"
                        />
                        <button
                            type="submit"
                            disabled={chatLoading || !chatInput.trim()}
                            className="px-5 py-2.5 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black transition-colors disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <Send className="w-3.5 h-3.5" />
                            <span>{chatLoading ? 'Consultando...' : 'Preguntar'}</span>
                        </button>
                    </form>
                    <span className="text-[10px] text-[#687169] block mt-2 font-mono">
                        * Nota de gobernanza: Gemini interpreta resultados estadísticos generados por Atlas; no altera el pronóstico matemático ni atribuye causalidad operativa.
                    </span>
                </div>
            </div>
        </AppLayout>
    );
}
