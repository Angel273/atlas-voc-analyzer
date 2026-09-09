import React, { useState } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import RequestTraceDrawer from './Components/RequestTraceDrawer';
import {
    Activity,
    Search,
    Filter,
    Clock,
    Zap,
    AlertTriangle,
    Eye,
    ChevronLeft,
    ChevronRight,
    RotateCcw,
    Sparkles,
    SlidersHorizontal,
} from 'lucide-react';

interface RequestItem {
    id: string;
    conversation_id?: string;
    user?: { id: number; name: string; email: string };
    user_prompt: string;
    provider: string;
    model: string;
    status: string;
    tokens_used: number;
    latency_ms: number;
    tool_calls_count: number;
    tool_summary: Record<string, number>;
    created_at?: string;
    formatted_date: string;
    relative_date: string;
}

interface PaginationMeta {
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Props {
    requests: {
        data: RequestItem[];
        links: any[];
    } & PaginationMeta;
    kpis: {
        total_tokens: number;
        avg_tokens: number;
        max_tokens: number;
        avg_latency_ms: number;
        total_runs: number;
        tool_call_rate: number;
        peak_run?: {
            id: string;
            tokens_used: number;
            user_name: string;
            prompt: string;
        } | null;
    };
    filters: {
        user_id: string;
        token_tier: string;
        min_tokens: string;
        status: string;
        tool_name: string;
        search: string;
        date_from: string;
        date_to: string;
        sort_by: string;
        sort_direction: string;
    };
    users: { id: number; name: string; email: string }[];
    available_tools: string[];
}

export default function RequestsIndex({ requests, kpis, filters, users, available_tools }: Props) {
    const [inspectingRunId, setInspectingRunId] = useState<string | null>(null);
    const [localFilters, setLocalFilters] = useState(filters);

    const handleFilterChange = (key: keyof typeof filters, value: string) => {
        const updated = { ...localFilters, [key]: value };
        setLocalFilters(updated);

        router.get('/admin/requests', updated as any, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleResetFilters = () => {
        const cleared = {
            user_id: '',
            token_tier: '',
            min_tokens: '',
            status: '',
            tool_name: '',
            search: '',
            date_from: '',
            date_to: '',
            sort_by: 'tokens_used',
            sort_direction: 'desc',
        };
        setLocalFilters(cleared);
        router.get('/admin/requests', cleared, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const getTokenSeverity = (tokens: number) => {
        if (tokens >= 60000) return 'bg-red-100 text-red-800 border-red-300 font-bold';
        if (tokens >= 30000) return 'bg-amber-100 text-amber-900 border-amber-300 font-semibold';
        if (tokens >= 10000) return 'bg-yellow-100 text-yellow-800 border-yellow-300 font-medium';
        return 'bg-emerald-50 text-emerald-800 border-emerald-200 font-medium';
    };

    return (
        <AppLayout
            title="Histórico de Requests & Consumo de Tokens"
            kicker="TELEMETRÍA RE-ACT · ADMINISTRACIÓN DE AGENTE IA"
            description="Auditoría exhaustiva de consultas efectuadas por los usuarios al Asistente IA, trazabilidad de llamadas a tools y ranking de consumo de tokens para optimización continua."
        >
            <Head title="Histórico de Requests — ATLAS VOC Analysis" />

            {/* KPI Cards Row */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                {/* Total Tokens */}
                <div className="border border-[#ccd1ca] bg-white p-5">
                    <span className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#687169] block mb-1">
                        Total Tokens Consumidos
                    </span>
                    <div className="flex items-baseline space-x-2">
                        <span className="font-serif text-3xl md:text-4xl text-[#18221d]">
                            {kpis.total_tokens.toLocaleString()}
                        </span>
                        <span className="text-xs text-[#687169] font-mono">tokens</span>
                    </div>
                    <span className="text-[11px] text-[#687169] mt-2 block">
                        Acumulado de {kpis.total_runs} peticiones
                    </span>
                </div>

                {/* Avg Tokens / Request */}
                <div className="border border-[#ccd1ca] bg-white p-5">
                    <span className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#687169] block mb-1">
                        Promedio por Request
                    </span>
                    <div className="flex items-baseline space-x-2">
                        <span className="font-serif text-3xl md:text-4xl text-[#18221d]">
                            {kpis.avg_tokens.toLocaleString()}
                        </span>
                        <span className="text-xs text-[#687169] font-mono">tokens/req</span>
                    </div>
                    <span className="text-[11px] text-[#687169] mt-2 block">
                        Eficiencia media del asistente
                    </span>
                </div>

                {/* Peak Consumption Query */}
                <div className="border border-[#ccd1ca] bg-[#fff8dc]/50 p-5 relative overflow-hidden">
                    <span className="text-[11px] font-bold uppercase tracking-[0.16em] text-amber-900 block mb-1 flex items-center gap-1">
                        <AlertTriangle className="w-3 h-3 text-amber-700" />
                        Petición Más Costosa
                    </span>
                    <div className="flex items-baseline space-x-2">
                        <span className="font-serif text-3xl md:text-4xl text-amber-950 font-bold">
                            {kpis.max_tokens.toLocaleString()}
                        </span>
                        <span className="text-xs text-amber-800 font-mono">tokens</span>
                    </div>
                    {kpis.peak_run && (
                        <button
                            type="button"
                            onClick={() => setInspectingRunId(kpis.peak_run!.id)}
                            className="mt-2 text-[11px] font-semibold text-[#18221d] underline decoration-[#18221d]/40 hover:decoration-[#18221d] flex items-center gap-1"
                        >
                            <span>Inspeccionar caso crítico ({kpis.peak_run.user_name})</span>
                            <Eye className="w-3 h-3" />
                        </button>
                    )}
                </div>

                {/* ReAct Average Latency */}
                <div className="border border-[#ccd1ca] bg-white p-5">
                    <span className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#687169] block mb-1">
                        Latencia Media ReAct
                    </span>
                    <div className="flex items-baseline space-x-2">
                        <span className="font-serif text-3xl md:text-4xl text-[#18221d]">
                            {(kpis.avg_latency_ms / 1000).toFixed(1)}s
                        </span>
                        <span className="text-xs text-[#687169] font-mono">({kpis.avg_latency_ms} ms)</span>
                    </div>
                    <span className="text-[11px] text-[#687169] mt-2 block">
                        Tiempo total de extremo a extremo
                    </span>
                </div>

                {/* Tool Invocations Rate */}
                <div className="border border-[#ccd1ca] bg-white p-5">
                    <span className="text-[11px] font-bold uppercase tracking-[0.16em] text-[#687169] block mb-1">
                        Tasa de Invocación Tools
                    </span>
                    <div className="flex items-baseline space-x-2">
                        <span className="font-serif text-3xl md:text-4xl text-[#18221d]">
                            {kpis.tool_call_rate}%
                        </span>
                    </div>
                    <span className="text-[11px] text-[#687169] mt-2 block">
                        Requests que ejecutan herramientas
                    </span>
                </div>
            </div>

            {/* Filter & Search Toolbar */}
            <div className="border border-[#ccd1ca] bg-white p-5 mb-6">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-[#ccd1ca]/60">
                    <div className="flex items-center space-x-2 text-xs font-semibold uppercase tracking-wider text-[#18221d]">
                        <SlidersHorizontal className="w-4 h-4" />
                        <span>Filtros y Criterios de Ordenamiento</span>
                    </div>

                    <button
                        type="button"
                        onClick={handleResetFilters}
                        className="text-xs text-[#687169] hover:text-[#18221d] flex items-center gap-1 font-mono transition-colors"
                    >
                        <RotateCcw className="w-3 h-3" />
                        <span>Restablecer Filtros</span>
                    </button>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 pt-4">
                    {/* Search Prompt */}
                    <div className="lg:col-span-2">
                        <label className="block text-[10px] font-bold uppercase tracking-wider text-[#687169] mb-1">
                            Buscar en Consulta
                        </label>
                        <div className="relative">
                            <input
                                type="text"
                                placeholder="Palabras clave en prompt..."
                                value={localFilters.search}
                                onChange={(e) => handleFilterChange('search', e.target.value)}
                                className="w-full pl-8 pr-3 py-1.5 bg-[#f7f6f1] border border-[#ccd1ca] text-xs text-[#18221d]"
                            />
                            <Search className="w-3.5 h-3.5 text-[#687169] absolute left-2.5 top-2.5" />
                        </div>
                    </div>

                    {/* Filter User */}
                    <div>
                        <label className="block text-[10px] font-bold uppercase tracking-wider text-[#687169] mb-1">
                            Usuario
                        </label>
                        <select
                            value={localFilters.user_id}
                            onChange={(e) => handleFilterChange('user_id', e.target.value)}
                            className="w-full p-1.5 bg-[#f7f6f1] border border-[#ccd1ca] text-xs text-[#18221d]"
                        >
                            <option value="">Todos los usuarios</option>
                            {users.map((u) => (
                                <option key={u.id} value={u.id}>
                                    {u.name} ({u.email})
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Filter Token Tier */}
                    <div>
                        <label className="block text-[10px] font-bold uppercase tracking-wider text-[#687169] mb-1">
                            Consumo de Tokens
                        </label>
                        <select
                            value={localFilters.token_tier}
                            onChange={(e) => handleFilterChange('token_tier', e.target.value)}
                            className="w-full p-1.5 bg-[#f7f6f1] border border-[#ccd1ca] text-xs text-[#18221d]"
                        >
                            <option value="">Todos los rangos</option>
                            <option value="critical">Crítico (&gt; 60k tokens)</option>
                            <option value="high">Alto (30k - 60k tokens)</option>
                            <option value="medium">Medio (10k - 30k tokens)</option>
                            <option value="low">Bajo (&lt; 10k tokens)</option>
                        </select>
                    </div>

                    {/* Filter Tool Invoked */}
                    <div>
                        <label className="block text-[10px] font-bold uppercase tracking-wider text-[#687169] mb-1">
                            Tool Invocada
                        </label>
                        <select
                            value={localFilters.tool_name}
                            onChange={(e) => handleFilterChange('tool_name', e.target.value)}
                            className="w-full p-1.5 bg-[#f7f6f1] border border-[#ccd1ca] text-xs text-[#18221d]"
                        >
                            <option value="">Cualquier tool</option>
                            {available_tools.map((t) => (
                                <option key={t} value={t}>
                                    {t}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Sort By */}
                    <div>
                        <label className="block text-[10px] font-bold uppercase tracking-wider text-[#687169] mb-1">
                            Ordenar Por
                        </label>
                        <select
                            value={`${localFilters.sort_by}:${localFilters.sort_direction}`}
                            onChange={(e) => {
                                const [field, dir] = e.target.value.split(':');
                                const updated = { ...localFilters, sort_by: field, sort_direction: dir };
                                setLocalFilters(updated);
                                router.get('/admin/requests', updated as any, {
                                    preserveState: true,
                                    preserveScroll: true,
                                    replace: true,
                                });
                            }}
                            className="w-full p-1.5 bg-[#f7f6f1] border border-[#ccd1ca] text-xs text-[#18221d] font-semibold"
                        >
                            <option value="tokens_used:desc">Mayor Consumo de Tokens</option>
                            <option value="tokens_used:asc">Menor Consumo de Tokens</option>
                            <option value="latency_ms:desc">Mayor Latencia (ms)</option>
                            <option value="created_at:desc">Más Recientes Primero</option>
                            <option value="created_at:asc">Más Antiguos Primero</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Requests Table */}
            <div className="border border-[#ccd1ca] bg-white overflow-hidden shadow-xs mb-6">
                <div className="p-4 bg-[#f7f6f1] border-b border-[#ccd1ca] flex items-center justify-between">
                    <span className="text-xs font-semibold text-[#18221d]">
                        Mostrando {requests.from || 0} - {requests.to || 0} de {requests.total} requests registrados
                    </span>
                    <span className="text-[11px] font-mono text-[#687169]">
                        Orden actual: {localFilters.sort_by === 'tokens_used' ? 'Tokens (Descendente)' : localFilters.sort_by}
                    </span>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr className="bg-white border-b border-[#ccd1ca] text-[#687169] font-mono uppercase text-[10px]">
                                <th className="py-3 px-4">Fecha / Hora</th>
                                <th className="py-3 px-4">Usuario</th>
                                <th className="py-3 px-4 min-w-[280px]">Consulta / Prompt</th>
                                <th className="py-3 px-4 text-right">Tokens Consumidos</th>
                                <th className="py-3 px-4 text-right">Latencia</th>
                                <th className="py-3 px-4">Tools Ejecutadas</th>
                                <th className="py-3 px-4">Estado</th>
                                <th className="py-3 px-4 text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#ccd1ca]/60">
                            {requests.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="py-12 text-center text-xs text-[#687169] font-sans">
                                        No se encontraron requests que coincidan con los filtros aplicados.
                                    </td>
                                </tr>
                            ) : (
                                requests.data.map((r) => (
                                    <tr key={r.id} className="hover:bg-[#f7f6f1]/60 transition-colors">
                                        {/* Date */}
                                        <td className="py-3 px-4 font-mono text-[11px] text-[#18221d] whitespace-nowrap">
                                            <div>{r.formatted_date}</div>
                                            <div className="text-[10px] text-[#687169]">{r.relative_date}</div>
                                        </td>

                                        {/* User */}
                                        <td className="py-3 px-4 whitespace-nowrap">
                                            <div className="flex items-center space-x-2">
                                                <div className="w-6 h-6 rounded-full bg-[#18221d] text-white flex items-center justify-center text-[10px] font-serif">
                                                    {r.user?.name ? r.user.name.charAt(0).toUpperCase() : 'U'}
                                                </div>
                                                <div>
                                                    <div className="font-semibold text-[#18221d]">
                                                        {r.user?.name || 'Anónimo'}
                                                    </div>
                                                    <div className="text-[10px] text-[#687169] font-mono">
                                                        {r.user?.email || 'N/A'}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        {/* User Prompt */}
                                        <td className="py-3 px-4">
                                            <div
                                                className="text-xs text-[#18221d] font-sans line-clamp-2 max-w-md leading-relaxed"
                                                title={r.user_prompt}
                                            >
                                                {r.user_prompt}
                                            </div>
                                        </td>

                                        {/* Tokens Consumidos */}
                                        <td className="py-3 px-4 text-right whitespace-nowrap">
                                            <span
                                                className={`inline-block font-mono text-xs px-2.5 py-1 border ${getTokenSeverity(
                                                    r.tokens_used
                                                )}`}
                                            >
                                                {r.tokens_used.toLocaleString()}
                                            </span>
                                        </td>

                                        {/* Latency */}
                                        <td className="py-3 px-4 text-right font-mono text-xs text-[#18221d] whitespace-nowrap">
                                            {r.latency_ms ? `${(r.latency_ms / 1000).toFixed(1)}s` : '—'}
                                            <span className="block text-[10px] text-[#687169]">
                                                {r.latency_ms ? `${r.latency_ms} ms` : ''}
                                            </span>
                                        </td>

                                        {/* Tool Calls */}
                                        <td className="py-3 px-4">
                                            {r.tool_calls_count === 0 ? (
                                                <span className="text-[11px] text-[#687169] font-mono">—</span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1 max-w-xs">
                                                    {Object.entries(r.tool_summary).map(([toolName, count]) => (
                                                        <span
                                                            key={toolName}
                                                            className="text-[10px] font-mono px-1.5 py-0.5 bg-[#f7f6f1] border border-[#ccd1ca] text-[#18221d]"
                                                        >
                                                            {toolName} {count > 1 ? `×${count}` : ''}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </td>

                                        {/* Status */}
                                        <td className="py-3 px-4 whitespace-nowrap">
                                            <span
                                                className={`text-[9px] font-mono px-2 py-0.5 font-bold uppercase border ${
                                                    r.status === 'completed'
                                                        ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                                                        : 'bg-red-50 text-red-800 border-red-300'
                                                }`}
                                            >
                                                {r.status === 'completed' ? 'Éxito' : r.status}
                                            </span>
                                        </td>

                                        {/* Action */}
                                        <td className="py-3 px-4 text-center whitespace-nowrap">
                                            <button
                                                type="button"
                                                onClick={() => setInspectingRunId(r.id)}
                                                className="px-3 py-1.5 bg-[#18221d] text-white hover:bg-black text-xs font-mono font-medium flex items-center gap-1.5 transition-colors mx-auto"
                                            >
                                                <Eye className="w-3 h-3" />
                                                <span>Inspeccionar</span>
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination footer */}
                {requests.last_page > 1 && (
                    <div className="p-4 bg-[#f7f6f1] border-t border-[#ccd1ca] flex items-center justify-between">
                        <div className="text-xs text-[#687169]">
                            Página {requests.current_page} de {requests.last_page}
                        </div>

                        <div className="flex items-center space-x-2">
                            {requests.prev_page_url && (
                                <Link
                                    href={requests.prev_page_url}
                                    preserveState
                                    preserveScroll
                                    className="px-3 py-1.5 border border-[#ccd1ca] bg-white hover:bg-[#f7f6f1] text-xs font-medium text-[#18221d] flex items-center gap-1"
                                >
                                    <ChevronLeft className="w-3 h-3" />
                                    <span>Anterior</span>
                                </Link>
                            )}

                            {requests.next_page_url && (
                                <Link
                                    href={requests.next_page_url}
                                    preserveState
                                    preserveScroll
                                    className="px-3 py-1.5 border border-[#ccd1ca] bg-white hover:bg-[#f7f6f1] text-xs font-medium text-[#18221d] flex items-center gap-1"
                                >
                                    <span>Siguiente</span>
                                    <ChevronRight className="w-3 h-3" />
                                </Link>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* Deep Trace Drawer */}
            <RequestTraceDrawer
                runId={inspectingRunId}
                onClose={() => setInspectingRunId(null)}
            />
        </AppLayout>
    );
}
