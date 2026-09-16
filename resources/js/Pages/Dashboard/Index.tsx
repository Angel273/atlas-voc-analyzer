import React, { useState, useEffect, useRef } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import KpiCard from '@/Components/KpiCard';
import GridWidget from '@/Components/GridWidget';
import EChartComponent from '@/Components/EChartComponent';
import WidgetConfigSidebar from '@/Components/WidgetConfigSidebar';
import KpiGoalsModal, { KpiGoalsMap } from '@/Components/KpiGoalsModal';
import {
    Filter,
    RefreshCw,
    Plus,
    LayoutGrid,
    Check,
    Download,
    RotateCcw,
    Save,
    Cpu,
    FileText,
    AlertCircle,
    Sliders,
    Search,
    Target,
} from 'lucide-react';
import { Widget, Dashboard, WidgetType } from '@/types/dashboard';

interface Props {
    dashboard: Dashboard;
    dashboards: { id: number; name: string; is_default: boolean }[];
    filter_options: {
        supervisors: string[];
        waves: string[];
        categories: string[];
    };
    categorization_status?: {
        total: number;
        completed: number;
        percentage: number;
    };
}

const METRIC_META: Record<string, { label: string; defaultColor: string; isPercent: boolean }> = {
    nps: { label: 'NPS', defaultColor: '#18221d', isPercent: true },
    csat: { label: 'CSAT %', defaultColor: '#aac69c', isPercent: true },
    professionalism: { label: 'Professionalism %', defaultColor: '#d7bf70', isPercent: true },
    survey_volume: { label: 'Volumen', defaultColor: '#687169', isPercent: false },
};

const COLOR_PALETTE = ['#18221d', '#aac69c', '#d7bf70', '#e1a89e', '#687169', '#3b82f6'];

