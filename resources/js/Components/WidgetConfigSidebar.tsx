import React, { useState, useEffect } from 'react';
import { X, Check, Trash2, Sliders, Palette, Target, Calculator, FileText, CheckSquare } from 'lucide-react';
import { Widget, WidgetConfig, WidgetType } from '@/types/dashboard';

interface Props {
    widget: Widget | null;
    isOpen: boolean;
    onClose: () => void;
    onSave: (updatedWidget: Widget) => void;
    onDelete?: (widgetId: number) => void;
}

const COLOR_PRESETS = [
    { label: 'Ink (#18221d)', value: '#18221d' },
    { label: 'Sage (#aac69c)', value: '#aac69c' },
    { label: 'Lime (#d7f45b)', value: '#d7f45b' },
    { label: 'Muted (#687169)', value: '#687169' },
    { label: 'Soft Red (#e1a89e)', value: '#e1a89e' },
    { label: 'Soft Yellow (#d7bf70)', value: '#d7bf70' },
];

const AVAILABLE_METRICS = [
    { id: 'nps', label: 'Net Promoter Score (NPS)', color: '#18221d', desc: 'Índice de lealtad (-100 a +100%)' },
    { id: 'csat', label: 'Customer Satisfaction (CSAT)', color: '#aac69c', desc: 'Satisfacción general con el servicio (%)' },
    { id: 'professionalism', label: 'Professionalism Score', color: '#d7bf70', desc: 'Profesionalismo del representante (%)' },
    { id: 'survey_volume', label: 'Survey Volume', color: '#687169', desc: 'Total respuestas de encuestas (n)' },
];

