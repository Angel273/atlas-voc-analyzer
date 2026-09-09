import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { UploadCloud, FileSpreadsheet, ArrowRight, CheckCircle2, AlertTriangle, XCircle, Clock, Database, ChevronRight, Trash2, RefreshCw, Cpu, Check, Layers, Play } from 'lucide-react';

interface ImportRecord {
    id: number;
    original_filename: string;
    sheet_name: string;
    header_row: number;
    row_count: number;
    accepted_rows: number;
    rejected_rows: number;
    duplicate_rows: number;
    status: string;
    created_at: string;
    user?: { name: string };
    errors?: any[];
}

interface Template {
    id: number;
    name: string;
    description: string;
    fields: { internal_field: string; source_column: string; transformations: any }[];
}

interface CategorizationStatus {
    total_surveys: number;
    total_verbatims: number;
    completed: number;
    failed: number;
    pending: number;
    percentage: number;
    is_processing: boolean;
    jobs_in_queue?: number;
    running_jobs?: number;
}

interface Props {
    imports: { data: ImportRecord[]; links: any[] };
    templates: Template[];
    categorization_status?: CategorizationStatus;
}

const ATLAS_ATTRIBUTES = [
    { key: 'survey_id', label: 'Survey ID', required: true, desc: 'Identificador único de encuesta' },
    { key: 'nps_score', label: 'NPS Score', required: true, desc: 'Rango numérico [-1 a 1]' },
    { key: 'csat_score', label: 'CSAT Score', required: true, desc: 'Rango numérico [-1 a 1]' },
    { key: 'professionalism_score', label: 'Professionalism Score', required: true, desc: 'Rango numérico [-1 a 1]' },
    { key: 'verbatim', label: 'Verbatim / Comentario', required: false, desc: 'Texto de opinión del cliente' },
    { key: 'agent_name', label: 'Nombre del Agente', required: false, desc: 'Nombre del representante' },
    { key: 'agent_bms', label: 'Agent BMS ID', required: true, desc: 'ID/código del representante' },
    { key: 'supervisor', label: 'Supervisor / Team Leader', required: true, desc: 'Nombre del supervisor' },
    { key: 'survey_date', label: 'Survey Date', required: true, desc: 'Fecha de la respuesta (YYYY-MM-DD)' },
    { key: 'wave', label: 'Wave / Cohorte', required: false, desc: 'Ola u orden de contratación' },
    { key: 'tenure_days', label: 'Tenure Days', required: false, desc: 'Días en producción (>= 0)' },
];

