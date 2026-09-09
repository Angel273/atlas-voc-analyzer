import React, { useState, useEffect } from 'react';
import { X, Play, Clock, Zap, Database, CheckCircle2, AlertCircle, FileText } from 'lucide-react';

interface Tool {
    name: string;
    label: string;
    description: string;
    parameters_schema?: any;
}

interface Props {
    tool: Tool | null;
    isOpen: boolean;
    onClose: () => void;
}

export default function ToolTestSandboxModal({ tool, isOpen, onClose }: Props) {
    if (!isOpen || !tool) return null;

    // Generate initial test arguments from parameters_schema properties
    const generateInitialArgs = () => {
        const props = tool.parameters_schema?.properties || {};
        const sample: Record<string, any> = {};
        for (const [key, def] of Object.entries<any>(props)) {
            if (def.enum && def.enum.length > 0) {
                sample[key] = def.enum[0];
            } else if (def.type === 'integer') {
                sample[key] = 5;
            } else if (def.type === 'string') {
                sample[key] = key === 'date_range' ? { from: '2026-01-01', to: '2026-12-31' } : 'ejemplo';
            }
        }
        if (Object.keys(sample).length === 0) {
            sample['metric'] = 'nps';
        }
        return JSON.stringify(sample, null, 2);
    };

    const [argsJson, setArgsJson] = useState<string>(generateInitialArgs());
    const [running, setRunning] = useState<boolean>(false);
    const [result, setResult] = useState<any | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        setArgsJson(generateInitialArgs());
        setResult(null);
        setError(null);
    }, [tool]);

    const handleRunTest = async (e: React.FormEvent) => {
        e.preventDefault();
        setRunning(true);
        setError(null);
        setResult(null);

        let parsedArgs = {};
        try {
            parsedArgs = argsJson.trim() ? JSON.parse(argsJson) : {};
        } catch (err: any) {
            setError(`JSON de argumentos inválido: ${err.message}`);
            setRunning(false);
            return;
        }

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        try {
            const res = await fetch('/admin/tools/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    tool_name: tool.name,
                    arguments: parsedArgs,
                }),
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Error al ejecutar herramienta en Sandbox');
            }

            setResult(data);
        } catch (err: any) {
            setError(err.message);
        } finally {
            setRunning(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto bg-black/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div className="bg-[#f7f6f1] border border-[#ccd1ca] max-w-4xl w-full shadow-2xl flex flex-col max-h-[90vh]">
                {/* Header */}
                <div className="p-5 border-b border-[#ccd1ca] bg-white flex items-center justify-between">
                    <div>
                        <div className="flex items-center space-x-2">
                            <span className="text-[10px] font-bold uppercase tracking-[0.16em] text-[#687169] block">
                                SANDBOX DE PRUEBAS · DRY RUN
                            </span>
                        </div>
                        <h3 className="font-serif text-2xl text-[#18221d]">
                            Probar Tool: <span className="font-mono text-xl">{tool.name}</span>
                        </h3>
                    </div>
                    <button
                        onClick={onClose}
                        className="p-1.5 border border-[#ccd1ca] hover:bg-[#f7f6f1] text-[#18221d] transition-colors"
                    >
                        <X className="w-4 h-4" />
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto p-6 space-y-6">
                    {/* Tool Description info */}
                    <div className="p-3 bg-white border border-[#ccd1ca] text-xs">
                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#687169] block mb-1">
                            Descripción que lee el LLM:
                        </span>
                        <p className="text-[#18221d] leading-relaxed font-sans">{tool.description}</p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Input Arguments Column */}
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <label className="text-[11px] font-bold uppercase tracking-wider text-[#18221d]">
                                    Argumentos de Prueba (JSON):
                                </label>
                                <span className="text-[10px] font-mono text-[#687169]">Simula la llamada de la IA</span>
                            </div>

                            <form onSubmit={handleRunTest} className="space-y-3">
                                <textarea
                                    value={argsJson}
                                    onChange={(e) => setArgsJson(e.target.value)}
                                    rows={10}
                                    className="w-full p-3 bg-[#18221d] text-[#d7f45b] font-mono text-xs leading-relaxed border border-[#ccd1ca]"
                                />

                                <button
                                    type="submit"
                                    disabled={running}
                                    className="w-full py-2.5 bg-[#d7f45b] text-[#18221d] border border-[#18221d] font-semibold text-xs uppercase tracking-wider flex items-center justify-center gap-1.5 hover:bg-[#cbf03f] transition-colors"
                                >
                                    <Play className="w-3.5 h-3.5 fill-[#18221d]" />
                                    <span>{running ? 'Ejecutando Sandbox...' : 'Ejecutar Tool de Prueba'}</span>
                                </button>
                            </form>
                        </div>

                        {/* Result Output Column */}
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <label className="text-[11px] font-bold uppercase tracking-wider text-[#18221d]">
                                    Resultado Devuelto:
                                </label>
                                {result && (
                                    <span className="text-[10px] font-mono text-emerald-800 font-bold bg-emerald-100 px-1.5 py-0.5 border border-emerald-300">
                                        {result.status?.toUpperCase() || 'ÉXITO'}
                                    </span>
                                )}
                            </div>

                            {error && (
                                <div className="p-3 bg-red-50 border border-red-200 text-red-800 text-xs flex items-start gap-2">
                                    <AlertCircle className="w-4 h-4 text-red-600 flex-shrink-0 mt-0.5" />
                                    <span>{error}</span>
                                </div>
                            )}

                            {!result && !error && (
                                <div className="h-64 bg-white border border-[#ccd1ca] flex flex-col items-center justify-center text-center p-6 text-xs text-[#687169]">
                                    <FileText className="w-8 h-8 text-[#ccd1ca] mb-2" />
                                    <p>Ingresa los argumentos deseados y haz clic en "Ejecutar Tool de Prueba" para ver el resultado y el peso estimado en tokens.</p>
                                </div>
                            )}

                            {result && (
                                <div className="space-y-3">
                                    {/* Metrics strip */}
                                    <div className="grid grid-cols-3 gap-2">
                                        <div className="p-2 bg-white border border-[#ccd1ca] text-center">
                                            <span className="text-[9px] font-bold uppercase text-[#687169] block">
                                                Duración
                                            </span>
                                            <span className="font-mono text-xs font-bold text-[#18221d]">
                                                {result.duration_ms} ms
                                            </span>
                                        </div>

                                        <div className="p-2 bg-white border border-[#ccd1ca] text-center">
                                            <span className="text-[9px] font-bold uppercase text-[#687169] block">
                                                Tamaño
                                            </span>
                                            <span className="font-mono text-xs font-bold text-[#18221d]">
                                                {result.output_size_bytes} B
                                            </span>
                                        </div>

                                        <div className="p-2 bg-[#dce4d8]/60 border border-[#ccd1ca] text-center">
                                            <span className="text-[9px] font-bold uppercase text-[#18221d] block">
                                                Tokens Estimados
                                            </span>
                                            <span className="font-mono text-xs font-bold text-[#18221d]">
                                                ~{result.estimated_tokens}
                                            </span>
                                        </div>
                                    </div>

                                    {/* JSON viewer */}
                                    <pre className="bg-[#18221d] text-white p-3 font-mono text-xs leading-relaxed max-h-56 overflow-y-auto border border-[#ccd1ca]">
                                        {JSON.stringify(result.result, null, 2)}
                                    </pre>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <div className="p-4 border-t border-[#ccd1ca] bg-white flex justify-end">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black"
                    >
                        Cerrar Sandbox
                    </button>
                </div>
            </div>
        </div>
    );
}