export default function DashboardIndex({
    dashboard,
    dashboards,
    filter_options,
    categorization_status,
}: Props) {
    const pageProps = usePage<{ kpi_goals?: KpiGoalsMap }>().props;
    const [goals, setGoals] = useState<KpiGoalsMap | null>(pageProps.kpi_goals || null);
    const [isGoalsModalOpen, setIsGoalsModalOpen] = useState<boolean>(false);

    useEffect(() => {
        if (pageProps.kpi_goals) {
            setGoals(pageProps.kpi_goals);
        }
    }, [pageProps.kpi_goals]);

    const gridRef = useRef<HTMLDivElement>(null);

    // Saved global filters from official dashboard record
    const savedFilters = dashboard.global_filters || {};

    // Filters state
    const [selectedSupervisor, setSelectedSupervisor] = useState<string>(savedFilters.supervisor || '');
    const [selectedWave, setSelectedWave] = useState<string>(savedFilters.wave || '');
    const [selectedCategory, setSelectedCategory] = useState<string>(savedFilters.category || '');
    const [dateFrom, setDateFrom] = useState<string>(savedFilters.dateFrom || savedFilters.date_from || '');
    const [dateTo, setDateTo] = useState<string>(savedFilters.dateTo || savedFilters.date_to || '');
    const [level, setLevel] = useState<'supervisors' | 'agents'>(savedFilters.level || 'supervisors');

    // Local in-table search for quick filtering
    const [tableSearch, setTableSearch] = useState<string>('');

    // Dashboard & Widgets state
    const [widgets, setWidgets] = useState<Widget[]>(dashboard.widgets || []);
    const [widgetData, setWidgetData] = useState<Record<number, any>>({});
    const [loadingWidgets, setLoadingWidgets] = useState<Record<number, boolean>>({});

    // Interactive & Governance mode
    const [isEditing, setIsEditing] = useState<boolean>(false);
    const [isLocalDirty, setIsLocalDirty] = useState<boolean>(false);
    const [isSaving, setIsSaving] = useState<boolean>(false);
    const [showAddModal, setShowAddModal] = useState<boolean>(false);
    const [selectedWidgetForConfig, setSelectedWidgetForConfig] = useState<Widget | null>(null);

    // HTML5 Drag and drop tracking
    const [draggedIndex, setDraggedIndex] = useState<number | null>(null);
    const [dragOverIndex, setDragOverIndex] = useState<number | null>(null);

    // Synchronize local widgets and filters when dashboard prop changes
    useEffect(() => {
        setWidgets(dashboard.widgets || []);
        const sf = dashboard.global_filters || {};
        setSelectedSupervisor(sf.supervisor || '');
        setSelectedWave(sf.wave || '');
        setSelectedCategory(sf.category || '');
        setDateFrom(sf.dateFrom || sf.date_from || '');
        setDateTo(sf.dateTo || sf.date_to || '');
        setLevel(sf.level || 'supervisors');
        setIsLocalDirty(false);
    }, [dashboard.id, JSON.stringify(dashboard.global_filters), dashboard.widgets]);

    // Fetch data for a specific widget via Query DSL
    const fetchWidgetData = async (widget: Widget) => {
        // Skip data fetching for text widgets
        if (widget.type === 'text') return;

        setLoadingWidgets((prev) => ({ ...prev, [widget.id]: true }));

        const filters: any[] = [];
        if (selectedSupervisor) {
            filters.push({ field: 'supervisor', operator: '=', value: selectedSupervisor });
        }
        if (selectedWave) {
            filters.push({ field: 'wave', operator: '=', value: selectedWave });
        }
        if (selectedCategory) {
            filters.push({ field: 'category', operator: '=', value: selectedCategory });
        }

        const dateRange = (dateFrom || dateTo) ? { from: dateFrom || undefined, to: dateTo || undefined } : undefined;

        let dsl: any = {
            filters,
            date_range: dateRange,
            ...widget.configuration,
        };

        if (widget.type === 'metric' || widget.type === 'kpi_card') {
            dsl = {
                metric: widget.configuration.metric || 'nps',
                aggregation: widget.configuration.aggregation || 'avg',
                filters,
                date_range: dateRange,
            };
        } else if (widget.type === 'trend' || widget.type === 'line_chart') {
            const metricsToFetch = (widget.configuration.metrics && widget.configuration.metrics.length > 0)
                ? widget.configuration.metrics
                : (widget.configuration.metric ? [widget.configuration.metric] : ['nps', 'csat']);
            dsl = {
                metrics: metricsToFetch,
                group_by: ['survey_date'],
                sort_by: 'survey_date',
                sort_order: 'asc',
                filters,
                date_range: dateRange,
            };
        } else if (widget.type === 'comparison' || widget.type === 'bar_chart') {
            const metricsToFetch = (widget.configuration.metrics && widget.configuration.metrics.length > 0)
                ? widget.configuration.metrics
                : [widget.configuration.metric || 'nps'];
            dsl = {
                metrics: metricsToFetch,
                group_by: [level === 'supervisors' ? 'supervisor' : 'agent'],
                filters,
                date_range: dateRange,
                limit: 12,
            };
        } else if (widget.type === 'category_breakdown') {
            dsl = {
                metric: 'survey_volume',
                group_by: ['category'],
                filters,
                date_range: dateRange,
            };
        } else if (widget.type === 'distribution' || widget.type === 'area_chart') {
            dsl = {
                metric: widget.configuration.metric || 'nps',
                group_by: [widget.configuration.dimension || 'supervisor'],
                filters,
                date_range: dateRange,
            };
        } else if (widget.type === 'table') {
            dsl = {
                metrics: ['nps', 'csat', 'professionalism', 'survey_volume'],
                group_by: [level === 'supervisors' ? 'supervisor' : 'agent'],
                filters,
                date_range: dateRange,
            };
        }

        try {
            const response = await fetch('/dashboard/query', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify(dsl),
            });
            const data = await response.json();
            setWidgetData((prev) => ({ ...prev, [widget.id]: data.data || [] }));
        } catch (err) {
            console.error(`Error querying widget ${widget.id}:`, err);
        } finally {
            setLoadingWidgets((prev) => ({ ...prev, [widget.id]: false }));
        }
    };

    // Refetch widgets on filter changes or widget configuration changes
    useEffect(() => {
        widgets.forEach((w) => fetchWidgetData(w));
    }, [selectedSupervisor, selectedWave, selectedCategory, dateFrom, dateTo, level, widgets.length]);

    const resetFilters = () => {
        setSelectedSupervisor('');
        setSelectedWave('');
        setSelectedCategory('');
        setDateFrom('');
        setDateTo('');
        setIsLocalDirty(true);
    };

    // 1. Resize Handler for GridWidget (Width & Height)
    const handleWidgetResize = (widgetId: number, newW: number, newH: number) => {
        setWidgets((prev) =>
            prev.map((w) => (w.id === widgetId ? { ...w, w: newW, h: newH } : w))
        );
        if (selectedWidgetForConfig?.id === widgetId) {
            setSelectedWidgetForConfig((prev) => (prev ? { ...prev, w: newW, h: newH } : null));
        }
        setIsLocalDirty(true);
    };

    // 2. HTML5 Drag & Drop Reordering
    const handleDragStart = (index: number, e: React.DragEvent) => {
        setDraggedIndex(index);
        e.dataTransfer.setData('text/plain', String(index));
    };

    const handleDragOver = (index: number, e: React.DragEvent) => {
        e.preventDefault();
        setDragOverIndex(index);
    };

    const handleDrop = (dropIndex: number) => {
        if (draggedIndex === null || draggedIndex === dropIndex) {
            setDraggedIndex(null);
            setDragOverIndex(null);
            return;
        }

        const reordered = [...widgets];
        const [movedItem] = reordered.splice(draggedIndex, 1);
        reordered.splice(dropIndex, 0, movedItem);

        setWidgets(reordered);
        setIsLocalDirty(true);
        setDraggedIndex(null);
        setDragOverIndex(null);
    };

    // 3. Save Widget Configuration from Sidebar
    const handleSaveWidgetConfig = async (updated: Widget) => {
        setWidgets((prev) => prev.map((w) => (w.id === updated.id ? updated : w)));
        if (selectedWidgetForConfig?.id === updated.id) {
            setSelectedWidgetForConfig(updated);
        }
        setIsLocalDirty(true);
        fetchWidgetData(updated);
    };

    // 4. Reset to Official (Revert Ephemeral Local Changes)
    const handleResetToOfficial = () => {
        setWidgets([...dashboard.widgets]);
        const sf = dashboard.global_filters || {};
        setSelectedSupervisor(sf.supervisor || '');
        setSelectedWave(sf.wave || '');
        setSelectedCategory(sf.category || '');
        setDateFrom(sf.date_from || sf.dateFrom || '');
        setDateTo(sf.date_to || sf.dateTo || '');
        setLevel(sf.level || 'supervisors');
        setIsLocalDirty(false);
    };

    // 5. Persist All Changes to Backend (Atomic Dashboard State & View Filters)
    const handleSaveChanges = async () => {
        setIsSaving(true);
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        try {
            const payload = {
                global_filters: {
                    level,
                    supervisor: selectedSupervisor || null,
                    wave: selectedWave || null,
                    category: selectedCategory || null,
                    date_from: dateFrom || null,
                    date_to: dateTo || null,
                },
                widgets: widgets.map((w, idx) => ({
                    id: w.id,
                    title: w.title,
                    type: w.type,
                    x: w.x,
                    y: w.y,
                    w: w.w,
                    h: w.h,
                    sort_order: idx + 1,
                    configuration: w.configuration,
                })),
            };

            const res = await fetch(`/dashboard/${dashboard.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (!res.ok) {
                const errData = await res.json().catch(() => ({}));
                throw new Error(errData.message || 'Error al guardar cambios');
            }

            const data = await res.json();
            if (data.dashboard) {
                setWidgets(data.dashboard.widgets || []);
                const sf = data.dashboard.global_filters || {};
                setSelectedSupervisor(sf.supervisor || '');
                setSelectedWave(sf.wave || '');
                setSelectedCategory(sf.category || '');
                setDateFrom(sf.date_from || sf.dateFrom || '');
                setDateTo(sf.date_to || sf.dateTo || '');
                setLevel(sf.level || 'supervisors');
            }

            setIsLocalDirty(false);
            router.reload({ only: ['dashboard'] });
        } catch (err) {
            console.error('Error saving dashboard changes:', err);
            alert('Error al guardar cambios en el servidor.');
        } finally {
            setIsSaving(false);
        }
    };

    // 6. Delete Widget
    const handleDeleteWidget = async (widgetId: number) => {
        try {
            await fetch(`/dashboard/${dashboard.id}/widgets/${widgetId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });
            setWidgets((prev) => prev.filter((w) => w.id !== widgetId));
            router.reload({ only: ['dashboard'] });
        } catch (err) {
            console.error(err);
        }
    };

    // 7. Client-side Direct CSV Export (UTF-8 with BOM)
    const handleExportCsv = () => {
        if (!widgets.length) return;
        const lines: string[] = ['Widget,Tipo,Dimensión,Métrica / Detalle,Valor / Resultado'];

        for (const w of widgets) {
            const rows = widgetData[w.id] || [];
            if (w.type === 'text') {
                const cleanText = (w.configuration.textContent || '').replace(/"/g, '""').replace(/\n/g, ' ');
                lines.push(`"${w.title}","text","","Instrucciones Operativas","${cleanText}"`);
                continue;
            }

            if (Array.isArray(rows)) {
                for (const row of rows) {
                    const dimVal =
                        (w.configuration.dimension && row[w.configuration.dimension]) ||
                        row.supervisor ||
                        row.agent ||
                        row.category ||
                        row.survey_date ||
                        'Total';
                    const metricKey = w.configuration.metric || 'nps';
                    const metricVal =
                        row[metricKey] !== undefined
                            ? row[metricKey]
                            : row.survey_volume || row.sample_count || '';
                    lines.push(
                        `"${w.title.replace(/"/g, '""')}","${w.type}","${String(dimVal).replace(/"/g, '""')}","${metricKey}","${metricVal}"`
                    );
                }
            }
        }

        const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const safeSlug = dashboard.name.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        link.download = `${safeSlug}-export-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(url);
    };

    return (
        <AppLayout
            title={dashboard.name}
            kicker="VOC MASTER OVERVIEW"
            description={
                dashboard.description ||
                'Continuous monitoring of Voice of Customer sentiment, agent performance, and verbatim feedback distributions.'
            }
            actions={
                <div className="flex flex-wrap items-center gap-2">
                    {categorization_status && categorization_status.total > 0 && (
                        <Link
                            href="/data"
                            className="px-3 py-1.5 text-xs border border-[#ccd1ca] bg-white hover:bg-[#f7f6f1] text-[#18221d] flex items-center gap-1.5 transition-colors"
                            title="Ver estado detallado de clasificación semántica con IA"
                        >
                            <Cpu className="w-3.5 h-3.5 text-[#687169]" />
                            <span>
                                IA Verbatims: {categorization_status.completed}/{categorization_status.total} (
                                {categorization_status.percentage}%)
                            </span>
                        </Link>
                    )}

                    {/* CSV Export */}
                    <button
                        type="button"
                        onClick={handleExportCsv}
                        className="px-3 py-2 text-xs font-semibold uppercase tracking-wider border border-[#ccd1ca] bg-white text-[#18221d] hover:bg-[#f7f6f1] flex items-center gap-1.5 transition-colors"
                        title="Exportar datos del tablero en formato CSV (UTF-8)"
                    >
                        <Download className="w-3.5 h-3.5 text-[#687169]" />
                        <span>Exportar CSV</span>
                    </button>

                    {/* Ephemeral Reset to Official Button */}
                    {isLocalDirty && (
                        <button
                            type="button"
                            onClick={handleResetToOfficial}
                            className="px-3 py-2 text-xs font-semibold uppercase tracking-wider border border-[#e1a89e] bg-[#fff0ed] text-[#943126] hover:bg-[#ffe5e0] flex items-center gap-1.5 transition-colors"
                            title="Descartar cambios locales no persistidos"
                        >
                            <RotateCcw className="w-3.5 h-3.5" />
                            <span>Restablecer</span>
                        </button>
                    )}

                    {/* Persist / Save Changes Button */}
                    <button
                        type="button"
                        disabled={isSaving}
                        onClick={handleSaveChanges}
                        className={`px-3.5 py-2 text-xs font-semibold uppercase tracking-wider border flex items-center gap-1.5 transition-colors shadow-sm ${
                            isLocalDirty
                                ? 'bg-[#d7f45b] text-[#18221d] border-[#18221d] hover:bg-[#cbf03f] ring-2 ring-[#d7f45b]/50'
                                : 'bg-white text-[#18221d] border-[#ccd1ca] hover:bg-[#f7f6f1]'
                        }`}
                        title="Guardar vista actual del dashboard (filtros, nivel y widgets)"
                    >
                        <Save className="w-3.5 h-3.5" />
                        <span>{isSaving ? 'Guardando...' : isLocalDirty ? 'Guardar Cambios *' : 'Guardar Vista'}</span>
                    </button>

                    {/* Metas / KPI Goals Modal Toggle */}
                    <button
                        type="button"
                        onClick={() => setIsGoalsModalOpen(true)}
                        className="px-3 py-2 text-xs font-semibold uppercase tracking-wider bg-white text-[#18221d] border border-[#ccd1ca] hover:border-[#18221d] hover:bg-[#f7f6f1] flex items-center gap-1.5 transition-colors cursor-pointer"
                        title="Definir o ajustar metas operacionales de NPS, CSAT y Profesionalismo"
                    >
                        <Target className="w-3.5 h-3.5 text-[#18221d]" />
                        <span>Metas Operacionales</span>
                    </button>

                    {/* Edit Layout Mode Toggle */}
                    <button
                        type="button"
                        onClick={() => setIsEditing(!isEditing)}
                        className={`px-3 py-2 text-xs font-semibold uppercase tracking-wider border flex items-center gap-1.5 transition-colors ${
                            isEditing
                                ? 'bg-[#18221d] text-white border-[#18221d]'
                                : 'bg-white border-[#ccd1ca] text-[#18221d] hover:bg-[#f7f6f1]'
                        }`}
                    >
                        <LayoutGrid className="w-3.5 h-3.5" />
                        <span>{isEditing ? 'Listo' : 'Editar Cuadrícula'}</span>
                    </button>

                    {/* Add Widget Button */}
                    {isEditing && (
                        <button
                            type="button"
                            onClick={() => setShowAddModal(true)}
                            className="px-3 py-2 text-xs font-semibold uppercase tracking-wider bg-[#18221d] text-white border border-[#18221d] flex items-center gap-1.5 hover:bg-[#2c3e34] transition-colors"
                        >
                            <Plus className="w-3.5 h-3.5 text-[#d7f45b]" />
                            <span>Añadir Widget</span>
                        </button>
                    )}
                </div>
            }
        >
            <Head title="Dashboard — ATLAS VOC Analysis" />

            {/* Ephemeral dirty status indicator */}
            {isLocalDirty && (
                <div className="mb-4 px-4 py-2.5 bg-[#fff8dc] border border-[#d7bf70] text-[#7a6418] text-xs flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <AlertCircle className="w-4 h-4 text-[#7a6418]" />
                        <span>
                            <strong>Modo Visualización Efímero:</strong> Tienes cambios locales no guardados en la cuadrícula o filtros de vista.
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleResetToOfficial}
                            className="underline hover:text-[#18221d] text-xs"
                        >
                            Restablecer a oficial
                        </button>
                        <span className="text-[#d7bf70]">|</span>
                        <button
                            onClick={handleSaveChanges}
                            className="font-bold underline hover:text-[#18221d] text-xs"
                        >
                            Guardar versión oficial
                        </button>
                    </div>
                </div>
            )}

            {/* Shared Filter Bar */}
            <div className="bg-white border border-[#ccd1ca] p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
                <div className="flex flex-wrap items-center gap-3">
                    {/* Agentes | Supervisores Segmented Switcher */}
                    <div className="inline-flex border border-[#ccd1ca] p-0.5 bg-[#f7f6f1]">
                        <button
                            type="button"
                            onClick={() => {
                                setLevel('supervisors');
                                setIsLocalDirty(true);
                            }}
                            className={`px-3 py-1.5 text-xs font-medium transition-colors ${
                                level === 'supervisors'
                                    ? 'bg-[#18221d] text-white font-semibold'
                                    : 'text-[#687169] hover:text-[#18221d]'
                            }`}
                        >
                            Supervisores
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setLevel('agents');
                                setIsLocalDirty(true);
                            }}
                            className={`px-3 py-1.5 text-xs font-medium transition-colors ${
                                level === 'agents'
                                    ? 'bg-[#18221d] text-white font-semibold'
                                    : 'text-[#687169] hover:text-[#18221d]'
                            }`}
                        >
                            Agentes
                        </button>
                    </div>

                    {/* Supervisor Dropdown */}
                    <select
                        value={selectedSupervisor}
                        onChange={(e) => {
                            setSelectedSupervisor(e.target.value);
                            setIsLocalDirty(true);
                        }}
                        className="text-xs bg-white border border-[#ccd1ca] px-3 py-2 text-[#18221d] focus:outline-none focus:border-[#18221d]"
                    >
                        <option value="">Todos los supervisores</option>
                        {filter_options.supervisors.map((s) => (
                            <option key={s} value={s}>
                                {s}
                            </option>
                        ))}
                    </select>

                    {/* Wave Dropdown */}
                    <select
                        value={selectedWave}
                        onChange={(e) => {
                            setSelectedWave(e.target.value);
                            setIsLocalDirty(true);
                        }}
                        className="text-xs bg-white border border-[#ccd1ca] px-3 py-2 text-[#18221d] focus:outline-none focus:border-[#18221d]"
                    >
                        <option value="">Todas las olas (Wave)</option>
                        {filter_options.waves.map((w) => (
                            <option key={w} value={w}>
                                {w}
                            </option>
                        ))}
                    </select>

                    {/* Category Dropdown */}
                    <select
                        value={selectedCategory}
                        onChange={(e) => {
                            setSelectedCategory(e.target.value);
                            setIsLocalDirty(true);
                        }}
                        className="text-xs bg-white border border-[#ccd1ca] px-3 py-2 text-[#18221d] focus:outline-none focus:border-[#18221d]"
                    >
                        <option value="">Todas las categorías</option>
                        {filter_options.categories.map((c) => (
                            <option key={c} value={c}>
                                {c}
                            </option>
                        ))}
                    </select>

                    {/* Date Range Inputs */}
                    <div className="flex items-center space-x-1.5 text-xs">
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => {
                                setDateFrom(e.target.value);
                                setIsLocalDirty(true);
                            }}
                            placeholder="From"
                            className="bg-white border border-[#ccd1ca] px-2.5 py-1.5 text-[#18221d] focus:outline-none"
                        />
                        <span className="text-[#687169]">→</span>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => {
                                setDateTo(e.target.value);
                                setIsLocalDirty(true);
                            }}
                            placeholder="To"
                            className="bg-white border border-[#ccd1ca] px-2.5 py-1.5 text-[#18221d] focus:outline-none"
                        />
                    </div>
                </div>

                <div className="flex items-center space-x-2">
                    {(selectedSupervisor || selectedWave || selectedCategory || dateFrom || dateTo) && (
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="text-xs text-[#687169] underline hover:text-[#18221d] px-2"
                        >
                            Limpiar filtros
                        </button>
                    )}
                    <button
                        type="button"
                        onClick={() => widgets.forEach((w) => fetchWidgetData(w))}
                        title="Actualizar métricas"
                        className="p-2 border border-[#ccd1ca] hover:bg-[#f7f6f1] text-[#18221d] transition-colors"
                    >
                        <RefreshCw className="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            {/* 12-Column Decoupled CSS Grid Canvas */}
            <div
                ref={gridRef}
                data-grid-container="true"
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(12, minmax(0, 1fr))',
                    gap: '1.5rem',
                }}
            >
                {widgets.map((widget, index) => {
                    const data = widgetData[widget.id] || [];
                    const isLoading = loadingWidgets[widget.id];
                    const cfg = widget.configuration || {};

                    // 1. Single Metric KPI Card Widget (kpi_card / metric)
                    if (widget.type === 'metric' || widget.type === 'kpi_card') {
                        const row = data[0] || {};
                        const metricKey = cfg.metric || 'nps';
                        let rawVal = row[metricKey];
                        let formattedVal = '—';
                        let unit = '';
                        let statusType: any = 'neutral';
                        let statusText = 'En meta';

                        if (rawVal !== undefined && rawVal !== null) {
                            if (metricKey === 'nps') {
                                const npsTarget = goals?.nps?.target_value ?? 0.50;
                                const npsWarn = goals?.nps?.warning_threshold ?? 0.20;
                                formattedVal = (rawVal * 100).toFixed(1);
                                statusType = rawVal >= npsTarget ? 'positive' : rawVal >= npsWarn ? 'warning' : 'negative';
                                statusText = rawVal >= npsTarget ? 'Sobre meta' : rawVal >= npsWarn ? 'En riesgo' : 'Debajo meta';
                            } else if (metricKey === 'csat') {
                                const csatTarget = goals?.csat?.target_value ?? 0.80;
                                const csatWarn = goals?.csat?.warning_threshold ?? 0.70;
                                formattedVal = (rawVal * 100).toFixed(1);
                                unit = '%';
                                statusType = rawVal >= csatTarget ? 'positive' : rawVal >= csatWarn ? 'warning' : 'negative';
                                statusText = rawVal >= csatTarget ? 'Sobre meta' : rawVal >= csatWarn ? 'En riesgo' : 'Debajo meta';
                            } else if (metricKey === 'professionalism') {
                                const profTarget = goals?.professionalism?.target_value ?? 0.85;
                                const profWarn = goals?.professionalism?.warning_threshold ?? 0.75;
                                formattedVal = (rawVal * 100).toFixed(1);
                                unit = '%';
                                statusType = rawVal >= profTarget ? 'positive' : rawVal >= profWarn ? 'warning' : 'negative';
                                statusText = rawVal >= profTarget ? 'Sobre meta' : rawVal >= profWarn ? 'En riesgo' : 'Debajo meta';
                            } else if (metricKey === 'survey_volume') {
                                formattedVal = Number(rawVal).toLocaleString();
                                unit = 'respuestas';
                                statusType = 'neutral';
                                statusText = 'Volumen';
                            }
                        }

                        const kpiGoalForMetric = goals?.[metricKey];
                        const defaultTargetDisplay = kpiGoalForMetric
                            ? (metricKey === 'nps'
                                ? `${kpiGoalForMetric.target_value >= 0 ? '+' : ''}${(kpiGoalForMetric.target_value * 100).toFixed(1)}%`
                                : `${(kpiGoalForMetric.target_value * 100).toFixed(1)}%`)
                            : undefined;

                        const displayTarget = cfg.showTargetLine && cfg.targetLineValue !== undefined
                            ? `${cfg.targetLineValue}%`
                            : defaultTargetDisplay;

                        // Accessible fallback table
                        const accessibleTable = (
                            <table className="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-[#ccd1ca] bg-[#f7f6f1]">
                                        <th className="py-2 px-3">Métrica</th>
                                        <th className="py-2 px-3 text-right">Valor</th>
                                        <th className="py-2 px-3 text-right">Muestra</th>
                                        <th className="py-2 px-3">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td className="py-2 px-3 font-semibold">{widget.title}</td>
                                        <td className="py-2 px-3 text-right font-mono">{formattedVal} {unit}</td>
                                        <td className="py-2 px-3 text-right font-mono">{row.sample_count || '—'}</td>
                                        <td className="py-2 px-3">{statusText}</td>
                                    </tr>
                                </tbody>
                            </table>
                        );

                        return (
                            <GridWidget
                                key={widget.id}
                                title={widget.title}
                                subtitle={cfg.showTargetLine ? `Meta: ${cfg.targetLineLabel || cfg.targetLineValue || 'Definida'}` : (displayTarget ? `Meta: ${displayTarget}` : undefined)}
                                colSpan={widget.w}
                                rowSpan={widget.h || 3}
                                isEditing={isEditing}
                                accessibleTable={accessibleTable}
                                onConfigure={() => setSelectedWidgetForConfig(widget)}
                                onDelete={() => handleDeleteWidget(widget.id)}
                                onResize={(w, h) => handleWidgetResize(widget.id, w, h)}
                                draggable={isEditing}
                                onDragStart={(e) => handleDragStart(index, e)}
                                onDragOver={(e) => handleDragOver(index, e)}
                                onDrop={() => handleDrop(index)}
                                className={dragOverIndex === index ? 'ring-2 ring-[#d7f45b]' : ''}
                            >
                                <KpiCard
                                    title={widget.title}
                                    value={isLoading ? '...' : formattedVal}
                                    unit={unit}
                                    sampleSize={row.sample_count || (metricKey === 'survey_volume' ? row.survey_volume : undefined)}
                                    target={displayTarget}
                                    statusText={statusText}
                                    statusType={statusType}
                                />
                            </GridWidget>
                        );
                    }

                    // 2. Trend Line Chart Widget (trend / line_chart)
                    if (widget.type === 'trend' || widget.type === 'line_chart') {
                        const selectedMetrics: string[] = (cfg.metrics && cfg.metrics.length > 0)
                            ? cfg.metrics
                            : (cfg.metric ? [cfg.metric] : ['nps', 'csat']);

                        const dates = data.map((d: any) => d.survey_date?.substring(5) || '');
                        const showTarget = Boolean(cfg.showTargetLine && cfg.targetLineValue !== undefined);
                        const hasVolume = selectedMetrics.includes('survey_volume');
                        const hasScores = selectedMetrics.some((m) => m !== 'survey_volume');

                        const series = selectedMetrics.map((mKey, idx) => {
                            const meta = METRIC_META[mKey] || { label: mKey.toUpperCase(), defaultColor: COLOR_PALETTE[idx % COLOR_PALETTE.length], isPercent: false };
                            const mColor = idx === 0 && cfg.color ? cfg.color : (meta.defaultColor || COLOR_PALETTE[idx % COLOR_PALETTE.length]);
                            const values = data.map((d: any) => {
                                if (d[mKey] === undefined || d[mKey] === null) return null;
                                return meta.isPercent ? +(d[mKey] * 100).toFixed(1) : d[mKey];
                            });

                            return {
                                name: meta.label,
                                type: 'line',
                                data: values,
                                smooth: true,
                                lineStyle: { color: mColor, width: 2.5 },
                                itemStyle: { color: mColor },
                                yAxisIndex: (hasVolume && hasScores && mKey === 'survey_volume') ? 1 : 0,
                                markLine: (idx === 0 && showTarget)
                                    ? {
                                          symbol: 'none',
                                          lineStyle: { type: 'dashed', color: '#943126', width: 1.5 },
                                          data: [
                                              {
                                                  yAxis: cfg.targetLineValue,
                                                  name: cfg.targetLineLabel || 'Meta',
                                              },
                                          ],
                                      }
                                    : undefined,
                            };
                        });

                        const chartOptions: any = {
                            tooltip: { trigger: 'axis' },
                            legend: { data: selectedMetrics.map((m) => METRIC_META[m]?.label || m), bottom: 0 },
                            grid: { left: 45, right: (hasVolume && hasScores) || showTarget ? 55 : 18, top: 24, bottom: 36 },
                            xAxis: { type: 'category', data: dates },
                            yAxis: hasVolume && hasScores
                                ? [
                                      { type: 'value', name: 'Score %', max: 100 },
                                      { type: 'value', name: 'Respuestas', position: 'right' },
                                  ]
                                : { type: 'value' },
                            series,
                        };

                        // Accessible WCAG Table
                        const accessibleTable = (
                            <table className="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-[#ccd1ca] bg-[#f7f6f1]">
                                        <th className="py-2 px-3">Fecha</th>
                                        {selectedMetrics.map((m) => (
                                            <th key={m} className="py-2 px-3 text-right">
                                                {METRIC_META[m]?.label || m}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#ccd1ca]/60 font-mono">
                                    {data.map((d: any, i: number) => (
                                        <tr key={i}>
                                            <td className="py-1.5 px-3 font-sans">{d.survey_date || '—'}</td>
                                            {selectedMetrics.map((m) => {
                                                const isPerc = METRIC_META[m]?.isPercent ?? false;
                                                const val = d[m];
                                                return (
                                                    <td key={m} className="py-1.5 px-3 text-right">
                                                        {val !== undefined && val !== null
                                                            ? isPerc ? `${(val * 100).toFixed(1)}%` : val
                                                            : '—'}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        );

                        return (
                            <GridWidget
                                key={widget.id}
                                title={widget.title}
                                subtitle={showTarget ? `Línea de Meta: ${cfg.targetLineLabel || cfg.targetLineValue}` : 'Evolución temporal del sentimiento'}
                                colSpan={widget.w}
                                rowSpan={widget.h || 5}
                                isEditing={isEditing}
                                accessibleTable={accessibleTable}
                                onConfigure={() => setSelectedWidgetForConfig(widget)}
                                onDelete={() => handleDeleteWidget(widget.id)}
                                onResize={(w, h) => handleWidgetResize(widget.id, w, h)}
                                draggable={isEditing}
                                onDragStart={(e) => handleDragStart(index, e)}
                                onDragOver={(e) => handleDragOver(index, e)}
                                onDrop={() => handleDrop(index)}
                                className={dragOverIndex === index ? 'ring-2 ring-[#d7f45b]' : ''}
                            >
                                {data.length === 0 ? (
                                    <div className="h-[240px] flex items-center justify-center text-xs text-[#687169]">
                                        No hay datos históricos para los filtros seleccionados.
                                    </div>
                                ) : (
                                    <EChartComponent options={chartOptions} height={(widget.h || 5) * 55} />
                                )}
                            </GridWidget>
                        );
                    }

                    // 3. Comparison Horizontal Bar Widget (comparison / bar_chart)
                    if (widget.type === 'comparison' || widget.type === 'bar_chart') {
                        const dimKey = level === 'supervisors' ? 'supervisor' : 'agent';
                        const labels = data
                            .map((d: any) => {
                                if (level === 'agents' && d.agent_name) {
                                    return `${d.agent_name} (${d.agent || d.agent_bms || ''})`;
                                }
                                return d[dimKey] || 'N/A';
                            })
                            .reverse();

                        const selectedMetrics: string[] = (cfg.metrics && cfg.metrics.length > 0)
                            ? cfg.metrics
                            : [cfg.metric || 'nps'];

                        const showTarget = Boolean(cfg.showTargetLine && cfg.targetLineValue !== undefined);

                        const series = selectedMetrics.map((mKey, idx) => {
                            const meta = METRIC_META[mKey] || { label: mKey.toUpperCase(), defaultColor: COLOR_PALETTE[idx % COLOR_PALETTE.length], isPercent: false };
                            const barColor = idx === 0 && cfg.color ? cfg.color : (meta.defaultColor || COLOR_PALETTE[idx % COLOR_PALETTE.length]);
                            const values = data
                                .map((d: any) => {
                                    const val = d[mKey];
                                    if (val === undefined || val === null) return 0;
                                    return meta.isPercent ? +(val * 100).toFixed(1) : val;
                                })
                                .reverse();

                            return {
                                name: meta.label,
                                type: 'bar',
                                data: values,
                                itemStyle: { color: barColor },
                                barMaxWidth: 26,
                                markLine: (idx === 0 && showTarget)
                                    ? {
                                          symbol: 'none',
                                          lineStyle: { type: 'dashed', color: '#943126', width: 1.5 },
                                          data: [
                                              {
                                                  xAxis: cfg.targetLineValue,
                                                  name: cfg.targetLineLabel || 'Meta',
                                              },
                                          ],
                                      }
                                    : undefined,
                            };
                        });

                        const chartOptions: any = {
                            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
                            legend: selectedMetrics.length > 1
                                ? { data: selectedMetrics.map((m) => METRIC_META[m]?.label || m), bottom: 0 }
                                : undefined,
                            grid: {
                                left: '3%',
                                right: '4%',
                                bottom: selectedMetrics.length > 1 ? '10%' : '3%',
                                containLabel: true,
                            },
                            xAxis: { type: 'value' },
                            yAxis: { type: 'category', data: labels },
                            series,
                        };

                        const accessibleTable = (
                            <table className="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-[#ccd1ca] bg-[#f7f6f1]">
                                        <th className="py-2 px-3">{level === 'supervisors' ? 'Supervisor' : 'Agente'}</th>
                                        {selectedMetrics.map((m) => (
                                            <th key={m} className="py-2 px-3 text-right">
                                                {METRIC_META[m]?.label || m}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#ccd1ca]/60 font-mono">
                                    {data.map((d: any, i: number) => (
                                        <tr key={i}>
                                            <td className="py-1.5 px-3 font-sans">{d[dimKey] || d.agent_name || '—'}</td>
                                            {selectedMetrics.map((m) => {
                                                const isPerc = METRIC_META[m]?.isPercent ?? false;
                                                const val = d[m];
                                                return (
                                                    <td key={m} className="py-1.5 px-3 text-right">
                                                        {val !== undefined && val !== null
                                                            ? isPerc ? `${(val * 100).toFixed(1)}%` : val
                                                            : '—'}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        );

                        return (
                            <GridWidget
                                key={widget.id}
                                title={widget.title}
                                subtitle={`Comparativo por ${level === 'supervisors' ? 'Supervisor' : 'Agente'}`}
                                colSpan={widget.w}
                                rowSpan={widget.h || 5}
                                isEditing={isEditing}
                                accessibleTable={accessibleTable}
                                onConfigure={() => setSelectedWidgetForConfig(widget)}
                                onDelete={() => handleDeleteWidget(widget.id)}
                                onResize={(w, h) => handleWidgetResize(widget.id, w, h)}
                                draggable={isEditing}
                                onDragStart={(e) => handleDragStart(index, e)}
                                onDragOver={(e) => handleDragOver(index, e)}
                                onDrop={() => handleDrop(index)}
                                className={dragOverIndex === index ? 'ring-2 ring-[#d7f45b]' : ''}
                            >
                                {data.length === 0 ? (
                                    <div className="h-[240px] flex items-center justify-center text-xs text-[#687169]">
                                        Sin datos de comparación.
                                    </div>
                                ) : (
                                    <EChartComponent options={chartOptions} height={(widget.h || 5) * 55} />
                                )}
                            </GridWidget>
                        );
                    }

                    // 4. Verbatim Category Breakdown / Area Chart Widget
                    if (widget.type === 'category_breakdown' || widget.type === 'area_chart' || widget.type === 'distribution') {
                        const labels = data.map((d: any) => d.category || d[cfg.dimension || 'supervisor'] || 'Uncategorized');
                        const values = data.map((d: any) => d.survey_volume || d.sample_count || (d.nps ? Math.round(d.nps * 100) : 0));

                        const chartOptions = {
                            tooltip: { trigger: 'item' },
                            legend: { orient: 'vertical', left: 'left' },
                            series: [
                                {
                                    name: 'Feedback',
                                    type: 'pie',
                                    radius: ['45%', '70%'],
                                    avoidLabelOverlap: false,
                                    label: { show: false },
                                    data: labels.map((l: string, i: number) => ({ name: l, value: values[i] })),
                                },
                            ],
                        };

                        const accessibleTable = (
                            <table className="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr className="border-b border-[#ccd1ca] bg-[#f7f6f1]">
                                        <th className="py-2 px-3">Categoría / Segmento</th>
                                        <th className="py-2 px-3 text-right">Volumen</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#ccd1ca]/60 font-mono">
                                    {labels.map((l: string, i: number) => (
                                        <tr key={i}>
                                            <td className="py-1.5 px-3 font-sans">{l}</td>
                                            <td className="py-1.5 px-3 text-right">{values[i]}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        );

                        return (
                            <GridWidget
                                key={widget.id}
                                title={widget.title}
                                subtitle="Distribución semántica y proporcional"
                                colSpan={widget.w}
                                rowSpan={widget.h || 5}
                                isEditing={isEditing}
                                accessibleTable={accessibleTable}
                                onConfigure={() => setSelectedWidgetForConfig(widget)}
                                onDelete={() => handleDeleteWidget(widget.id)}
                                onResize={(w, h) => handleWidgetResize(widget.id, w, h)}
                                draggable={isEditing}
                                onDragStart={(e) => handleDragStart(index, e)}
                                onDragOver={(e) => handleDragOver(index, e)}
                                onDrop={() => handleDrop(index)}
                                className={dragOverIndex === index ? 'ring-2 ring-[#d7f45b]' : ''}
                            >
                                {data.length === 0 ? (
                                    <div className="h-[240px] flex items-center justify-center text-xs text-[#687169]">
                                        No hay datos clasificados aún.
                                    </div>
                                ) : (
                                    <EChartComponent options={chartOptions} height={(widget.h || 5) * 55} />
                                )}
                            </GridWidget>
                        );
                    }

                    // 5. Detailed Table Widget with Computed Column & Conditional Formatting
                    if (widget.type === 'table') {
                        const dimKey = level === 'supervisors' ? 'supervisor' : 'agent';

                        const filteredData = tableSearch.trim()
                            ? data.filter((row: any) => {
                                  const term = tableSearch.toLowerCase().trim();
                                  const nameMatch = row.agent_name && String(row.agent_name).toLowerCase().includes(term);
                                  const keyMatch = row[dimKey] && String(row[dimKey]).toLowerCase().includes(term);
                                  return nameMatch || keyMatch;
                              })
                            : data;

                        const totalReceived = filteredData.reduce((acc: number, r: any) => acc + (r.survey_volume || r.sample_count || 0), 0);
                        const weightedNps =
                            totalReceived > 0
                                ? filteredData.reduce((acc: number, r: any) => acc + (r.nps ?? 0) * (r.survey_volume || r.sample_count || 0), 0) /
                                  totalReceived
                                : 0;
                        const weightedCsat =
                            totalReceived > 0
                                ? filteredData.reduce((acc: number, r: any) => acc + (r.csat ?? 0) * (r.survey_volume || r.sample_count || 0), 0) /
                                  totalReceived
                                : 0;
                        const weightedProf =
                            totalReceived > 0
                                ? filteredData.reduce(
                                      (acc: number, r: any) => acc + (r.professionalism ?? 0) * (r.survey_volume || r.sample_count || 0),
                                      0
                                  ) / totalReceived
                                : 0;

                        // Conditional formatting configuration
                        const cf = cfg.conditionalFormatting || { enabled: false };
                        const greenTh = cf.greenThreshold ?? 0.65;
                        const redTh = cf.redThreshold ?? 0.55;
                        const cfMode = cf.mode || 'badge';

                        const renderConditionalCell = (val: number | undefined) => {
                            if (val === undefined || val === null) return '—';
                            const percent = (val * 100).toFixed(2) + '%';

                            if (!cf.enabled) {
                                return <span>{percent}</span>;
                            }

                            const isGreen = val >= greenTh;
                            const isYellow = val >= redTh && val < greenTh;

                            if (cfMode === 'badge') {
                                const badgeClass = isGreen
                                    ? 'bg-[#eef7e8] text-[#2e5e33] border-[#aac69c]'
                                    : isYellow
                                    ? 'bg-[#fff8dc] text-[#7a6418] border-[#d7bf70]'
                                    : 'bg-[#fff0ed] text-[#943126] border-[#e1a89e]';
                                return (
                                    <span className={`inline-block px-1.5 py-0.5 border text-[11px] font-mono ${badgeClass}`}>
                                        {percent}
                                    </span>
                                );
                            }

                            if (cfMode === 'background') {
                                const bgClass = isGreen
                                    ? 'bg-[#a3d9a5]/40 text-[#144a1e]'
                                    : isYellow
                                    ? 'bg-[#fae498]/50 text-[#614b03]'
                                    : 'bg-[#f59e9e]/40 text-[#6b1414]';
                                return <span className={`px-2 py-1 rounded-none font-semibold ${bgClass}`}>{percent}</span>;
                            }

                            if (cfMode === 'bar') {
                                const barColor = isGreen ? '#2e5e33' : isYellow ? '#7a6418' : '#943126';
                                const barWidth = Math.max(0, Math.min(100, Math.round(val * 100)));
                                return (
                                    <div className="flex items-center justify-end gap-1.5">
                                        <div className="w-12 bg-[#ccd1ca]/40 h-2 overflow-hidden">
                                            <div style={{ width: `${barWidth}%`, backgroundColor: barColor }} className="h-full" />
                                        </div>
                                        <span>{percent}</span>
                                    </div>
                                );
                            }

                            return <span>{percent}</span>;
                        };

                        // Computed Column calculation
                        const comp = cfg.computedColumn || { enabled: false };
                        const targetBenchmark = cfg.targetLineValue || 50;

                        const calculateComputedValue = (row: any) => {
                            if (!comp.enabled) return null;
                            const baseNps = (row.nps ?? 0) * 100;

                            switch (comp.calculationType) {
                                case 'percent_of_target':
                                    return targetBenchmark > 0 ? ((baseNps / targetBenchmark) * 100).toFixed(1) + '%' : '—';
                                case 'diff_from_target':
                                    const diff = baseNps - targetBenchmark;
                                    return (diff > 0 ? '+' : '') + diff.toFixed(1);
                                case 'multiply_100':
                                    return (baseNps * 100).toFixed(0);
                                case 'custom':
                                    return (baseNps * (comp.customMultiplier ?? 1)).toFixed(1);
                                default:
                                    return baseNps.toFixed(1);
                            }
                        };

                        return (
                            <GridWidget
                                key={widget.id}
                                title={widget.title}
                                subtitle={
                                    level === 'agents'
                                        ? `100% del equipo (${data.length} agentes)`
                                        : `Supervisores (${data.length} registros)`
                                }
                                colSpan={widget.w}
                                rowSpan={widget.h || 6}
                                isEditing={isEditing}
                                onConfigure={() => setSelectedWidgetForConfig(widget)}
                                onDelete={() => handleDeleteWidget(widget.id)}
                                onResize={(w, h) => handleWidgetResize(widget.id, w, h)}
                                draggable={isEditing}
                                onDragStart={(e) => handleDragStart(index, e)}
                                onDragOver={(e) => handleDragOver(index, e)}
                                onDrop={() => handleDrop(index)}
                                className={dragOverIndex === index ? 'ring-2 ring-[#d7f45b]' : ''}
                            >
                                <div className="pb-3 flex flex-wrap items-center justify-between gap-2 border-b border-[#ccd1ca]/60">
                                    <div className="relative flex-1 min-w-[200px] max-w-sm">
                                        <Search className="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[#687169]" />
                                        <input
                                            type="text"
                                            value={tableSearch}
                                            onChange={(e) => setTableSearch(e.target.value)}
                                            placeholder={level === 'agents' ? 'Buscar por agente o código BMS...' : 'Buscar por supervisor...'}
                                            className="w-full text-xs pl-8 pr-3 py-1.5 border border-[#ccd1ca] bg-[#f7f6f1]/40 text-[#18221d] placeholder-[#687169] focus:outline-none focus:border-[#18221d] focus:bg-white transition-colors"
                                        />
                                    </div>
                                    <div className="text-[11px] text-[#687169] font-mono">
                                        Mostrando <strong className="text-[#18221d] font-bold">{filteredData.length}</strong> de {data.length} ({level === 'agents' ? '100% de agentes' : '100% de supervisores'})
                                    </div>
                                </div>

                                <div className="overflow-x-auto overflow-y-auto max-h-[500px] -mx-5 -mb-5">
                                    <table className="w-full text-left text-xs border-collapse">
                                        <thead className="sticky top-0 z-10 bg-[#0b4d68]">
                                            <tr className="bg-[#0b4d68] text-white border-b border-[#083b50]">
                                                <th className="py-3 px-5 font-semibold uppercase tracking-wider">
                                                    {level === 'supervisors' ? 'Supervisor' : 'Agente'}
                                                </th>
                                                <th className="py-3 px-3 font-semibold uppercase tracking-wider text-right">
                                                    Received
                                                </th>
                                                <th className="py-3 px-3 font-semibold uppercase tracking-wider text-right">
                                                    NPS AVERAGE
                                                </th>
                                                <th className="py-3 px-3 font-semibold uppercase tracking-wider text-right">
                                                    CSAT AVERAGE
                                                </th>
                                                <th className="py-3 px-4 font-semibold uppercase tracking-wider text-right">
                                                    PROFESSIONALISM
                                                </th>
                                                {comp.enabled && (
                                                    <th className="py-3 px-4 font-semibold uppercase tracking-wider text-right bg-[#083b50] text-[#d7f45b]">
                                                        {comp.name || 'Calculada'}
                                                    </th>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-[#ccd1ca]/60">
                                            {filteredData.length === 0 ? (
                                                <tr>
                                                    <td colSpan={comp.enabled ? 6 : 5} className="py-8 text-center text-[#687169]">
                                                        No hay registros que coincidan con la búsqueda.
                                                    </td>
                                                </tr>
                                            ) : (
                                                filteredData.map((row: any, idx: number) => (
                                                    <tr key={idx} className="hover:bg-[#f7f6f1]/50 transition-colors">
                                                        <td className="py-2.5 px-5 font-medium text-[#18221d]">
                                                            {level === 'agents' && (row.agent_name || row.agent || row[dimKey]) ? (
                                                                <div>
                                                                    <span className="font-semibold text-[#18221d]">
                                                                        {row.agent_name || row.agent || row[dimKey]}
                                                                    </span>
                                                                    {row[dimKey] && row[dimKey] !== row.agent_name && (
                                                                        <span className="block text-[10px] text-[#687169] font-mono">
                                                                            {row[dimKey]}
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            ) : (
                                                                row[dimKey] || '—'
                                                            )}
                                                        </td>
                                                        <td className="py-2.5 px-3 font-mono text-right font-semibold text-[#18221d]">
                                                            {row.survey_volume || row.sample_count || 0}
                                                        </td>
                                                        <td className="py-2.5 px-3 font-mono text-right">
                                                            {renderConditionalCell(row.nps)}
                                                        </td>
                                                        <td className="py-2.5 px-3 font-mono text-right">
                                                            {renderConditionalCell(row.csat)}
                                                        </td>
                                                        <td className="py-2.5 px-4 font-mono text-right">
                                                            {renderConditionalCell(row.professionalism)}
                                                        </td>
                                                        {comp.enabled && (
                                                            <td className="py-2.5 px-4 font-mono text-right font-bold text-[#18221d] bg-[#f7f6f1]/40">
                                                                {calculateComputedValue(row)}
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))
                                            )}
                                        </tbody>
                                        {filteredData.length > 0 && (
                                            <tfoot className="sticky bottom-0 z-10 border-t-2 border-[#18221d] font-bold bg-[#f7f6f1] shadow-[0_-2px_4px_rgba(0,0,0,0.05)]">
                                                <tr>
                                                    <td className="py-3 px-5 text-[#18221d] font-serif text-sm">
                                                        Grand Total
                                                    </td>
                                                    <td className="py-3 px-3 font-mono text-right text-[#18221d] font-bold">
                                                        {totalReceived}
                                                    </td>
                                                    <td className="py-3 px-3 font-mono text-right">
                                                        {(weightedNps * 100).toFixed(2)}%
                                                    </td>
                                                    <td className="py-3 px-3 font-mono text-right">
                                                        {(weightedCsat * 100).toFixed(2)}%
                                                    </td>
                                                    <td className="py-3 px-4 font-mono text-right">
                                                        {(weightedProf * 100).toFixed(2)}%
                                                    </td>
                                                    {comp.enabled && (
                                                        <td className="py-3 px-4 font-mono text-right text-[#687169]">
                                                            —
                                                        </td>
                                                    )}
                                                </tr>
                                            </tfoot>
                                        )}
                                    </table>
                                </div>
                            </GridWidget>
                        );
                    }

                    // 6. Text / Operative Notes Widget (text)
                    if (widget.type === 'text') {
                        return (
                            <GridWidget
                                key={widget.id}
                                title={widget.title}
                                subtitle="Instrucciones Operativas y Contexto"
                                colSpan={widget.w}
                                rowSpan={widget.h || 3}
                                isEditing={isEditing}
                                onConfigure={() => setSelectedWidgetForConfig(widget)}
                                onDelete={() => handleDeleteWidget(widget.id)}
                                onResize={(w, h) => handleWidgetResize(widget.id, w, h)}
                                draggable={isEditing}
                                onDragStart={(e) => handleDragStart(index, e)}
                                onDragOver={(e) => handleDragOver(index, e)}
                                onDrop={() => handleDrop(index)}
                                className={dragOverIndex === index ? 'ring-2 ring-[#d7f45b]' : ''}
                            >
                                <div className="text-xs text-[#18221d] whitespace-pre-wrap leading-relaxed">
                                    {cfg.textContent || (
                                        <span className="text-[#687169] italic">
                                            Sin notas asignadas. Haz clic en el icono de configuración para añadir notas o instrucciones operativas.
                                        </span>
                                    )}
                                </div>
                            </GridWidget>
                        );
                    }

                    return null;
                })}
            </div>

            {/* Lateral Configuration Sidebar Drawer */}
            <WidgetConfigSidebar
                widget={selectedWidgetForConfig}
                isOpen={Boolean(selectedWidgetForConfig)}
                onClose={() => setSelectedWidgetForConfig(null)}
                onSave={handleSaveWidgetConfig}
                onDelete={handleDeleteWidget}
            />

            {/* Add Widget Modal */}
            {showAddModal && (
                <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                    <div className="bg-white border border-[#ccd1ca] max-w-[540px] w-full p-6 shadow-2xl">
                        <div className="flex justify-between items-center mb-4 border-b border-[#ccd1ca] pb-3">
                            <h3 className="font-serif text-2xl text-[#18221d]">Añadir Widget al Dashboard</h3>
                            <button
                                onClick={() => setShowAddModal(false)}
                                className="text-[#687169] hover:text-[#18221d] p-1"
                            >
                                ✕
                            </button>
                        </div>
                        <p className="text-xs text-[#687169] mb-4">
                            Selecciona el tipo de componente visual que deseas incorporar a la cuadrícula de 12 columnas:
                        </p>
                        <div className="grid grid-cols-2 gap-3 mb-6">
                            {[
                                { title: 'Tarjeta Métrica NPS', type: 'metric', w: 3, h: 3, cfg: { metric: 'nps', showTargetLine: true, targetLineValue: 40 } },
                                { title: 'Tarjeta Métrica CSAT', type: 'metric', w: 3, h: 3, cfg: { metric: 'csat', showTargetLine: true, targetLineValue: 50 } },
                                { title: 'Tendencia Temporal', type: 'trend', w: 8, h: 5, cfg: { metrics: ['nps', 'csat'], showTargetLine: true, targetLineValue: 50, targetLineLabel: 'Meta Corporativa' } },
                                { title: 'Comparativa de Equipos', type: 'comparison', w: 4, h: 5, cfg: { metric: 'nps', color: '#18221d' } },
                                { title: 'Desglose de Categorías', type: 'category_breakdown', w: 6, h: 5, cfg: {} },
                                { title: 'Tabla con Cálculos', type: 'table', w: 12, h: 6, cfg: { computedColumn: { enabled: true, name: '% Cumplimiento', calculationType: 'percent_of_target' }, conditionalFormatting: { enabled: true, mode: 'badge' } } },
                                { title: 'Notas Operativas', type: 'text', w: 6, h: 3, cfg: { textContent: 'SOP Operativo: Revisar retroalimentación con NPS < 0 al inicio del turno.' } },
                            ].map((item, i) => (
                                <button
                                    key={i}
                                    type="button"
                                    onClick={async () => {
                                        const response = await fetch(`/dashboard/${dashboard.id}/widgets`, {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                                            },
                                            body: JSON.stringify({
                                                title: item.title,
                                                type: item.type,
                                                w: item.w,
                                                h: item.h,
                                                configuration: item.cfg,
                                            }),
                                        });
                                        const resData = await response.json();
                                        if (resData.widget) {
                                            setWidgets((prev) => [...prev, resData.widget]);
                                            fetchWidgetData(resData.widget);
                                        }
                                        setShowAddModal(false);
                                    }}
                                    className="p-3 border border-[#ccd1ca] hover:border-[#18221d] text-left text-xs font-medium hover:bg-[#f7f6f1] transition-colors flex flex-col justify-between"
                                >
                                    <div>
                                        <div className="font-semibold text-[#18221d] mb-0.5">{item.title}</div>
                                        <div className="text-[10px] text-[#687169] uppercase font-mono">{item.type}</div>
                                    </div>
                                    <div className="text-[10px] text-[#687169] mt-2 font-mono">
                                        {item.w} cols × {item.h} filas
                                    </div>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            <KpiGoalsModal
                isOpen={isGoalsModalOpen}
                onClose={() => setIsGoalsModalOpen(false)}
                initialGoals={goals}
                onGoalsSaved={(newGoals) => setGoals(newGoals)}
            />
        </AppLayout>
    );
}
