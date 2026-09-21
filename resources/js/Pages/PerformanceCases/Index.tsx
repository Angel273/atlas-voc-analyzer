import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { 
    Plus, Search, Filter, AlertTriangle, CheckCircle2, Clock, 
    ArrowRight, UserCheck, Shield, ChevronRight, BarChart2
} from 'lucide-react';

interface WorkforceMember {
    id: number;
    name: string;
    role: string;
    external_id?: string;
}

interface AssignedUser {
    id: number;
    name: string;
}

interface LatestRecalc {
    id: number;
    version_number: number;
    nps_score: number | null;
    csat_score: number | null;
    survey_volume: number;
}

interface PerformanceCaseItem {
    id: number;
    case_number: string;
    workforce_member: WorkforceMember;
    assigned_to?: AssignedUser;
    type: string;
    priority: string;
    status: string;
    reason: string;
    opened_at: string;
    next_review_at?: string;
    closed_at?: string;
    baseline_metrics?: {
        nps?: number;
        csat?: number;
        volume?: number;
    };
    latest_recalculation?: LatestRecalc;
}

interface Props {
    cases?: {
        data: PerformanceCaseItem[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters?: {
        status?: string;
        priority?: string;
        type?: string;
        responsible_id?: string;
        search?: string;
    };
    stats?: {
        open_count: number;
        under_review_count: number;
        resolved_count: number;
        high_priority_count: number;
    };
    responsibles?: Array<{ id: number; name: string }>;
    users?: Array<{ id: number; name: string }>;
}

export default function PerformanceCasesIndex(props: Props) {
    const cases = props.cases || { data: [], links: [], current_page: 1, last_page: 1, total: 0 };
    const filters = props.filters || {};
    const stats = props.stats || { open_count: 0, under_review_count: 0, resolved_count: 0, high_priority_count: 0 };
    const responsibles = props.responsibles || props.users || [];

    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [priority, setPriority] = useState(filters.priority || '');
    const [type, setType] = useState(filters.type || '');
    const [responsibleId, setResponsibleId] = useState(filters.responsible_id || '');

    const applyFilters = (newFilters: Record<string, string>) => {
        router.get('/performance-cases', {
            search,
            status,
            priority,
            type,
            responsible_id: responsibleId,
            ...newFilters,
        }, { preserveState: true, replace: true });
    };

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({ search });
    };

    return (
        <AppLayout
            title="Seguimiento de Casos"
            kicker="HERRAMIENTA 004 · SEGUIMIENTO DE DESEMPEÑO"
            description="Gestión y acompañamiento continuo de agentes y supervisores con recálculos versionados y resguardo disciplinario."
            actions={
                <Link
                    href="/performance-cases/create"
                    className="inline-flex items-center gap-2 px-4 py-2 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider rounded shadow-sm hover:bg-[#283830] transition-colors"
                >
                    <Plus className="w-4 h-4" />
                    Nuevo Caso
                </Link>
            }
        >
            <Head title="Seguimiento de Casos de Desempeño" />

            <div className="space-y-6">
                {/* Metric Summary Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white border border-[#ccd1ca] p-4 rounded shadow-sm">
                        <div className="flex items-center justify-between text-[#687169] text-xs font-bold uppercase tracking-wider mb-1">
                            <span>Abiertos</span>
                            <Clock className="w-4 h-4 text-amber-600" />
                        </div>
                        <div className="text-2xl font-serif text-[#18221d] font-bold">{stats.open_count}</div>
                        <p className="text-[11px] text-[#687169] mt-0.5">Casos en acompañamiento inicial</p>
                    </div>

                    <div className="bg-white border border-[#ccd1ca] p-4 rounded shadow-sm">
                        <div className="flex items-center justify-between text-[#687169] text-xs font-bold uppercase tracking-wider mb-1">
                            <span>En Revisión</span>
                            <BarChart2 className="w-4 h-4 text-blue-600" />
                        </div>
                        <div className="text-2xl font-serif text-[#18221d] font-bold">{stats.under_review_count}</div>
                        <p className="text-[11px] text-[#687169] mt-0.5">Con sesiones y métricas activas</p>
                    </div>

                    <div className="bg-white border border-[#ccd1ca] p-4 rounded shadow-sm">
                        <div className="flex items-center justify-between text-[#687169] text-xs font-bold uppercase tracking-wider mb-1">
                            <span>Alta Prioridad</span>
                            <AlertTriangle className="w-4 h-4 text-red-600" />
                        </div>
                        <div className="text-2xl font-serif text-[#18221d] font-bold">{stats.high_priority_count}</div>
                        <p className="text-[11px] text-[#687169] mt-0.5">Requieren atención inmediata</p>
                    </div>

                    <div className="bg-white border border-[#ccd1ca] p-4 rounded shadow-sm">
                        <div className="flex items-center justify-between text-[#687169] text-xs font-bold uppercase tracking-wider mb-1">
                            <span>Resueltos</span>
                            <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                        </div>
                        <div className="text-2xl font-serif text-[#18221d] font-bold">{stats.resolved_count}</div>
                        <p className="text-[11px] text-[#687169] mt-0.5">Objetivos cumplidos satisfactoriamente</p>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white border border-[#ccd1ca] p-4 rounded shadow-sm space-y-3">
                    <form onSubmit={handleSearchSubmit} className="flex flex-col md:flex-row gap-3">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3 top-3 text-[#687169]" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar por número de caso, colaborador o BMS..."
                                className="w-full pl-9 pr-4 py-2 border border-[#ccd1ca] rounded text-sm focus:outline-none focus:border-[#18221d] bg-[#fafaf8]"
                            />
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                            <select
                                value={status}
                                onChange={(e) => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }}
                                className="px-3 py-2 border border-[#ccd1ca] rounded text-xs bg-white focus:outline-none focus:border-[#18221d]"
                            >
                                <option value="">Todos los Estados</option>
                                <option value="open">Abierto</option>
                                <option value="under_review">En Revisión</option>
                                <option value="resolved">Resuelto</option>
                                <option value="closed">Cerrado</option>
                            </select>

                            <select
                                value={priority}
                                onChange={(e) => { setPriority(e.target.value); applyFilters({ priority: e.target.value }); }}
                                className="px-3 py-2 border border-[#ccd1ca] rounded text-xs bg-white focus:outline-none focus:border-[#18221d]"
                            >
                                <option value="">Todas las Prioridades</option>
                                <option value="high">Alta</option>
                                <option value="medium">Media</option>
                                <option value="low">Baja</option>
                            </select>

                            <select
                                value={type}
                                onChange={(e) => { setType(e.target.value); applyFilters({ type: e.target.value }); }}
                                className="px-3 py-2 border border-[#ccd1ca] rounded text-xs bg-white focus:outline-none focus:border-[#18221d]"
                            >
                                <option value="">Todos los Tipos</option>
                                <option value="performance">Desempeño</option>
                                <option value="quality">Calidad</option>
                                <option value="conduct">Conducta</option>
                            </select>

                            <select
                                value={responsibleId}
                                onChange={(e) => { setResponsibleId(e.target.value); applyFilters({ responsible_id: e.target.value }); }}
                                className="px-3 py-2 border border-[#ccd1ca] rounded text-xs bg-white focus:outline-none focus:border-[#18221d]"
                            >
                                <option value="">Responsable: Todos</option>
                                {responsibles.map((r) => (
                                    <option key={r.id} value={r.id}>{r.name}</option>
                                ))}
                            </select>
                        </div>
                    </form>
                </div>

                {/* Cases Table */}
                <div className="bg-white border border-[#ccd1ca] rounded shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr className="border-b border-[#ccd1ca] bg-[#f2f1ea] text-[#687169] uppercase tracking-wider font-semibold">
                                    <th className="py-3 px-4">N° Caso</th>
                                    <th className="py-3 px-4">Colaborador</th>
                                    <th className="py-3 px-4">Tipo / Prioridad</th>
                                    <th className="py-3 px-4">Motivo Principal</th>
                                    <th className="py-3 px-4 text-center">NPS Actual</th>
                                    <th className="py-3 px-4 text-center">CSAT Actual</th>
                                    <th className="py-3 px-4 text-center">Estado</th>
                                    <th className="py-3 px-4">Responsable</th>
                                    <th className="py-3 px-4 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#ecebe4]">
                                {cases.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="py-12 text-center text-[#687169]">
                                            No se encontraron casos de desempeño con los filtros actuales.
                                        </td>
                                    </tr>
                                ) : (
                                    cases.data.map((c) => {
                                        const currentNps = c.latest_recalculation?.nps_score ?? c.baseline_metrics?.nps ?? null;
                                        const currentCsat = c.latest_recalculation?.csat_score ?? c.baseline_metrics?.csat ?? null;

                                        return (
                                            <tr key={c.id} className="hover:bg-[#fafaf8] transition-colors">
                                                <td className="py-3.5 px-4 font-mono font-bold text-[#18221d]">
                                                    <Link href={`/performance-cases/${c.id}`} className="hover:underline text-[#18221d]">
                                                        {c.case_number}
                                                    </Link>
                                                </td>
                                                <td className="py-3.5 px-4">
                                                    <div className="font-semibold text-[#18221d]">{c.workforce_member.name}</div>
                                                    <div className="text-[10px] text-[#687169] flex items-center gap-1.5 mt-0.5">
                                                        <span className="uppercase">{c.workforce_member.role}</span>
                                                        {c.workforce_member.external_id && (
                                                            <span>· BMS {c.workforce_member.external_id}</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-3.5 px-4">
                                                    <div className="flex flex-col gap-1 items-start">
                                                        <span className="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-[#edf0ee] text-[#18221d]">
                                                            {c.type === 'performance' ? 'Desempeño' : (c.type === 'quality' ? 'Calidad' : 'Conducta')}
                                                        </span>
                                                        <span className={`text-[9px] uppercase font-bold tracking-wider px-1.5 py-0.2 rounded ${
                                                            c.priority === 'high' 
                                                                ? 'bg-red-100 text-red-800' 
                                                                : (c.priority === 'medium' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800')
                                                        }`}>
                                                            {c.priority}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="py-3.5 px-4 max-w-xs truncate text-[#334139]">
                                                    {c.reason}
                                                </td>
                                                <td className="py-3.5 px-4 text-center font-mono">
                                                    {currentNps !== null ? (
                                                        <span className={`font-semibold ${currentNps >= 0.5 ? 'text-emerald-700' : 'text-red-700'}`}>
                                                            {currentNps > 0 ? `+${currentNps.toFixed(2)}` : currentNps.toFixed(2)}
                                                        </span>
                                                    ) : (
                                                        <span className="text-[#9aa099]">—</span>
                                                    )}
                                                </td>
                                                <td className="py-3.5 px-4 text-center font-mono">
                                                    {currentCsat !== null ? (
                                                        <span className={`font-semibold ${currentCsat >= 0.8 ? 'text-emerald-700' : 'text-red-700'}`}>
                                                            {(currentCsat * 100).toFixed(1)}%
                                                        </span>
                                                    ) : (
                                                        <span className="text-[#9aa099]">—</span>
                                                    )}
                                                </td>
                                                <td className="py-3.5 px-4 text-center">
                                                    <span className={`inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                                                        c.status === 'open' 
                                                            ? 'bg-amber-100 text-amber-900 border border-amber-300' 
                                                            : (c.status === 'under_review' 
                                                                ? 'bg-blue-100 text-blue-900 border border-blue-300' 
                                                                : (c.status === 'resolved' 
                                                                    ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' 
                                                                    : 'bg-gray-100 text-gray-800 border border-gray-300'))
                                                    }`}>
                                                        {c.status.replace('_', ' ')}
                                                    </span>
                                                </td>
                                                <td className="py-3.5 px-4 text-[#4a524c]">
                                                    {c.assigned_to?.name || 'Sin asignar'}
                                                </td>
                                                <td className="py-3.5 px-4 text-right">
                                                    <Link
                                                        href={`/performance-cases/${c.id}`}
                                                        className="inline-flex items-center gap-1 text-[11px] font-bold text-[#18221d] hover:underline"
                                                    >
                                                        Detalle <ChevronRight className="w-3.5 h-3.5" />
                                                    </Link>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {cases.last_page > 1 && (
                        <div className="p-4 border-t border-[#ccd1ca] flex items-center justify-between text-xs text-[#687169]">
                            <div>
                                Mostrando página {cases.current_page} de {cases.last_page} ({cases.total} casos)
                            </div>
                            <div className="flex gap-1">
                                {cases.links.map((link, idx) => (
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
        </AppLayout>
    );
}
