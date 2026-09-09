import React, { useState, useEffect } from 'react';
import { X, Code, Sliders, CheckCircle2, AlertCircle } from 'lucide-react';
import { router } from '@inertiajs/react';

interface Tool {
    id?: string;
    name: string;
    label: string;
    description: string;
    is_builtin?: boolean;
    is_active: boolean;
    execution_mode: string;
    parameters_schema: any;
    dsl_template?: any;
}

interface Props {
    tool: Tool | null; // If null, create mode
    isOpen: boolean;
    onClose: () => void;
}

export default function ToolEditorModal({ tool, isOpen, onClose }: Props) {
    if (!isOpen) return null;

    const isEdit = Boolean(tool && tool.id);
    const isBuiltin = Boolean(tool && tool.is_builtin);

    const [name, setName] = useState<string>(tool?.name || '');
    const [label, setLabel] = useState<string>(tool?.label || '');
    const [description, setDescription] = useState<string>(tool?.description || '');
    const [executionMode, setExecutionMode] = useState<string>(tool?.execution_mode || 'dsl_query');
    const [isActive, setIsActive] = useState<boolean>(tool?.is_active ?? true);

    const [schemaJson, setSchemaJson] = useState<string>(
        tool?.parameters_schema ? JSON.stringify(tool.parameters_schema, null, 2) : JSON.stringify({
            type: 'object',
            properties: {
                metric: {
                    type: 'string',
                    enum: ['nps', 'csat', 'professionalism', 'survey_volume'],
                    description: 'Métrica objetivo a calcular o clasificar',
                },
                limit: {
                    type: 'integer',
                    description: 'Cantidad máxima de registros a retornar',
                },
            },
            required: ['metric'],
        }, null, 2)
    );

    const [dslTemplateJson, setDslTemplateJson] = useState<string>(
        tool?.dsl_template ? JSON.stringify(tool.dsl_template, null, 2) : JSON.stringify({
            group_by: ['agent'],
            default_limit: 10,
        }, null, 2)
    );

    const [activeTab, setActiveTab] = useState<'info' | 'schema' | 'dsl'>('info');
    const [saving, setSaving] = useState<boolean>(false);
    const [formError, setFormError] = useState<string | null>(null);

    useEffect(() => {
        if (tool) {
            setName(tool.name);
            setLabel(tool.label);
            setDescription(tool.description);
            setExecutionMode(tool.execution_mode);
            setIsActive(tool.is_active);
            setSchemaJson(JSON.stringify(tool.parameters_schema || {}, null, 2));
            setDslTemplateJson(JSON.stringify(tool.dsl_template || {}, null, 2));
        } else {
            setName('');
            setLabel('');
            setDescription('');
            setExecutionMode('dsl_query');
            setIsActive(true);
            setSchemaJson(JSON.stringify({
                type: 'object',
                properties: {
                    metric: {
                        type: 'string',
                        enum: ['nps', 'csat', 'professionalism', 'survey_volume'],
                        description: 'Métrica objetivo a calcular',
                    },
                    limit: {
                        type: 'integer',
                        description: 'Cantidad máxima de registros a retornar',
                    },
                },
                required: ['metric'],
            }, null, 2));
            setDslTemplateJson(JSON.stringify({
                group_by: ['agent'],
                default_limit: 10,
            }, null, 2));
        }
        setFormError(null);
    }, [tool]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setFormError(null);

        // Parse JSON Schema
        let parsedSchema = null;
        try {
            parsedSchema = JSON.parse(schemaJson);
        } catch (err: any) {
            setFormError(`El JSON de 'Definición de Parámetros (Schema)' es inválido: ${err.message}`);
            setActiveTab('schema');
            setSaving(false);
            return;
        }

        // Parse DSL Template
        let parsedTemplate = null;
        if (dslTemplateJson.trim()) {
            try {
                parsedTemplate = JSON.parse(dslTemplateJson);
            } catch (err: any) {
                setFormError(`El JSON de 'Plantilla Query DSL' es inválido: ${err.message}`);
                setActiveTab('dsl');
                setSaving(false);
                return;
            }
        }

        const payload = {
            name,
            label,
            description,
            execution_mode: executionMode,
            is_active: isActive,
            parameters_schema: parsedSchema,
            dsl_template: parsedTemplate,
        };

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        try {
            const url = isEdit ? `/admin/tools/${tool!.id}` : '/admin/tools';
            const method = isEdit ? 'PUT' : 'POST';

            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || 'Error al guardar la herramienta');
            }

            onClose();
            router.reload({ only: ['tools'] });
        } catch (err: any) {
            setFormError(err.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-[#f7f6f1] border border-[#ccd1ca] max-w-3xl w-full shadow-2xl flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="p-5 border-b border-[#ccd1ca] bg-white flex items-center justify-between">
                    <div>
                        <span className="text-[10px] font-bold uppercase tracking-[0.16em] text-[#687169] block">
                            {isEdit ? 'EDICIÓN DE HERRAMIENTA' : 'NUEVA TOOL DSL'}
                        </span>
                        <h3 className="font-serif text-2xl text-[#18221d]">
                            {isEdit ? `Editar: ${label || name}` : 'Crear Nueva Tool DSL para Agente'}
                        </h3>
                    </div>
                    <button
                        onClick={onClose}
                        className="p-1.5 border border-[#ccd1ca] hover:bg-[#f7f6f1] text-[#18221d] transition-colors"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Sub-tabs */}
                <div className="bg-white border-b border-[#ccd1ca] px-6 flex items-center space-x-6 text-xs font-semibold uppercase tracking-wider">
                    <button
                        type="button"
                        onClick={() => setActiveTab('info')}
                        className={`py-2.5 border-b-2 transition-colors ${
                            activeTab === 'info'
                                ? 'border-[#18221d] text-[#18221d]'
                                : 'border-transparent text-[#687169] hover:text-[#18221d]'
                        }`}
                    >
                        1. Definición & Prompt IA
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('schema')}
                        className={`py-2.5 border-b-2 transition-colors ${
                            activeTab === 'schema'
                                ? 'border-[#18221d] text-[#18221d]'
                                : 'border-transparent text-[#687169] hover:text-[#18221d]'
                        }`}
                    >
                        2. Parámetros (JSON Schema)
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('dsl')}
                        className={`py-2.5 border-b-2 transition-colors ${
                            activeTab === 'dsl'
                                ? 'border-[#18221d] text-[#18221d]'
                                : 'border-transparent text-[#687169] hover:text-[#18221d]'
                        }`}
                    >
                        3. Plantilla Query DSL
                    </button>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-6 space-y-4">
                    {formError && (
                        <div className="p-3 bg-red-50 border border-red-200 text-red-800 text-xs flex items-start gap-2">
                            <AlertCircle className="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" />
                            <span>{formError}</span>
                        </div>
                    )}

                    {/* Tab 1: Info & Prompting */}
                    {activeTab === 'info' && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[11px] font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                        Nombre Técnico (Slug)
                                    </label>
                                    <input
                                        type="text"
                                        value={name}
                                        onChange={(e) => setName(e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))}
                                        disabled={isBuiltin}
                                        required
                                        placeholder="ej: supervisor_performance_matrix"
                                        className="w-full p-2 bg-white border border-[#ccd1ca] text-xs font-mono text-[#18221d] disabled:bg-gray-100 disabled:text-gray-500"
                                    />
                                    <span className="text-[10px] text-[#687169] mt-0.5 block font-mono">
                                        Solo letras minúsculas y guiones bajos (snake_case).
                                    </span>
                                </div>

                                <div>
                                    <label className="block text-[11px] font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                        Nombre Amigable (Label)
                                    </label>
                                    <input
                                        type="text"
                                        value={label}
                                        onChange={(e) => setLabel(e.target.value)}
                                        required
                                        placeholder="ej: Matriz de Desempeño por Supervisor"
                                        className="w-full p-2 bg-white border border-[#ccd1ca] text-xs font-sans text-[#18221d]"
                                    />
                                    <span className="text-[10px] text-[#687169] mt-0.5 block">
                                        Nombre para visualización humana en auditorías y reportes.
                                    </span>
                                </div>
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="text-[11px] font-bold uppercase tracking-wider text-[#18221d]">
                                        Descripción para el Modelo de IA (Prompt de Tool)
                                    </label>
                                    <span className="text-[10px] font-mono text-[#687169]">
                                        {description.length} caracteres
                                    </span>
                                </div>
                                <textarea
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                    required
                                    rows={4}
                                    placeholder="Explica detalladamente qué datos retorna esta herramienta y en qué casos el agente debe usarla preferentemente para evitar bucles de consultas..."
                                    className="w-full p-2.5 bg-white border border-[#ccd1ca] text-xs font-sans text-[#18221d] leading-relaxed"
                                />
                                <div className="mt-1 p-2 bg-[#dce4d8]/40 border border-[#ccd1ca] text-[11px] text-[#18221d]/85">
                                    <strong>Consejo de optimización:</strong> Sé muy explícito indicando cuándo debe invocarse. Una descripción precisa evita que el LLM intente llamar múltiples herramientas básicas por separado.
                                </div>
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <div>
                                    <label className="block text-[11px] font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                        Modo de Ejecución
                                    </label>
                                    <select
                                        value={executionMode}
                                        onChange={(e) => setExecutionMode(e.target.value)}
                                        disabled={isBuiltin}
                                        className="w-full p-2 bg-white border border-[#ccd1ca] text-xs text-[#18221d] disabled:bg-gray-100"
                                    >
                                        <option value="dsl_query">Query DSL Estándar (Agregado)</option>
                                        <option value="multi_metric">Cálculo Multimétrica</option>
                                        <option value="system" disabled={!isBuiltin}>
                                            Handler Interno del Sistema
                                        </option>
                                    </select>
                                </div>

                                <div className="flex items-center space-x-3 pt-6">
                                    <input
                                        type="checkbox"
                                        id="tool_is_active"
                                        checked={isActive}
                                        onChange={(e) => setIsActive(e.target.checked)}
                                        className="w-4 h-4 accent-[#18221d]"
                                    />
                                    <label htmlFor="tool_is_active" className="text-xs font-semibold text-[#18221d] cursor-pointer">
                                        Herramienta activa para el Asistente IA
                                    </label>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Tab 2: Parameters JSON Schema */}
                    {activeTab === 'schema' && (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <label className="text-[11px] font-bold uppercase tracking-wider text-[#18221d]">
                                    JSON Schema de Parámetros (Function Calling OpenAPI)
                                </label>
                                <span className="text-[10px] font-mono text-[#687169]">
                                    Compatible con Google Gemini / Function Calling
                                </span>
                            </div>
                            <textarea
                                value={schemaJson}
                                onChange={(e) => setSchemaJson(e.target.value)}
                                rows={14}
                                required
                                className="w-full p-3 bg-[#18221d] text-[#d7f45b] font-mono text-xs leading-relaxed border border-[#ccd1ca]"
                            />
                            <p className="text-[11px] text-[#687169]">
                                Define qué argumentos requiere y acepta la herramienta (tipos, enums y descripciones que lee la IA).
                            </p>
                        </div>
                    )}

                    {/* Tab 3: Query DSL Template */}
                    {activeTab === 'dsl' && (
                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <label className="text-[11px] font-bold uppercase tracking-wider text-[#18221d]">
                                    Plantilla Query DSL (Valores por defecto & Dimensiones)
                                </label>
                                <span className="text-[10px] font-mono text-[#687169]">
                                    Configuración de ejecución server-side
                                </span>
                            </div>
                            <textarea
                                value={dslTemplateJson}
                                onChange={(e) => setDslTemplateJson(e.target.value)}
                                rows={12}
                                className="w-full p-3 bg-[#18221d] text-white font-mono text-xs leading-relaxed border border-[#ccd1ca]"
                            />
                            <p className="text-[11px] text-[#687169]">
                                Opciones predefinidas como <code>group_by</code>, límites de registros por defecto o filtros fijos.
                            </p>
                        </div>
                    )}

                    {/* Action Bar */}
                    <div className="flex items-center justify-end space-x-3 pt-4 border-t border-[#ccd1ca]">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 border border-[#ccd1ca] bg-white text-xs font-semibold text-[#687169] uppercase hover:bg-[#f7f6f1]"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={saving}
                            className="px-6 py-2 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black transition-colors"
                        >
                            {saving ? 'Guardando...' : isEdit ? 'Actualizar Tool DSL' : 'Crear Tool DSL'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
