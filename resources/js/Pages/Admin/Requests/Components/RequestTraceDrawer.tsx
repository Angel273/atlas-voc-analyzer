import React, { useEffect, useState } from 'react';
import { X, Clock, Zap, Cpu, Database, AlertTriangle, Lightbulb, CheckCircle2, Copy, Check } from 'lucide-react';

interface ToolCall {
    id: string;
    tool_name: string;
    tool_version?: string;
    arguments: any;
    query_dsl?: any;
    result: any;
    status: string;
    duration_ms: number;
    citations?: any[];
    requested_at?: string;
    executed_at?: string;
}

interface RunDetail {
    id: string;
    conversation_id?: string;
    user?: { id: number; name: string; email: string };
    user_prompt: string;
    provider: string;
    model: string;
    prompt_version: string;
    tool_schema_version: string;
    status: string;
    tokens_used: number;
    latency_ms: number;
    started_at?: string;
    completed_at?: string;
    created_at?: string;
    tool_calls: ToolCall[];
    assistant_response?: string;
    grounding_citations?: any[];
    diagnostics?: {
        is_high_consumption: boolean;
        warnings: string[];
        recommendations: string[];
        tool_frequency: Record<string, number>;
    };
}

interface Props {
    runId: string | null;
    onClose: () => void;
}