export default function DataIndex({ imports, templates, categorization_status }: Props) {
    const [step, setStep] = useState<number>(1);
    const [uploading, setUploading] = useState<boolean>(false);
    const [fileToken, setFileToken] = useState<string>('');
    const [originalFilename, setOriginalFilename] = useState<string>('');
    const [sheets, setSheets] = useState<{ name: string; approximate_rows: number }[]>([]);
    const [selectedSheet, setSelectedSheet] = useState<string>('');
    const [headerRow, setHeaderRow] = useState<number>(1);

    const [previewHeaders, setPreviewHeaders] = useState<Record<string, string>>({});
    const [sampleRows, setSampleRows] = useState<any[]>([]);
    const [mapping, setMapping] = useState<Record<string, string>>({});
    const [transformations, setTransformations] = useState<Record<string, any>>({});

    const [validationResult, setValidationResult] = useState<any>(null);
    const [validating, setValidating] = useState<boolean>(false);
    const [importing, setImporting] = useState<boolean>(false);
    const [templateName, setTemplateName] = useState<string>('');

    const [catStatus, setCatStatus] = useState<CategorizationStatus | null>(categorization_status || null);
    const [refreshingCat, setRefreshingCat] = useState<boolean>(false);
    const [deletingImportId, setDeletingImportId] = useState<number | null>(null);
    const [clearingAll, setClearingAll] = useState<boolean>(false);

    // Poll categorization status if in progress
    const fetchCatStatus = async () => {
        try {
            const res = await fetch('/data/categorization-status');
            const data = await res.json();
            setCatStatus(data);
        } catch (err) {
            console.error('Error fetching categorization status:', err);
        }
    };

    useEffect(() => {
        if (catStatus?.is_processing) {
            const interval = setInterval(fetchCatStatus, 3000);
            return () => clearInterval(interval);
        }
    }, [catStatus?.is_processing]);

    const handleTriggerCategorization = async () => {
        setRefreshingCat(true);
        try {
            await fetch('/data/process-categorization', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });
            await fetchCatStatus();
        } catch (err) {
            console.error(err);
        } finally {
            setRefreshingCat(false);
        }
    };

    const handleDeleteImport = async (id: number) => {
        if (!confirm(`¿Eliminar la importación #${id}? Esto eliminará todas las encuestas asociadas a este lote y recalculará los totales.`)) {
            return;
        }

        setDeletingImportId(id);
        try {
            const res = await fetch(`/data/imports/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Error al eliminar importación');
            router.reload();
        } catch (err: any) {
            alert(err.message);
        } finally {
            setDeletingImportId(null);
        }
    };

    const handleClearAll = async () => {
        if (!confirm('¿ATENCIÓN: Estás seguro de eliminar TODAS las encuestas e importaciones anteriores? Esta acción no se puede deshacer y reiniciará la base de datos a 0 registros.')) {
            return;
        }

        setClearingAll(true);
        try {
            const res = await fetch('/data/clear-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Error al reiniciar datos');
            router.reload();
        } catch (err: any) {
            alert(err.message);
        } finally {
            setClearingAll(false);
        }
    };

    // Handle File Drop or Upload
    const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setUploading(true);
        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await fetch('/data/upload', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: formData,
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Upload failed');

            setFileToken(data.file_token);
            setOriginalFilename(data.original_filename);
            setSheets(data.sheets);
            if (data.sheets.length > 0) {
                setSelectedSheet(data.sheets[0].name);
            }
            setStep(2); // Advance to Sheet & Header Selection
        } catch (err: any) {
            alert(err.message);
        } finally {
            setUploading(false);
        }
    };

    // Load Sheet Preview & Header Mapping Suggestions
    const handleLoadPreview = async () => {
        setUploading(true);
        try {
            const res = await fetch('/data/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    file_token: fileToken,
                    sheet_name: selectedSheet,
                    header_row: headerRow,
                }),
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Preview failed');

            setPreviewHeaders(data.preview.headers);
            setSampleRows(data.preview.sample_rows);

            // Apply deterministic mapping suggestions
            const initialMapping: Record<string, string> = {};
            if (data.suggestions) {
                Object.entries(data.suggestions).forEach(([attr, item]: [string, any]) => {
                    initialMapping[attr] = item.column_key;
                });
            }
            setMapping(initialMapping);
            setStep(3); // Advance to Mapping & Transformation
        } catch (err: any) {
            alert(err.message);
        } finally {
            setUploading(false);
        }
    };

    // Pre-flight Validation Preview
    const handleValidateMapping = async () => {
        setValidating(true);
        try {
            const res = await fetch('/data/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    file_token: fileToken,
                    sheet_name: selectedSheet,
                    header_row: headerRow,
                    column_mapping: mapping,
                    transformations,
                }),
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Validation failed');

            setValidationResult(data);
            setStep(4); // Advance to Validation Preview
        } catch (err: any) {
            alert(err.message);
        } finally {
            setValidating(false);
        }
    };

    // Confirm & Execute Import
    const handleExecuteImport = async () => {
        setImporting(true);
        try {
            const res = await fetch('/data/execute', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    file_token: fileToken,
                    original_filename: originalFilename,
                    sheet_name: selectedSheet,
                    header_row: headerRow,
                    column_mapping: mapping,
                    transformations,
                }),
            });

            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Execution failed');

            // Reset wizard state and reload list
            setStep(1);
            setFileToken('');
            setValidationResult(null);
            router.reload();
        } catch (err: any) {
            alert(err.message);
        } finally {
            setImporting(false);
        }
    };

    // Apply Saved Template
    const applyTemplate = (t: Template) => {
        const newMapping: Record<string, string> = {};
        const newTransforms: Record<string, any> = {};

        t.fields.forEach((f) => {
            // Find corresponding column key by matching header name
            const matchedCol = Object.entries(previewHeaders).find(([k, h]) => h.trim().toLowerCase() === f.source_column.trim().toLowerCase());
            if (matchedCol) {
                newMapping[f.internal_field] = matchedCol[0];
            }
            if (f.transformations) {
                newTransforms[f.internal_field] = f.transformations;
            }
        });

        setMapping((prev) => ({ ...prev, ...newMapping }));
        setTransformations((prev) => ({ ...prev, ...newTransforms }));
    };

    return (
        <AppLayout
            title="Data Center & Importations"
            kicker="EXCEL INGESTION ENGINE"
            description="Upload multi-sheet Excel files with deterministic column mapping, explicit percentage transformations, pre-flight validation, and idempotent change auditing."
        >
            <Head title="Data Ingestion — ATLAS VOC Analysis" />

            {/* Stepper Wizard Bar */}
            <div className="bg-white border border-[#ccd1ca] p-4 mb-8 flex items-center justify-between">
                {[
                    { s: 1, label: '1. Cargar archivo' },
                    { s: 2, label: '2. Hoja y Encabezados' },
                    { s: 3, label: '3. Mapeo de columnas' },
                    { s: 4, label: '4. Validación y Ejecución' },
                ].map((st, i) => (
                    <div key={st.s} className="flex items-center space-x-2">
                        <span className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                            step >= st.s ? 'bg-[#18221d] text-white' : 'bg-[#f7f6f1] border border-[#ccd1ca] text-[#687169]'
                        }`}>
                            {st.s}
                        </span>
                        <span className={`text-xs font-medium hidden sm:inline ${step === st.s ? 'text-[#18221d] font-bold' : 'text-[#687169]'}`}>
                            {st.label}
                        </span>
                        {i < 3 && <ChevronRight className="w-3.5 h-3.5 text-[#ccd1ca] hidden md:inline ml-4" />}
                    </div>
                ))}
            </div>

            {/* Step 1: Upload Dropzone */}
            {step === 1 && (
                <div className="border border-[#ccd1ca] bg-white p-12 text-center mb-12">
                    <div className="w-16 h-16 rounded-full bg-[#dce4d8] flex items-center justify-center mx-auto mb-4">
                        <FileSpreadsheet className="w-8 h-8 text-[#18221d]" />
                    </div>
                    <h3 className="font-serif text-3xl text-[#18221d] mb-2">
                        Subir archivo Excel de VOC
                    </h3>
                    <p className="text-sm text-[#687169] max-w-md mx-auto mb-8">
                        Formatos soportados: <code>.xlsx</code>, <code>.xls</code> o <code>.csv</code>. El archivo se inspecciona temporalmente y no se almacena permanentemente.
                    </p>

                    <label className="inline-flex items-center space-x-2 px-6 py-3 bg-[#d7f45b] text-[#18221d] border border-[#18221d] font-semibold text-sm cursor-pointer hover:bg-[#cbf03f] transition-colors">
                        <UploadCloud className="w-4 h-4" />
                        <span>{uploading ? 'Analizando libro...' : 'Seleccionar archivo Excel'}</span>
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={handleFileUpload}
                            disabled={uploading}
                            className="hidden"
                        />
                    </label>
                </div>
            )}

            {/* Step 2: Sheet Selection & Header Row */}
            {step === 2 && (
                <div className="border border-[#ccd1ca] bg-white p-8 mb-12">
                    <h3 className="font-serif text-2xl text-[#18221d] mb-4">
                        Configurar Hoja y Encabezados ({originalFilename})
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-2">
                                Seleccionar Hoja de Trabajo (Worksheet)
                            </label>
                            <select
                                value={selectedSheet}
                                onChange={(e) => setSelectedSheet(e.target.value)}
                                className="w-full p-2.5 bg-white border border-[#ccd1ca] text-sm text-[#18221d]"
                            >
                                {sheets.map((s) => (
                                    <option key={s.name} value={s.name}>
                                        {s.name} (~{s.approximate_rows.toLocaleString()} filas)
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-2">
                                Fila de Encabezados (Header Row)
                            </label>
                            <input
                                type="number"
                                min={1}
                                max={50}
                                value={headerRow}
                                onChange={(e) => setHeaderRow(parseInt(e.target.value) || 1)}
                                className="w-full p-2.5 bg-white border border-[#ccd1ca] text-sm text-[#18221d]"
                            />
                            <span className="text-[11px] text-[#687169] mt-1 block">
                                Si las filas 1-3 contienen metadatos del reporte, indique la fila 4.
                            </span>
                        </div>
                    </div>

                    <div className="flex justify-end space-x-3">
                        <button
                            onClick={() => setStep(1)}
                            className="px-4 py-2 border border-[#ccd1ca] text-xs font-semibold uppercase tracking-wider text-[#687169] hover:bg-[#f7f6f1]"
                        >
                            Cancelar
                        </button>
                        <button
                            onClick={handleLoadPreview}
                            disabled={uploading}
                            className="px-6 py-2 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black transition-colors"
                        >
                            {uploading ? 'Cargando vista previa...' : 'Continuar al mapeo →'}
                        </button>
                    </div>
                </div>
            )}

            {/* Step 3: Column Mapping & Transformations */}
            {step === 3 && (
                <div className="border border-[#ccd1ca] bg-white p-8 mb-12">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div>
                            <h3 className="font-serif text-2xl text-[#18221d]">Mapeo de Columnas</h3>
                            <p className="text-xs text-[#687169]">
                                Asocie las columnas de su Excel con los atributos requeridos de ATLAS VOC.
                            </p>
                        </div>

                        {templates.length > 0 && (
                            <div className="flex items-center space-x-2">
                                <span className="text-xs text-[#687169]">Usar plantilla:</span>
                                <select
                                    onChange={(e) => {
                                        const t = templates.find((tmp) => tmp.id === parseInt(e.target.value));
                                        if (t) applyTemplate(t);
                                    }}
                                    className="text-xs border border-[#ccd1ca] p-1.5 bg-white"
                                >
                                    <option value="">Seleccionar plantilla...</option>
                                    {templates.map((t) => (
                                        <option key={t.id} value={t.id}>{t.name}</option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    {/* Mapping Form Table */}
                    <div className="overflow-x-auto border border-[#ccd1ca] mb-8">
                        <table className="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr className="bg-[#f7f6f1] border-b border-[#ccd1ca]">
                                    <th className="py-3 px-4 font-semibold text-[#18221d]">Atributo Atlas VOC</th>
                                    <th className="py-3 px-4 font-semibold text-[#18221d]">Columna en Excel</th>
                                    <th className="py-3 px-4 font-semibold text-[#18221d]">Transformaciones explícitas</th>
                                    <th className="py-3 px-4 font-semibold text-[#18221d]">Estado</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#ccd1ca]/60">
                                {ATLAS_ATTRIBUTES.map((attr) => {
                                    const isMapped = !!mapping[attr.key];
                                    const canTransformPercentage = ['nps_score', 'csat_score', 'professionalism_score'].includes(attr.key);

                                    return (
                                        <tr key={attr.key} className="hover:bg-[#f7f6f1]/40">
                                            <td className="py-3 px-4">
                                                <div className="font-semibold text-[#18221d]">{attr.label}</div>
                                                <div className="text-[11px] text-[#687169]">{attr.desc}</div>
                                            </td>

                                            <td className="py-3 px-4">
                                                <select
                                                    value={mapping[attr.key] || ''}
                                                    onChange={(e) => setMapping((prev) => ({ ...prev, [attr.key]: e.target.value }))}
                                                    className="w-full p-2 bg-white border border-[#ccd1ca] text-xs focus:border-[#18221d]"
                                                >
                                                    <option value="">— No mapeada —</option>
                                                    {Object.entries(previewHeaders).map(([colKey, headerName]) => (
                                                        <option key={colKey} value={colKey}>
                                                            {headerName} ({colKey})
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>

                                            <td className="py-3 px-4">
                                                {canTransformPercentage ? (
                                                    <label className="flex items-center space-x-2 text-[11px] text-[#687169] cursor-pointer">
                                                        <input
                                                            type="checkbox"
                                                            checked={!!transformations[attr.key]?.percentage}
                                                            onChange={(e) => setTransformations((prev) => ({
                                                                ...prev,
                                                                [attr.key]: { ...prev[attr.key], percentage: e.target.checked }
                                                            }))}
                                                            className="accent-[#18221d]"
                                                        />
                                                        <span>Interpretar como porcentaje (/ 100)</span>
                                                    </label>
                                                ) : (
                                                    <span className="text-[#687169] text-[11px]">Normalización estándar</span>
                                                )}
                                            </td>

                                            <td className="py-3 px-4">
                                                {isMapped ? (
                                                    <span className="inline-flex items-center text-[10px] font-semibold text-[#2e5e33] bg-[#eef7e8] border border-[#aac69c] px-2 py-0.5">
                                                        Mapeada
                                                    </span>
                                                ) : attr.required ? (
                                                    <span className="inline-flex items-center text-[10px] font-semibold text-[#943126] bg-[#fff0ed] border border-[#e1a89e] px-2 py-0.5">
                                                        Requerida
                                                    </span>
                                                ) : (
                                                    <span className="text-[10px] text-[#687169]">Opcional</span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Light 10-20 Rows Preview */}
                    <div className="mb-8">
                        <h4 className="text-xs font-bold uppercase tracking-wider text-[#18221d] mb-3">
                            Vista Previa de Filas de Muestra ({sampleRows.length} filas)
                        </h4>
                        <div className="overflow-x-auto border border-[#ccd1ca] max-h-60">
                            <table className="w-full text-left text-xs border-collapse">
                                <thead className="bg-[#f7f6f1] sticky top-0">
                                    <tr className="border-b border-[#ccd1ca]">
                                        <th className="py-2 px-3 text-[#687169]">Fila</th>
                                        {Object.entries(previewHeaders).map(([col, name]) => (
                                            <th key={col} className="py-2 px-3 font-semibold text-[#18221d]">
                                                {name}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#ccd1ca]/60">
                                    {sampleRows.map((r, i) => (
                                        <tr key={i} className="hover:bg-[#f7f6f1]/50">
                                            <td className="py-2 px-3 text-[#687169] font-mono">{r.row_number}</td>
                                            {Object.keys(previewHeaders).map((col) => (
                                                <td key={col} className="py-2 px-3 text-[#18221d] truncate max-w-[160px]">
                                                    {r.values[col] !== null ? String(r.values[col]) : ''}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="flex justify-between items-center">
                        <button
                            onClick={() => setStep(2)}
                            className="px-4 py-2 border border-[#ccd1ca] text-xs font-semibold uppercase tracking-wider text-[#687169] hover:bg-[#f7f6f1]"
                        >
                            ← Volver a hoja
                        </button>
                        <button
                            onClick={handleValidateMapping}
                            disabled={validating}
                            className="px-6 py-2.5 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black transition-colors"
                        >
                            {validating ? 'Validando muestra...' : 'Validar datos →'}
                        </button>
                    </div>
                </div>
            )}

            {/* Step 4: Pre-flight Validation Preview & Execution */}
            {step === 4 && validationResult && (
                <div className="border border-[#ccd1ca] bg-white p-8 mb-12">
                    <h3 className="font-serif text-3xl text-[#18221d] mb-4">
                        Resultado de Pre-validación
                    </h3>

                    {/* Counts Summary Cards */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                        <div className="border border-[#aac69c] bg-[#eef7e8] p-5">
                            <span className="text-[11px] font-bold uppercase tracking-wider text-[#2e5e33]">Listas para Importar</span>
                            <div className="font-serif text-4xl text-[#18221d] mt-1">
                                {validationResult.ready_count.toLocaleString()}
                            </div>
                            <span className="text-xs text-[#2e5e33] mt-1 block">Sin inconsistencias</span>
                        </div>

                        <div className="border border-[#d7bf70] bg-[#fff8dc] p-5">
                            <span className="text-[11px] font-bold uppercase tracking-wider text-[#7a6418]">Advertencias</span>
                            <div className="font-serif text-4xl text-[#18221d] mt-1">
                                {validationResult.warning_count.toLocaleString()}
                            </div>
                            <span className="text-xs text-[#7a6418] mt-1 block">Transformaciones automáticas</span>
                        </div>

                        <div className="border border-[#e1a89e] bg-[#fff0ed] p-5">
                            <span className="text-[11px] font-bold uppercase tracking-wider text-[#943126]">Errores Bloqueantes</span>
                            <div className="font-serif text-4xl text-[#18221d] mt-1">
                                {validationResult.error_count.toLocaleString()}
                            </div>
                            <span className="text-xs text-[#943126] mt-1 block">Fuera de rango o campos vacíos</span>
                        </div>
                    </div>

                    {/* Sample Errors if any */}
                    {validationResult.sample_errors && validationResult.sample_errors.length > 0 && (
                        <div className="border border-[#e1a89e] bg-[#fff0ed]/40 p-4 mb-8">
                            <h4 className="text-xs font-bold uppercase tracking-wider text-[#943126] mb-2">
                                Ejemplos de errores detectados en la muestra:
                            </h4>
                            <ul className="space-y-1.5 text-xs text-[#18221d]">
                                {validationResult.sample_errors.map((errItem: any, idx: number) => (
                                    <li key={idx} className="font-mono">
                                        • Fila {errItem.row}: {errItem.errors.join(', ')}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    <div className="flex flex-col sm:flex-row justify-between items-center gap-4 pt-4 border-t border-[#ccd1ca]">
                        <button
                            onClick={() => setStep(3)}
                            className="px-4 py-2 border border-[#ccd1ca] text-xs font-semibold uppercase tracking-wider text-[#687169] hover:bg-[#f7f6f1]"
                        >
                            ← Corregir mapeo
                        </button>

                        <div className="flex items-center space-x-3">
                            <button
                                onClick={handleExecuteImport}
                                disabled={importing || !validationResult.can_import}
                                className={`px-6 py-3 font-semibold text-xs uppercase tracking-wider flex items-center gap-2 transition-colors ${
                                    validationResult.can_import
                                        ? 'bg-[#d7f45b] text-[#18221d] border border-[#18221d] hover:bg-[#cbf03f]'
                                        : 'bg-[#ccd1ca] text-[#687169] cursor-not-allowed'
                                }`}
                            >
                                <Database className="w-4 h-4" />
                                <span>{importing ? 'Procesando en lotes...' : 'Confirmar e Iniciar Importación'}</span>
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* AI Categorization Progress Card */}
            {catStatus && (
                <div className="border border-[#ccd1ca] bg-white p-6 mb-8">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                        <div className="flex items-center space-x-3">
                            <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
                                catStatus.is_processing
                                    ? 'bg-[#fff8dc] text-[#7a6418]'
                                    : catStatus.pending > 0
                                    ? 'bg-[#fefbe8] text-[#73510d]'
                                    : 'bg-[#eef7e8] text-[#2e5e33]'
                            }`}>
                                <Cpu className={`w-5 h-5 ${catStatus.is_processing ? 'animate-spin text-[#7a6418]' : ''}`} />
                            </div>
                            <div>
                                <h4 className="font-serif text-xl text-[#18221d]">
                                    Categorización Semántica con IA (Gemini)
                                </h4>
                                <p className="text-xs text-[#687169]">
                                    Clasificación automática de opiniones de clientes en la taxonomía gobernada.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-center space-x-3">
                            {catStatus.is_processing ? (
                                <span className="px-2.5 py-1 text-xs font-semibold border flex items-center gap-1.5 bg-[#fff8dc] border-[#d7bf70] text-[#7a6418]">
                                    <span className="w-2 h-2 rounded-full bg-[#7a6418] animate-ping" />
                                    <span>Procesando en segundo plano...</span>
                                </span>
                            ) : catStatus.pending > 0 ? (
                                <span className="px-2.5 py-1 text-xs font-semibold border flex items-center gap-1.5 bg-[#fefbe8] border-[#f3e39d] text-[#73510d]">
                                    <Clock className="w-3.5 h-3.5" />
                                    <span>Pendiente ({catStatus.pending})</span>
                                </span>
                            ) : (
                                <span className="px-2.5 py-1 text-xs font-semibold border flex items-center gap-1.5 bg-[#eef7e8] border-[#aac69c] text-[#2e5e33]">
                                    <Check className="w-3.5 h-3.5" />
                                    <span>Completado</span>
                                </span>
                            )}

                            {catStatus.pending > 0 && !catStatus.is_processing ? (
                                <button
                                    onClick={handleTriggerCategorization}
                                    disabled={refreshingCat}
                                    className="px-3 py-1.5 bg-[#18221d] text-white text-xs font-medium hover:bg-[#2c3e35] flex items-center gap-1.5 transition-colors disabled:opacity-50"
                                    title="Iniciar categorización para comentarios pendientes"
                                >
                                    <Play className={`w-3.5 h-3.5 fill-current ${refreshingCat ? 'animate-spin' : ''}`} />
                                    <span>{refreshingCat ? 'Iniciando...' : 'Categorizar pendientes'}</span>
                                </button>
                            ) : (
                                <button
                                    onClick={catStatus.is_processing ? fetchCatStatus : handleTriggerCategorization}
                                    disabled={refreshingCat}
                                    className="px-3 py-1.5 border border-[#ccd1ca] text-xs font-medium text-[#18221d] hover:bg-[#f7f6f1] flex items-center gap-1.5 disabled:opacity-50"
                                    title="Actualizar o reintentar comentarios pendientes"
                                >
                                    <RefreshCw className={`w-3.5 h-3.5 ${refreshingCat ? 'animate-spin' : ''}`} />
                                    <span>{refreshingCat ? 'Enviando...' : catStatus.is_processing ? 'Actualizar estado' : 'Revisar pendientes'}</span>
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Progress Bar & Stats */}
                    <div className="space-y-2">
                        <div className="flex justify-between text-xs font-mono">
                            <span className="text-[#18221d] font-semibold">
                                {catStatus.completed} de {catStatus.total_verbatims} comentarios clasificados
                            </span>
                            <span className="font-bold text-[#18221d]">{catStatus.percentage}%</span>
                        </div>
                        <div className="w-full h-2.5 bg-[#f7f6f1] border border-[#ccd1ca] overflow-hidden">
                            <div
                                className="h-full bg-[#18221d] transition-all duration-500 ease-out"
                                style={{ width: `${catStatus.percentage}%` }}
                            />
                        </div>
                        <div className="flex flex-wrap gap-4 text-[11px] text-[#687169] pt-1">
                            <span>Total encuestas: <strong className="text-[#18221d]">{catStatus.total_surveys}</strong></span>
                            <span>Con comentarios (verbatim): <strong className="text-[#18221d]">{catStatus.total_verbatims}</strong></span>
                            <span>Pendientes: <strong className="text-[#18221d]">{catStatus.pending}</strong></span>
                            {catStatus.failed > 0 && (
                                <span className="text-[#943126]">Fallidos: <strong>{catStatus.failed}</strong></span>
                            )}
                        </div>

                        {/* Helper banners */}
                        {catStatus.pending > 0 && !catStatus.is_processing && (
                            <div className="mt-3 p-3 bg-[#fefbe8] border border-[#f3e39d] text-xs text-[#73510d] flex items-center justify-between">
                                <span>
                                    Hay <strong>{catStatus.pending}</strong> comentarios pendientes de clasificar. Haz clic en <strong>"Categorizar pendientes"</strong> para despacharlos al proceso de IA.
                                </span>
                            </div>
                        )}

                        {catStatus.is_processing && (catStatus.jobs_in_queue ?? 0) > 0 && (catStatus.running_jobs ?? 0) === 0 && (
                            <div className="mt-3 p-3 bg-[#f7f6f1] border border-[#ccd1ca] text-xs text-[#687169] flex items-center justify-between">
                                <span>
                                    Trabajos en cola ({catStatus.jobs_in_queue}). Si el contador no avanza, recuerda tener activo el worker en otra terminal: <code className="font-mono bg-white px-1.5 py-0.5 border border-[#ccd1ca] text-[#18221d]">php artisan queue:work</code> o ejecutar <code className="font-mono bg-white px-1.5 py-0.5 border border-[#ccd1ca] text-[#18221d]">composer run dev</code>.
                                </span>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Past Imports Audit Table */}
            <div className="border border-[#ccd1ca] bg-white p-8">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                    <div>
                        <h3 className="font-serif text-2xl text-[#18221d]">
                            Historial de Importaciones
                        </h3>
                        <p className="text-xs text-[#687169]">
                            Gestión de archivos importados. Puedes eliminar lotes anteriores para que no afecten los totales.
                        </p>
                    </div>

                    {imports.data.length > 0 && (
                        <button
                            onClick={handleClearAll}
                            disabled={clearingAll}
                            className="px-3 py-1.5 border border-[#e1a89e] text-[#943126] bg-[#fff0ed] hover:bg-[#ffe3de] text-xs font-semibold flex items-center gap-1.5 transition-colors"
                        >
                            <Trash2 className="w-3.5 h-3.5" />
                            <span>{clearingAll ? 'Limpiando...' : 'Eliminar todos los datos y reiniciar'}</span>
                        </button>
                    )}
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr className="bg-[#f7f6f1] border-b border-[#ccd1ca]">
                                <th className="py-3 px-4 font-semibold text-[#18221d]">ID</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Archivo y Hoja</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Total Filas</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Aceptadas</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Duplicadas</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Rechazadas</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Estado</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Fecha</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d] text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#ccd1ca]/60">
                            {imports.data.length === 0 ? (
                                <tr>
                                    <td colSpan={9} className="py-6 text-center text-[#687169]">
                                        No hay importaciones registradas todavía.
                                    </td>
                                </tr>
                            ) : (
                                imports.data.map((imp) => (
                                    <tr key={imp.id} className="hover:bg-[#f7f6f1]/40">
                                        <td className="py-3 px-4 font-mono font-bold">#{imp.id}</td>
                                        <td className="py-3 px-4 font-medium text-[#18221d]">
                                            {imp.original_filename} <span className="text-[#687169]">({imp.sheet_name})</span>
                                        </td>
                                        <td className="py-3 px-4 font-mono">{imp.row_count}</td>
                                        <td className="py-3 px-4 font-mono text-[#2e5e33]">{imp.accepted_rows}</td>
                                        <td className="py-3 px-4 font-mono text-[#687169]">{imp.duplicate_rows}</td>
                                        <td className="py-3 px-4 font-mono text-[#943126]">{imp.rejected_rows}</td>
                                        <td className="py-3 px-4">
                                            <span className={`inline-block px-2 py-0.5 text-[10px] font-semibold border ${
                                                imp.status === 'completed'
                                                    ? 'bg-[#eef7e8] border-[#aac69c] text-[#2e5e33]'
                                                    : imp.status === 'processing'
                                                    ? 'bg-[#fff8dc] border-[#d7bf70] text-[#7a6418]'
                                                    : 'bg-[#fff0ed] border-[#e1a89e] text-[#943126]'
                                            }`}>
                                                {imp.status.toUpperCase()}
                                            </span>
                                        </td>
                                        <td className="py-3 px-4 text-[#687169]">{new Date(imp.created_at).toLocaleDateString()}</td>
                                        <td className="py-3 px-4 text-right">
                                            <button
                                                onClick={() => handleDeleteImport(imp.id)}
                                                disabled={deletingImportId === imp.id}
                                                className="px-2.5 py-1 text-xs text-[#943126] border border-[#e1a89e] hover:bg-[#fff0ed] transition-colors inline-flex items-center gap-1"
                                                title="Eliminar esta importación y sus encuestas asociadas"
                                            >
                                                <Trash2 className="w-3 h-3" />
                                                <span>{deletingImportId === imp.id ? '...' : 'Eliminar'}</span>
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
