import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import ToolEditorModal from './Components/ToolEditorModal';
import ToolTestSandboxModal from './Components/ToolTestSandboxModal';
import {
    Wrench,
    Plus,
    Play,
    Edit2,
    Trash2,
    Check,
    X,
    Shield,
    Sliders,
    Clock,
    Activity,
    Cpu,
    CheckCircle2,
    AlertCircle,
    Info,
} from 'lucide-react';

interface ToolItem {
    id: string;
    name: string;
    label: string;
    description: string;
    is_builtin: boolean;
    is_active: boolean;
    execution_mode: string;
    parameters_schema: any;
    dsl_template?: any;
    sort_order: number;
    created_by?: string;
    created_at?: string;
    stats: {
        invocations: number;
        avg_duration_ms: number;
        errors: number;
    };
}

interface Props {
    tools: ToolItem[];
}

export default function ToolsIndex({ tools }: Props) {
    const [editorOpen, setEditorOpen] = useState<boolean>(false);
    const [selectedToolForEdit, setSelectedToolForEdit] = useState<ToolItem | null>(null);

    const [sandboxOpen, setSandboxOpen] = useState<boolean>(false);
    const [selectedToolForSandbox, setSelectedToolForSandbox] = useState<ToolItem | null>(null);

    const [togglingId, setTogglingId] = useState<string | null>(null);

    useEffect(() => {
        // 1. Check if there was prefilled data from sessionStorage
        const savedPrefill = sessionStorage.getItem('atlas_prefilled_tool_data');
        if (savedPrefill) {
            try {
                const parsed = JSON.parse(savedPrefill);
                if (parsed.suggested_tool) {
                    setSelectedToolForEdit(parsed.suggested_tool);
                    setEditorOpen(true);
                }
            } catch (e) {
                // Ignore parse error
            }
            sessionStorage.removeItem('atlas_prefilled_tool_data');
        }

        // 2. Listen to custom event from FloatingDslChat
        const handleLoadDsl = (e: any) => {
            const detail = e.detail;
            if (detail && detail.suggested_tool) {
                setSelectedToolForEdit(detail.suggested_tool);
                setEditorOpen(true);
            }
        };

        window.addEventListener('load-dsl-into-editor', handleLoadDsl);
        return () => window.removeEventListener('load-dsl-into-editor', handleLoadDsl);
    }, []);

    const handleCreateNew = () => {
        setSelectedToolForEdit(null);
        setEditorOpen(true);
    };

    const handleEdit = (tool: ToolItem) => {
        setSelectedToolForEdit(tool);
        setEditorOpen(true);
    };

    const handleTestSandbox = (tool: ToolItem) => {
        setSelectedToolForSandbox(tool);
        setSandboxOpen(true);
    };

    const handleToggleActive = async (tool: ToolItem) => {
        setTogglingId(tool.id);
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        try {
            const res = await fetch(`/admin/tools/${tool.id}/toggle`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            if (!res.ok) {
                const data = await res.json();
                throw new Error(data.message || 'Error al cambiar estado de la herramienta');
            }

            router.reload({ only: ['tools'] });
        } catch (err: any) {
            alert(err.message);
        } finally {
            setTogglingId(null);
        }
    };

    const handleDelete = async (tool: ToolItem) => {
        if (tool.is_builtin) {
            alert('No se pueden eliminar las herramientas nativas del sistema.');
            return;
        }

        const confirmed = window.confirm(
            `¿Estás seguro de eliminar permanentemente la tool '${tool.label}' (${tool.name})? Esta acción no se puede deshacer.`
        );
        if (!confirmed) return;

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        try {
            const res = await fetch(`/admin/tools/${tool.id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });

            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Error al eliminar herramienta');
            }

            router.reload({ only: ['tools'] });
        } catch (err: any) {
            alert(err.message);
        }
    };

    const builtinCount = tools.filter((t) => t.is_builtin).length;
    const customCount = tools.filter((t) => !t.is_builtin).length;
    const activeCount = tools.filter((t) => t.is_active).length;

    return (
        <AppLayout
            title="Catálogo & Gestor de Tools DSL"
            kicker="EXTENSIBILIDAD & OPTIMIZACIÓN DE AGENTE IA"
            description="Administra, crea y ajusta las herramientas analíticas basadas en Query DSL provistas al modelo de Inteligencia Artificial. Optimiza los esquemas para reducir bucles de consultas y consumo de tokens."
            actions={
                <button
                    type="button"
                    onClick={handleCreateNew}
                    className="px-4 py-2.5 bg-[#d7f45b] text-[#18221d] border border-[#18221d] font-semibold text-xs uppercase tracking-wider flex items-center gap-1.5 hover:bg-[#cbf03f] transition-colors shadow-xs"
                >
                    <Plus className="w-4 h-4" />
                    <span>Nueva Tool DSL</span>
                </button>
            }
        >
            <Head title="Gestión de Tools DSL — ATLAS VOC Analysis" />

            {/* Quick Summary Strip */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                <div className="border border-[#ccd1ca] bg-white p-4 flex items-center justify-between">
                    <div>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#687169] block">
                            Tools Habilitadas
                        </span>
                        <span className="font-serif text-3xl text-[#18221d] mt-0.5 block">
                            {activeCount} <span className="text-sm font-sans text-[#687169]">/ {tools.length} total</span>
                        </span>
                    </div>
                    <div className="w-10 h-10 rounded-full bg-[#eef7e8] border border-[#aac69c] flex items-center justify-center text-emerald-800">
                        <CheckCircle2 className="w-5 h-5" />
                    </div>
                </div>

                <div className="border border-[#ccd1ca] bg-white p-4 flex items-center justify-between">
                    <div>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#687169] block">
                            Nativas del Sistema
                        </span>
                        <span className="font-serif text-3xl text-[#18221d] mt-0.5 block">
                            {builtinCount}
                        </span>
                    </div>
                    <div className="w-10 h-10 rounded-full bg-[#f7f6f1] border border-[#ccd1ca] flex items-center justify-center text-[#18221d]">
                        <Shield className="w-5 h-5" />
                    </div>
                </div>

                <div className="border border-[#ccd1ca] bg-white p-4 flex items-center justify-between">
                    <div>
                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#687169] block">
                            Tools DSL Personalizadas
                        </span>
                        <span className="font-serif text-3xl text-[#18221d] mt-0.5 block">
                            {customCount}
                        </span>
                    </div>
                    <div className="w-10 h-10 rounded-full bg-[#d7f45b]/40 border border-[#18221d]/30 flex items-center justify-center text-[#18221d]">
                        <Cpu className="w-5 h-5" />
                    </div>
                </div>
            </div>

            {/* Tools List */}
            <div className="space-y-4">
                {tools.map((t) => (
                    <div
                        key={t.id}
                        className={`border bg-white transition-all ${
                            t.is_active ? 'border-[#ccd1ca]' : 'border-[#ccd1ca]/60 opacity-75'
                        }`}
                    >
                        <div className="p-5 flex flex-col md:flex-row md:items-start justify-between gap-4">
                            {/* Left: Info */}
                            <div className="flex-1 min-w-0">
                                <div className="flex flex-wrap items-center gap-2 mb-1.5">
                                    <h4 className="font-serif text-xl text-[#18221d] font-bold">{t.label}</h4>
                                    <span className="font-mono text-xs px-2 py-0.5 bg-[#f7f6f1] border border-[#ccd1ca] text-[#18221d]">
                                        {t.name}
                                    </span>

                                    {t.is_builtin ? (
                                        <span className="text-[9px] font-mono px-1.5 py-0.5 uppercase tracking-wider bg-[#dce4d8] text-[#18221d] font-semibold border border-[#ccd1ca]">
                                            Nativa Sistema
                                        </span>
                                    ) : (
                                        <span className="text-[9px] font-mono px-1.5 py-0.5 uppercase tracking-wider bg-[#d7f45b] text-[#18221d] font-bold border border-[#18221d]/40">
                                            Custom DSL
                                        </span>
                                    )}

                                    <span
                                        className={`text-[9px] font-mono px-1.5 py-0.5 uppercase tracking-wider font-semibold border ${
                                            t.is_active
                                                ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                                                : 'bg-gray-100 text-gray-600 border-gray-300'
                                        }`}
                                    >
                                        {t.is_active ? 'Activa' : 'Inactiva'}
                                    </span>
                                </div>

                                <p className="text-xs text-[#18221d]/85 leading-relaxed font-sans mb-3 max-w-3xl">
                                    {t.description}
                                </p>

                                {/* Technical metadata & stats */}
                                <div className="flex flex-wrap items-center gap-4 text-[11px] font-mono text-[#687169] pt-2 border-t border-[#ccd1ca]/40">
                                    <span className="flex items-center gap-1">
                                        <Activity className="w-3 h-3 text-[#18221d]" />
                                        <span>Invocaciones: {t.stats.invocations}</span>
                                    </span>
                                    <span className="flex items-center gap-1">
                                        <Clock className="w-3 h-3 text-[#18221d]" />
                                        <span>Latencia media: {t.stats.avg_duration_ms}ms</span>
                                    </span>
                                    <span>Modo: {t.execution_mode}</span>
                                    {t.created_by && <span>Creada por: {t.created_by}</span>}
                                </div>
                            </div>

                            {/* Right: Actions */}
                            <div className="flex items-center space-x-2 flex-shrink-0 pt-2 md:pt-0">
                                {/* Toggle Active Switch */}
                                <button
                                    type="button"
                                    onClick={() => handleToggleActive(t)}
                                    disabled={togglingId === t.id}
                                    className={`px-3 py-1.5 text-xs font-mono font-semibold border transition-colors flex items-center gap-1 ${
                                        t.is_active
                                            ? 'bg-white text-[#18221d] border-[#ccd1ca] hover:bg-[#fff0ed] hover:text-red-700 hover:border-red-300'
                                            : 'bg-[#18221d] text-white border-[#18221d] hover:bg-black'
                                    }`}
                                    title={t.is_active ? 'Desactivar tool para el agente' : 'Activar tool'}
                                >
                                    {t.is_active ? (
                                        <>
                                            <X className="w-3 h-3 text-red-600" />
                                            <span>Desactivar</span>
                                        </>
                                    ) : (
                                        <>
                                            <Check className="w-3 h-3 text-emerald-400" />
                                            <span>Habilitar</span>
                                        </>
                                    )}
                                </button>

                                {/* Sandbox Test */}
                                <button
                                    type="button"
                                    onClick={() => handleTestSandbox(t)}
                                    className="px-3 py-1.5 bg-[#f7f6f1] border border-[#ccd1ca] hover:bg-white text-[#18221d] text-xs font-mono font-medium flex items-center gap-1 transition-colors"
                                    title="Ejecutar prueba de argumentos en seco"
                                >
                                    <Play className="w-3 h-3 fill-[#18221d]" />
                                    <span>Sandbox</span>
                                </button>

                                {/* Edit */}
                                <button
                                    type="button"
                                    onClick={() => handleEdit(t)}
                                    className="p-1.5 border border-[#ccd1ca] hover:bg-[#f7f6f1] text-[#18221d] transition-colors"
                                    title="Editar definición de tool"
                                >
                                    <Edit2 className="w-4 h-4" />
                                </button>

                                {/* Delete (Custom only) */}
                                {!t.is_builtin && (
                                    <button
                                        type="button"
                                        onClick={() => handleDelete(t)}
                                        className="p-1.5 border border-red-200 hover:bg-red-50 text-red-700 transition-colors"
                                        title="Eliminar tool personalizada"
                                    >
                                        <Trash2 className="w-4 h-4" />
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Modals */}
            <ToolEditorModal
                tool={selectedToolForEdit}
                isOpen={editorOpen}
                onClose={() => {
                    setEditorOpen(false);
                    setSelectedToolForEdit(null);
                }}
            />

            <ToolTestSandboxModal
                tool={selectedToolForSandbox}
                isOpen={sandboxOpen}
                onClose={() => {
                    setSandboxOpen(false);
                    setSelectedToolForSandbox(null);
                }}
            />
        </AppLayout>
    );
}