export default function RequestTraceDrawer({ runId, onClose }: Props) {
    const [run, setRun] = useState<RunDetail | null>(null);
    const [loading, setLoading] = useState<boolean>(false);
    const [error, setError] = useState<string | null>(null);
    const [inspectingTool, setInspectingTool] = useState<number | null>(null);
    const [copied, setCopied] = useState<boolean>(false);

    useEffect(() => {
        if (!runId) {
            setRun(null);
            return;
        }

        setLoading(true);
        setError(null);
        setInspectingTool(null);

        fetch(`/admin/requests/${runId}`, {
            headers: {
                'Accept': 'application/json',
            },
        })
            .then(async (res) => {
                if (!res.ok) {
                    const data = await res.json();
                    throw new Error(data.message || 'Error al cargar el detalle del request');
                }
                return res.json();
            })
            .then((data) => {
                setRun(data.run);
            })
            .catch((err) => {
                setError(err.message);
            })
            .finally(() => {
                setLoading(false);
            });
    }, [runId]);

    if (!runId) return null;

    const copyRunId = () => {
        if (run?.id) {
            navigator.clipboard.writeText(run.id);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const getTokenSeverity = (tokens: number) => {
        if (tokens >= 60000) return 'bg-red-100 text-red-800 border-red-300';
        if (tokens >= 30000) return 'bg-amber-100 text-amber-900 border-amber-300';
        if (tokens >= 10000) return 'bg-yellow-100 text-yellow-800 border-yellow-300';
        return 'bg-emerald-100 text-emerald-800 border-emerald-300';
    };

    return (
        <div className="fixed inset-0 z-50 overflow-hidden bg-black/40 backdrop-blur-xs flex justify-end transition-opacity">
            <div className="w-full max-w-3xl bg-[#f7f6f1] h-full shadow-2xl flex flex-col border-l border-[#ccd1ca] animate-in slide-in-from-right duration-200">
                {/* Header */}
                <div className="p-5 border-b border-[#ccd1ca] bg-white flex items-center justify-between flex-shrink-0">
                    <div className="flex items-center space-x-3">
                        <div className="w-8 h-8 rounded-full bg-[#18221d] text-white flex items-center justify-center font-serif text-sm">
                            A
                        </div>
                        <div>
                            <div className="flex items-center space-x-2">
                                <h3 className="font-serif text-xl text-[#18221d]">Inspección de Request ReAct</h3>
                                {run && (
                                    <span
                                        className={`text-[10px] font-mono font-bold uppercase px-2 py-0.5 border ${
                                            run.status === 'completed'
                                                ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                                : 'bg-red-100 text-red-800 border-red-300'
                                        }`}
                                    >
                                        {run.status === 'completed' ? 'Completado' : run.status}
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center space-x-2 text-[11px] font-mono text-[#687169] mt-0.5">
                                <span>ID: {runId.substring(0, 13)}...</span>
                                <button
                                    type="button"
                                    onClick={copyRunId}
                                    className="hover:text-[#18221d] flex items-center gap-1"
                                    title="Copiar UUID completo"
                                >
                                    {copied ? <Check className="w-3 h-3 text-emerald-600" /> : <Copy className="w-3 h-3" />}
                                </button>
                            </div>
                        </div>
                    </div>

                    <button
                        onClick={onClose}
                        className="p-2 border border-[#ccd1ca] hover:bg-[#f7f6f1] text-[#18221d] transition-colors"
                        title="Cerrar"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto p-6 space-y-6">
                    {loading && (
                        <div className="flex items-center justify-center py-20 text-[#687169] text-xs font-mono">
                            <Clock className="w-5 h-5 animate-spin mr-2" />
                            Cargando traza y telemetría del request...
                        </div>
                    )}

                    {error && (
                        <div className="p-4 bg-red-50 border border-red-200 text-red-800 text-xs font-sans">
                            {error}
                        </div>
                    )}

                    {!loading && run && (
                        <>
                            {/* Key Metadata Badges */}
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div className="p-3 bg-white border border-[#ccd1ca]">
                                    <span className="text-[10px] font-bold uppercase tracking-wider text-[#687169] block">
                                        Tokens Consumidos
                                    </span>
                                    <span
                                        className={`font-serif text-2xl inline-block mt-0.5 px-2 py-0.5 border ${getTokenSeverity(
                                            run.tokens_used
                                        )}`}
                                    >
                                        {run.tokens_used.toLocaleString()}
                                    </span>
                                </div>

                                <div className="p-3 bg-white border border-[#ccd1ca]">
                                    <span className="text-[10px] font-bold uppercase tracking-wider text-[#687169] block">
                                        Latencia ReAct
                                    </span>
                                    <span className="font-serif text-2xl text-[#18221d] block mt-0.5">
                                        {run.latency_ms ? `${run.latency_ms.toLocaleString()} ms` : 'N/A'}
                                    </span>
                                </div>

                                <div className="p-3 bg-white border border-[#ccd1ca]">
                                    <span className="text-[10px] font-bold uppercase tracking-wider text-[#687169] block">
                                        Herramientas
                                    </span>
                                    <span className="font-serif text-2xl text-[#18221d] block mt-0.5">
                                        {run.tool_calls.length} llamadas
                                    </span>
                                </div>

                                <div className="p-3 bg-white border border-[#ccd1ca]">
                                    <span className="text-[10px] font-bold uppercase tracking-wider text-[#687169] block">
                                        Modelo / Proveedor
                                    </span>
                                    <span className="text-xs font-mono font-bold text-[#18221d] block mt-1 truncate">
                                        {run.model}
                                    </span>
                                    <span className="text-[10px] text-[#687169] font-mono block">
                                        {run.provider}
                                    </span>
                                </div>
                            </div>

                            {/* User & Prompt Box */}
                            <div className="p-4 bg-white border border-[#ccd1ca]">
                                <div className="flex items-center justify-between text-xs text-[#687169] mb-2 font-mono">
                                    <span className="font-semibold text-[#18221d]">
                                        Usuario: {run.user?.name || 'Anónimo'} ({run.user?.email || 'N/A'})
                                    </span>
                                    <span>
                                        {run.created_at ? new Date(run.created_at).toLocaleString() : ''}
                                    </span>
                                </div>
                                <div className="text-[11px] font-bold uppercase tracking-wider text-[#687169] mb-1">
                                    Prompt / Consulta realizada por el usuario:
                                </div>
                                <div className="p-3 bg-[#f7f6f1] border border-[#ccd1ca] font-sans text-sm text-[#18221d] leading-relaxed whitespace-pre-wrap">
                                    {run.user_prompt}
                                </div>
                            </div>

                            {/* Efficiency Diagnostics Alert */}
                            {run.diagnostics?.is_high_consumption && (
                                <div className="p-4 bg-amber-50/80 border border-amber-300 text-xs">
                                    <div className="flex items-center space-x-2 text-amber-900 font-bold mb-2">
                                        <AlertTriangle className="w-4 h-4 text-amber-700" />
                                        <span>Diagnóstico de Eficiencia de Tools</span>
                                    </div>
                                    <div className="space-y-1.5 text-amber-950">
                                        {run.diagnostics.warnings.map((w, idx) => (
                                            <p key={idx} className="flex items-start gap-1.5 font-medium">
                                                <span className="text-amber-700">•</span>
                                                {w}
                                            </p>
                                        ))}
                                        {run.diagnostics.recommendations.map((r, idx) => (
                                            <div key={idx} className="mt-2 pt-2 border-t border-amber-200 flex items-start gap-1.5 text-emerald-900 bg-emerald-50/60 p-2 border border-emerald-200">
                                                <Lightbulb className="w-4 h-4 text-emerald-700 flex-shrink-0 mt-0.5" />
                                                <span className="font-medium">{r}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* ReAct Tool Trace Sequence */}
                            <div>
                                <div className="flex items-center justify-between mb-3">
                                    <div className="flex items-center space-x-2 text-xs font-mono uppercase tracking-wider text-[#18221d]">
                                        <Cpu className="w-4 h-4 text-[#18221d]" />
                                        <span className="font-bold">Cadena de Ejecución de Tools ({run.tool_calls.length})</span>
                                    </div>
                                    <span className="text-[10px] text-[#687169] font-mono">
                                        Línea de tiempo secuencial
                                    </span>
                                </div>

                                {run.tool_calls.length === 0 ? (
                                    <div className="p-4 bg-white border border-[#ccd1ca] text-center text-xs text-[#687169]">
                                        Esta solicitud no invocó herramientas externas (resolución directa por LLM).
                                    </div>
                                ) : (
                                    <div className="space-y-2">
                                        {run.tool_calls.map((t, idx) => {
                                            const isSelected = inspectingTool === idx;
                                            return (
                                                <div
                                                    key={t.id || idx}
                                                    className="bg-white border border-[#ccd1ca] overflow-hidden"
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() => setInspectingTool(isSelected ? null : idx)}
                                                        className="w-full text-left p-3 flex items-center justify-between hover:bg-[#f7f6f1] transition-colors"
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <span className="w-5 h-5 rounded-full bg-[#f7f6f1] border border-[#ccd1ca] flex items-center justify-center text-[10px] font-mono text-[#687169]">
                                                                {idx + 1}
                                                            </span>
                                                            <span className="font-mono text-xs font-bold text-[#18221d]">
                                                                {t.tool_name}
                                                            </span>
                                                            <span className="text-[10px] font-mono px-1.5 py-0.5 border bg-[#f7f6f1] border-[#ccd1ca] text-[#687169] flex items-center gap-1">
                                                                <Clock className="w-2.5 h-2.5" />
                                                                {t.duration_ms}ms
                                                            </span>
                                                            <span
                                                                className={`text-[9px] font-mono px-1.5 py-0.5 font-semibold uppercase ${
                                                                    t.status === 'success' || t.status === 'completed'
                                                                        ? 'bg-emerald-100 text-emerald-800'
                                                                        : 'bg-red-100 text-red-800'
                                                                }`}
                                                            >
                                                                {t.status === 'success' || t.status === 'completed' ? 'ÉXITO' : 'ERROR'}
                                                            </span>
                                                        </div>

                                                        <span className="text-[11px] font-mono text-[#687169] underline decoration-[#ccd1ca]">
                                                            {isSelected ? 'Ocultar payload' : 'Inspeccionar'}
                                                        </span>
                                                    </button>

                                                    {isSelected && (
                                                        <div className="p-3 bg-[#18221d] text-white text-[10px] font-mono border-t border-[#ccd1ca] overflow-x-auto space-y-3">
                                                            <div>
                                                                <span className="text-[#d7f45b] block font-bold mb-1">
                                                                    // Parámetros y Argumentos Enviados por el LLM:
                                                                </span>
                                                                <pre className="bg-black/40 p-2 rounded-none max-h-48 overflow-y-auto">
                                                                    {JSON.stringify(t.arguments, null, 2)}
                                                                </pre>
                                                            </div>

                                                            {t.query_dsl && (
                                                                <div>
                                                                    <span className="text-[#d7f45b] block font-bold mb-1">
                                                                        // Query DSL Subyacente:
                                                                    </span>
                                                                    <pre className="bg-black/40 p-2 rounded-none max-h-48 overflow-y-auto">
                                                                        {JSON.stringify(t.query_dsl, null, 2)}
                                                                    </pre>
                                                                </div>
                                                            )}

                                                            <div>
                                                                <span className="text-[#d7f45b] block font-bold mb-1">
                                                                    // Resultado / Respuesta Devuelta al Modelo:
                                                                </span>
                                                                <pre className="bg-black/40 p-2 rounded-none max-h-56 overflow-y-auto">
                                                                    {JSON.stringify(t.result, null, 2)}
                                                                </pre>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>

                            {/* Assistant Final Delivered Answer */}
                            {run.assistant_response && (
                                <div className="p-4 bg-white border border-[#ccd1ca]">
                                    <div className="flex items-center space-x-2 text-xs font-mono uppercase tracking-wider text-[#18221d] mb-2">
                                        <Database className="w-4 h-4 text-[#18221d]" />
                                        <span className="font-bold">Respuesta Final Entregada al Usuario</span>
                                    </div>
                                    <div className="p-3 bg-[#f7f6f1] border border-[#ccd1ca] font-sans text-xs leading-relaxed text-[#18221d] whitespace-pre-wrap max-h-72 overflow-y-auto">
                                        {run.assistant_response}
                                    </div>
                                </div>
                            )}
                        </>
                    )}
                </div>

                {/* Footer */}
                <div className="p-4 border-t border-[#ccd1ca] bg-white flex justify-end flex-shrink-0">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-5 py-2 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black"
                    >
                        Cerrar Inspección
                    </button>
                </div>
            </div>
        </div>
    );
}