export default function WidgetConfigSidebar({
    widget,
    isOpen,
    onClose,
    onSave,
    onDelete,
}: Props) {
    if (!widget || !isOpen) return null;

    const [title, setTitle] = useState(widget.title);
    const [type, setType] = useState<WidgetType>(widget.type as WidgetType);
    const [w, setW] = useState(widget.w);
    const [h, setH] = useState(widget.h);
    const [config, setConfig] = useState<WidgetConfig>({ ...widget.configuration });

    // Extract current selected metrics (multi-metric support)
    const selectedMetrics: string[] =
        config.metrics && config.metrics.length > 0
            ? config.metrics
            : config.metric
            ? [config.metric]
            : ['nps', 'csat'];

    // Sync state whenever active widget changes
    useEffect(() => {
        if (!widget) return;
        setTitle(widget.title);
        setType(widget.type as WidgetType);
        setW(widget.w);
        setH(widget.h);
        setConfig({ ...widget.configuration });
    }, [widget.id, widget.w, widget.h, widget.title, widget.type]);

    // Live update helper: updates parent immediately for real-time reactivity
    const emitChange = (newTitle: string, newType: WidgetType, newW: number, newH: number, newCfg: WidgetConfig) => {
        onSave({
            ...widget,
            title: newTitle,
            type: newType,
            w: newW,
            h: newH,
            configuration: newCfg,
        });
    };

    const handleTitleChange = (val: string) => {
        setTitle(val);
        emitChange(val, type, w, h, config);
    };

    const handleTypeChange = (newType: WidgetType) => {
        setType(newType);
        emitChange(title, newType, w, h, config);
    };

    const handleWidthChange = (newW: number) => {
        setW(newW);
        emitChange(title, type, newW, h, config);
    };

    const handleHeightChange = (newH: number) => {
        setH(newH);
        emitChange(title, type, w, newH, config);
    };

    const updateConfigField = (key: string, value: any) => {
        const updated = { ...config, [key]: value };
        setConfig(updated);
        emitChange(title, type, w, h, updated);
    };

    // Toggle multi-metric selection
    const toggleMetric = (metricId: string) => {
        let updatedList = [...selectedMetrics];
        if (updatedList.includes(metricId)) {
            // Prevent deselecting all metrics
            if (updatedList.length > 1) {
                updatedList = updatedList.filter((m) => m !== metricId);
            }
        } else {
            updatedList.push(metricId);
        }

        const updated = {
            ...config,
            metrics: updatedList,
            metric: updatedList[0], // fallback for single-metric compatibility
        };
        setConfig(updated);
        emitChange(title, type, w, h, updated);
    };

    const isChart = type === 'trend' || type === 'line_chart' || type === 'comparison' || type === 'bar_chart';

    return (
        <div className="fixed inset-0 z-50 overflow-hidden">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-black/40 backdrop-blur-[1px] transition-opacity"
                onClick={onClose}
            />

            <div className="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div className="w-screen max-w-md bg-white border-l border-[#ccd1ca] shadow-2xl flex flex-col">
                    {/* Header */}
                    <div className="p-6 border-b border-[#ccd1ca] flex items-center justify-between bg-[#f7f6f1]">
                        <div className="flex items-center gap-2">
                            <Sliders className="w-4 h-4 text-[#18221d]" />
                            <h2 className="font-serif text-xl text-[#18221d] tracking-tight">
                                Configuración de Widget
                            </h2>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="p-1 hover:bg-[#eae8e0] text-[#687169] hover:text-[#18221d] transition-colors"
                        >
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    {/* Scrollable Form Body */}
                    <div className="flex-1 overflow-y-auto p-6 space-y-6 text-xs">
                        {/* Section 1: General */}
                        <div className="space-y-3">
                            <span className="text-[11px] font-bold uppercase tracking-wider text-[#687169] block">
                                1. Propiedades Generales
                            </span>
                            <div>
                                <label className="block text-[#18221d] font-medium mb-1">
                                    Título del Widget
                                </label>
                                <input
                                    type="text"
                                    value={title}
                                    onChange={(e) => handleTitleChange(e.target.value)}
                                    className="w-full border border-[#ccd1ca] px-3 py-2 text-xs focus:outline-none focus:border-[#18221d]"
                                />
                            </div>

                            <div>
                                <label className="block text-[#18221d] font-medium mb-1">
                                    Tipo de Visualización
                                </label>
                                <select
                                    value={type}
                                    onChange={(e) => handleTypeChange(e.target.value as WidgetType)}
                                    className="w-full border border-[#ccd1ca] px-3 py-2 text-xs bg-white focus:outline-none focus:border-[#18221d]"
                                >
                                    <option value="metric">Tarjeta KPI (Métrica Destacada)</option>
                                    <option value="trend">Gráfica de Tendencia (Líneas / Temporal)</option>
                                    <option value="comparison">Comparativa por Entidad (Barras)</option>
                                    <option value="category_breakdown">Desglose de Categorías (Dona / Pastel)</option>
                                    <option value="table">Tabla Detallada con Cálculos y Semáforos</option>
                                    <option value="text">Bloque de Notas Operativas / Texto</option>
                                </select>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-[#18221d] font-medium mb-1">
                                        Ancho (Columnas Grid)
                                    </label>
                                    <select
                                        value={w}
                                        onChange={(e) => handleWidthChange(Number(e.target.value))}
                                        className="w-full border border-[#ccd1ca] px-3 py-2 text-xs bg-white focus:outline-none focus:border-[#18221d]"
                                    >
                                        <option value={3}>3 cols (25%)</option>
                                        <option value={4}>4 cols (33%)</option>
                                        <option value={6}>6 cols (50%)</option>
                                        <option value={8}>8 cols (66%)</option>
                                        <option value={12}>12 cols (100%)</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-[#18221d] font-medium mb-1">
                                        Altura (Filas base)
                                    </label>
                                    <select
                                        value={h}
                                        onChange={(e) => handleHeightChange(Number(e.target.value))}
                                        className="w-full border border-[#ccd1ca] px-3 py-2 text-xs bg-white focus:outline-none focus:border-[#18221d]"
                                    >
                                        <option value={2}>2 filas (~140px)</option>
                                        <option value={3}>3 filas (~210px)</option>
                                        <option value={4}>4 filas (~280px)</option>
                                        <option value={5}>5 filas (~350px)</option>
                                        <option value={6}>6 filas (~420px)</option>
                                        <option value={8}>8 filas (~560px)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {/* Section 2: Data & Semantics */}
                        {type !== 'text' && (
                            <div className="space-y-4 pt-4 border-t border-[#ccd1ca]">
                                <span className="text-[11px] font-bold uppercase tracking-wider text-[#687169] block">
                                    2. Datos & Semántica Analítica
                                </span>

                                {/* Multi-metric selection for charts */}
                                {isChart ? (
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <label className="block text-[#18221d] font-semibold">
                                                Métricas Seleccionadas (Multi-Métrica)
                                            </label>
                                            <span className="text-[10px] text-[#687169] font-mono">
                                                {selectedMetrics.length} seleccionada(s)
                                            </span>
                                        </div>
                                        <p className="text-[11px] text-[#687169]">
                                            Puedes activar múltiples métricas simultáneamente para compararlas en este mismo gráfico (ej. NPS + CSAT + Professionalism).
                                        </p>

                                        <div className="space-y-1.5 pt-1">
                                            {AVAILABLE_METRICS.map((m) => {
                                                const isChecked = selectedMetrics.includes(m.id);
                                                return (
                                                    <label
                                                        key={m.id}
                                                        className={`flex items-start gap-2.5 p-2.5 border cursor-pointer transition-all ${
                                                            isChecked
                                                                ? 'border-[#18221d] bg-[#f7f6f1] shadow-xs'
                                                                : 'border-[#ccd1ca] bg-white hover:bg-[#f7f6f1]/60'
                                                        }`}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={isChecked}
                                                            onChange={() => toggleMetric(m.id)}
                                                            className="mt-0.5 accent-[#18221d] w-4 h-4 cursor-pointer"
                                                        />
                                                        <div className="flex-1">
                                                            <div className="flex items-center justify-between">
                                                                <span className="font-semibold text-[#18221d] text-xs">
                                                                    {m.label}
                                                                </span>
                                                                <span
                                                                    className="w-2.5 h-2.5 rounded-full inline-block border border-black/10"
                                                                    style={{ backgroundColor: m.color }}
                                                                />
                                                            </div>
                                                            <p className="text-[10px] text-[#687169] mt-0.5">
                                                                {m.desc}
                                                            </p>
                                                        </div>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ) : (
                                    <div>
                                        <label className="block text-[#18221d] font-medium mb-1">
                                            Métrica Asociada (DSL)
                                        </label>
                                        <select
                                            value={config.metric || 'nps'}
                                            onChange={(e) => updateConfigField('metric', e.target.value)}
                                            className="w-full border border-[#ccd1ca] px-3 py-2 text-xs bg-white focus:outline-none focus:border-[#18221d]"
                                        >
                                            <option value="nps">Net Promoter Score (NPS)</option>
                                            <option value="csat">Customer Satisfaction (CSAT)</option>
                                            <option value="professionalism">Professionalism Score</option>
                                            <option value="survey_volume">Survey Volume (Total Respuestas)</option>
                                        </select>
                                    </div>
                                )}

                                <div>
                                    <label className="block text-[#18221d] font-medium mb-1">
                                        Dimensión de Agrupación
                                    </label>
                                    <select
                                        value={config.dimension || 'supervisor'}
                                        onChange={(e) => updateConfigField('dimension', e.target.value)}
                                        className="w-full border border-[#ccd1ca] px-3 py-2 text-xs bg-white focus:outline-none focus:border-[#18221d]"
                                    >
                                        <option value="supervisor">Supervisor</option>
                                        <option value="agent">Agente</option>
                                        <option value="wave">Ola (Wave)</option>
                                        <option value="category">Categoría de Feedback</option>
                                        <option value="survey_date">Fecha de Encuesta</option>
                                    </select>
                                </div>
                            </div>
                        )}

                        {/* Section 3: Color & Styling */}
                        <div className="space-y-3 pt-4 border-t border-[#ccd1ca]">
                            <div className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#687169]">
                                <Palette className="w-3.5 h-3.5" />
                                <span>3. Paleta & Acento Visual</span>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {COLOR_PRESETS.map((p) => (
                                    <button
                                        key={p.value}
                                        type="button"
                                        onClick={() => updateConfigField('color', p.value)}
                                        className={`w-7 h-7 rounded-none border flex items-center justify-center transition-transform ${
                                            config.color === p.value ? 'scale-110 border-[#18221d] shadow-sm' : 'border-[#ccd1ca]'
                                        }`}
                                        style={{ backgroundColor: p.value }}
                                        title={p.label}
                                    >
                                        {config.color === p.value && (
                                            <Check className={`w-3.5 h-3.5 ${p.value === '#d7f45b' || p.value === '#aac69c' || p.value === '#d7bf70' ? 'text-[#18221d]' : 'text-white'}`} />
                                        )}
                                    </button>
                                ))}
                            </div>

                            <div className="flex items-center gap-2 mt-2">
                                <input
                                    type="color"
                                    value={config.color || '#18221d'}
                                    onChange={(e) => updateConfigField('color', e.target.value)}
                                    className="w-8 h-8 border border-[#ccd1ca] p-0.5 cursor-pointer bg-white"
                                />
                                <input
                                    type="text"
                                    value={config.color || '#18221d'}
                                    onChange={(e) => updateConfigField('color', e.target.value)}
                                    placeholder="#18221d"
                                    className="border border-[#ccd1ca] px-3 py-1.5 text-xs font-mono w-28 uppercase focus:outline-none focus:border-[#18221d]"
                                />
                            </div>
                        </div>

                        {/* Section 4: Target Line / Benchmark */}
                        {(isChart || type === 'metric') && (
                            <div className="space-y-3 pt-4 border-t border-[#ccd1ca]">
                                <div className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#687169]">
                                    <Target className="w-3.5 h-3.5" />
                                    <span>4. Línea de Meta Operacional</span>
                                </div>

                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={config.showTargetLine ?? false}
                                        onChange={(e) => updateConfigField('showTargetLine', e.target.checked)}
                                        className="accent-[#18221d] w-4 h-4 cursor-pointer"
                                    />
                                    <span className="font-medium text-[#18221d]">
                                        Mostrar línea horizontal de meta (markLine)
                                    </span>
                                </label>

                                {config.showTargetLine && (
                                    <div className="grid grid-cols-2 gap-3 pl-6 pt-1">
                                        <div>
                                            <label className="block text-[#687169] mb-1">
                                                Valor de Meta (%)
                                            </label>
                                            <input
                                                type="number"
                                                step="any"
                                                value={config.targetLineValue ?? 50}
                                                onChange={(e) => updateConfigField('targetLineValue', Number(e.target.value))}
                                                placeholder="50"
                                                className="w-full border border-[#ccd1ca] px-3 py-1.5 text-xs focus:outline-none focus:border-[#18221d]"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-[#687169] mb-1">
                                                Etiqueta
                                            </label>
                                            <input
                                                type="text"
                                                value={config.targetLineLabel || 'Meta'}
                                                onChange={(e) => updateConfigField('targetLineLabel', e.target.value)}
                                                placeholder="Meta 50%"
                                                className="w-full border border-[#ccd1ca] px-3 py-1.5 text-xs focus:outline-none focus:border-[#18221d]"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Section 5: Table Computed Column & Conditional Formatting */}
                        {type === 'table' && (
                            <div className="space-y-4 pt-4 border-t border-[#ccd1ca]">
                                <div className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#687169]">
                                    <Calculator className="w-3.5 h-3.5" />
                                    <span>5. Columna Calculada & Semáforos</span>
                                </div>

                                {/* Computed Column */}
                                <div className="bg-[#f7f6f1] p-3 border border-[#ccd1ca] space-y-2">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={config.computedColumn?.enabled ?? false}
                                            onChange={(e) =>
                                                updateConfigField('computedColumn', {
                                                    ...config.computedColumn,
                                                    enabled: e.target.checked,
                                                    name: config.computedColumn?.name || 'Calculada',
                                                    calculationType: config.computedColumn?.calculationType || 'percent_of_target',
                                                })
                                            }
                                            className="accent-[#18221d] w-4 h-4 cursor-pointer"
                                        />
                                        <span className="font-semibold text-[#18221d]">
                                            Activar Columna Calculada
                                        </span>
                                    </label>

                                    {config.computedColumn?.enabled && (
                                        <div className="space-y-2 pt-2">
                                            <div>
                                                <label className="block text-[#687169] mb-1">Nombre Columna</label>
                                                <input
                                                    type="text"
                                                    value={config.computedColumn.name}
                                                    onChange={(e) =>
                                                        updateConfigField('computedColumn', {
                                                            ...config.computedColumn,
                                                            name: e.target.value,
                                                        })
                                                    }
                                                    className="w-full border border-[#ccd1ca] px-2.5 py-1.5 bg-white text-xs"
                                                />
                                            </div>
                                            <div>
                                                <label className="block text-[#687169] mb-1">Tipo de Cálculo</label>
                                                <select
                                                    value={config.computedColumn.calculationType}
                                                    onChange={(e) =>
                                                        updateConfigField('computedColumn', {
                                                            ...config.computedColumn,
                                                            calculationType: e.target.value as any,
                                                        })
                                                    }
                                                    className="w-full border border-[#ccd1ca] px-2.5 py-1.5 bg-white text-xs"
                                                >
                                                    <option value="percent_of_target">% sobre meta (Valor / Meta * 100)</option>
                                                    <option value="diff_from_target">Diferencia vs meta (Valor - Meta)</option>
                                                    <option value="multiply_100">Multiplicar por 100 (Ratio a %)</option>
                                                    <option value="custom">Multiplicador libre</option>
                                                </select>
                                            </div>
                                            {config.computedColumn.calculationType === 'custom' && (
                                                <div>
                                                    <label className="block text-[#687169] mb-1">Factor Multiplicador</label>
                                                    <input
                                                        type="number"
                                                        step="any"
                                                        value={config.computedColumn.customMultiplier ?? 1}
                                                        onChange={(e) =>
                                                            updateConfigField('computedColumn', {
                                                                ...config.computedColumn,
                                                                customMultiplier: Number(e.target.value),
                                                            })
                                                        }
                                                        className="w-full border border-[#ccd1ca] px-2.5 py-1.5 bg-white text-xs"
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Conditional Formatting */}
                                <div className="bg-[#f7f6f1] p-3 border border-[#ccd1ca] space-y-2">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={config.conditionalFormatting?.enabled ?? false}
                                            onChange={(e) =>
                                                updateConfigField('conditionalFormatting', {
                                                    ...config.conditionalFormatting,
                                                    enabled: e.target.checked,
                                                    greenThreshold: config.conditionalFormatting?.greenThreshold ?? 0.65,
                                                    redThreshold: config.conditionalFormatting?.redThreshold ?? 0.55,
                                                    mode: config.conditionalFormatting?.mode || 'badge',
                                                })
                                            }
                                            className="accent-[#18221d] w-4 h-4 cursor-pointer"
                                        />
                                        <span className="font-semibold text-[#18221d]">
                                            Semáforos Condicionales
                                        </span>
                                    </label>

                                    {config.conditionalFormatting?.enabled && (
                                        <div className="space-y-2 pt-2">
                                            <div className="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label className="block text-[#687169] mb-1">Umbral Verde (≥)</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={config.conditionalFormatting.greenThreshold ?? 0.65}
                                                        onChange={(e) =>
                                                            updateConfigField('conditionalFormatting', {
                                                                ...config.conditionalFormatting,
                                                                greenThreshold: Number(e.target.value),
                                                            })
                                                        }
                                                        className="w-full border border-[#ccd1ca] px-2.5 py-1.5 bg-white text-xs font-mono"
                                                    />
                                                </div>
                                                <div>
                                                    <label className="block text-[#687169] mb-1">Umbral Rojo (&lt;)</label>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={config.conditionalFormatting.redThreshold ?? 0.55}
                                                        onChange={(e) =>
                                                            updateConfigField('conditionalFormatting', {
                                                                ...config.conditionalFormatting,
                                                                redThreshold: Number(e.target.value),
                                                            })
                                                        }
                                                        className="w-full border border-[#ccd1ca] px-2.5 py-1.5 bg-white text-xs font-mono"
                                                    />
                                                </div>
                                            </div>

                                            <div>
                                                <label className="block text-[#687169] mb-1">Estilo Visual</label>
                                                <select
                                                    value={config.conditionalFormatting.mode || 'badge'}
                                                    onChange={(e) =>
                                                        updateConfigField('conditionalFormatting', {
                                                            ...config.conditionalFormatting,
                                                            mode: e.target.value as any,
                                                        })
                                                    }
                                                    className="w-full border border-[#ccd1ca] px-2.5 py-1.5 bg-white text-xs"
                                                >
                                                    <option value="badge">Insignia (Badge con color e icono)</option>
                                                    <option value="background">Tinte de fondo en la celda</option>
                                                    <option value="bar">Mini barra de progreso horizontal</option>
                                                </select>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Section 6: Notes / Text Content */}
                        {type === 'text' && (
                            <div className="space-y-3 pt-4 border-t border-[#ccd1ca]">
                                <div className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[#687169]">
                                    <FileText className="w-3.5 h-3.5" />
                                    <span>Contenido de Notas Operativas</span>
                                </div>
                                <textarea
                                    rows={6}
                                    value={config.textContent || ''}
                                    onChange={(e) => updateConfigField('textContent', e.target.value)}
                                    placeholder="Escribe instrucciones operativas, notas de equipo o contexto para este dashboard..."
                                    className="w-full border border-[#ccd1ca] p-3 text-xs focus:outline-none focus:border-[#18221d] font-sans"
                                />
                            </div>
                        )}

                        {/* Section 7: Delete Widget */}
                        {onDelete && (
                            <div className="pt-4 border-t border-[#ccd1ca]">
                                <button
                                    type="button"
                                    onClick={() => {
                                        if (confirm('¿Estás seguro de eliminar este widget del dashboard?')) {
                                            onDelete(widget.id);
                                            onClose();
                                        }
                                    }}
                                    className="w-full py-2.5 px-4 text-xs font-medium text-[#943126] border border-[#e1a89e] bg-[#fff0ed] hover:bg-[#ffe5e0] flex items-center justify-center gap-1.5 transition-colors"
                                >
                                    <Trash2 className="w-3.5 h-3.5" />
                                    <span>Eliminar Widget</span>
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Footer Actions */}
                    <div className="p-4 border-t border-[#ccd1ca] bg-[#f7f6f1] flex items-center justify-end">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-6 py-2 text-xs font-semibold uppercase tracking-wider bg-[#18221d] text-white hover:bg-[#2c3e34] transition-colors flex items-center gap-1.5"
                        >
                            <Check className="w-3.5 h-3.5 text-[#d7f45b]" />
                            <span>Listo</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
