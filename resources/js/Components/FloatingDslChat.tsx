import React, { useState, useRef, useEffect } from 'react';
import {
    Code2,
    X,
    Send,
    Play,
    Copy,
    Check,
    RotateCcw,
    ChevronDown,
    Sparkles,
    CheckCircle2,
    AlertCircle,
    Sliders,
    Clock,
    Database,
    Wrench,
    HelpCircle,
    ArrowUpRight,
} from 'lucide-react';
import MarkdownRenderer from './MarkdownRenderer';
import { router } from '@inertiajs/react';

interface DslQuery {
    metric: string;
    aggregation?: string;
    group_by?: string[];
    filters?: { field: string; operator: string; value: any }[];
    date_range?: { from?: string; to?: string };
    limit?: number;
    sort_order?: string;
    [key: string]: any;
}

interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    dsl?: DslQuery | null;
    dsl_valid?: boolean;
    validation_error?: string | null;
    suggested_tool?: any | null;
    test_result?: {
        count: number;
        data: any[];
        duration_ms: number;
    } | null;
    testing?: boolean;
    created_at: Date;
}

const QUICK_PROMPTS = [
    'NPS promedio agrupado por supervisor',
    'Top 5 agentes con peor CSAT',
    'Volumen de encuestas por categoría',
    'CSAT y NPS en Wave 1',
];

export default function FloatingDslChat() {
    const [isOpen, setIsOpen] = useState<boolean>(false);
    const [input, setInput] = useState<string>('');
    const [loading, setLoading] = useState<boolean>(false);
    const [copiedDslId, setCopiedDslId] = useState<string | null>(null);

    const initialWelcome: ChatMessage = {
        id: 'welcome',
        role: 'assistant',
        content:
            '**Hola, soy el Copiloto de Consultas Query DSL de ATLAS.**\n\n' +
            'Describe en lenguaje natural qué métrica, filtro o agrupación necesitas y generaré el Query DSL 100% validado conforme al sistema. Puedes ejecutar pruebas en tiempo real o cargarlo como una nueva Tool DSL.',
        created_at: new Date(),
    };

    const [messages, setMessages] = useState<ChatMessage[]>(() => {
        const saved = sessionStorage.getItem('atlas_dsl_chat_history');
        if (saved) {
            try {
                const parsed = JSON.parse(saved);
                return parsed.map((m: any) => ({ ...m, created_at: new Date(m.created_at) }));
            } catch (e) {
                // Ignore parse errors
            }
        }
        return [initialWelcome];
    });

    const messagesEndRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        sessionStorage.setItem('atlas_dsl_chat_history', JSON.stringify(messages));
    }, [messages]);

    useEffect(() => {
        if (isOpen) {
            messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
        }
    }, [messages, isOpen]);

    const handleSendMessage = async (textToSend?: string) => {
        const queryText = (textToSend || input).trim();
        if (!queryText || loading) return;

        const userMsg: ChatMessage = {
            id: 'msg_' + Date.now(),
            role: 'user',
            content: queryText,
            created_at: new Date(),
        };

        setMessages((prev) => [...prev, userMsg]);
        if (!textToSend) setInput('');
        setLoading(true);

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        // Format history for context
        const historyPayload = messages
            .filter((m) => m.id !== 'welcome')
            .slice(-6)
            .map((m) => ({
                role: m.role,
                content: m.content,
            }));

        try {
            const res = await fetch('/admin/dsl-assistant/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    message: queryText,
                    history: historyPayload,
                }),
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || 'Error al comunicarse con el asistente DSL');
            }

            const assistantMsg: ChatMessage = {
                id: 'asst_' + Date.now(),
                role: 'assistant',
                content: data.content,
                dsl: data.dsl,
                dsl_valid: data.dsl_valid,
                validation_error: data.validation_error,
                suggested_tool: data.suggested_tool,
                created_at: new Date(),
            };

            setMessages((prev) => [...prev, assistantMsg]);
        } catch (err: any) {
            const errorMsg: ChatMessage = {
                id: 'err_' + Date.now(),
                role: 'assistant',
                content: `⚠️ **Error:** ${err.message}`,
                created_at: new Date(),
            };
            setMessages((prev) => [...prev, errorMsg]);
        } finally {
            setLoading(false);
        }
    };

    const handleCopyDsl = (msgId: string, dsl: DslQuery) => {
        navigator.clipboard.writeText(JSON.stringify(dsl, null, 2));
        setCopiedDslId(msgId);
        setTimeout(() => setCopiedDslId(null), 2000);
    };

    const handleRunTest = async (msgId: string, dsl: DslQuery) => {
        setMessages((prev) =>
            prev.map((m) => (m.id === msgId ? { ...m, testing: true } : m))
        );

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '';

        try {
            const res = await fetch('/admin/dsl-assistant/execute', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ dsl }),
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Error al ejecutar Query DSL en la base de datos');
            }

            setMessages((prev) =>
                prev.map((m) =>
                    m.id === msgId
                        ? {
                              ...m,
                              testing: false,
                              test_result: {
                                  count: data.count,
                                  data: data.data,
                                  duration_ms: data.duration_ms,
                              },
                          }
                        : m
                )
            );
        } catch (err: any) {
            alert(`Fallo en prueba de ejecución: ${err.message}`);
            setMessages((prev) =>
                prev.map((m) => (m.id === msgId ? { ...m, testing: false } : m))
            );
        }
    };

    const handleLoadIntoToolEditor = (dsl: DslQuery, suggestedTool: any) => {
        const eventDetail = {
            dsl,
            suggested_tool: suggestedTool,
        };

        if (window.location.pathname.startsWith('/admin/tools')) {
            window.dispatchEvent(new CustomEvent('load-dsl-into-editor', { detail: eventDetail }));
        } else {
            // Store temporarily in sessionStorage and navigate to /admin/tools
            sessionStorage.setItem('atlas_prefilled_tool_data', JSON.stringify(eventDetail));
            router.visit('/admin/tools');
        }
    };

    const handleClearChat = () => {
        setMessages([initialWelcome]);
        sessionStorage.removeItem('atlas_dsl_chat_history');
    };

    return (
        <>
            {/* Floating Trigger Button */}
            <div className="fixed bottom-6 right-6 z-40 flex items-center space-x-2">
                {!isOpen && (
                    <button
                        type="button"
                        onClick={() => setIsOpen(true)}
                        className="hidden sm:flex items-center space-x-1.5 px-3 py-1.5 bg-white border border-[#ccd1ca] shadow-md hover:border-[#18221d] text-xs font-semibold text-[#18221d] font-mono transition-all animate-in fade-in duration-300"
                    >
                        <Sparkles className="w-3.5 h-3.5 text-[#2e5e33]" />
                        <span>Copiloto DSL</span>
                    </button>
                )}

                <button
                    type="button"
                    onClick={() => setIsOpen(!isOpen)}
                    title={isOpen ? 'Cerrar Asistente DSL' : 'Abrir Asistente para Creación de Consultas DSL'}
                    className="w-13 h-13 rounded-full bg-[#18221d] text-white shadow-2xl hover:scale-105 transition-all flex items-center justify-center border-2 border-[#d7f45b] relative group"
                >
                    {isOpen ? (
                        <X className="w-6 h-6 text-white" />
                    ) : (
                        <>
                            <Code2 className="w-6 h-6 text-white group-hover:scale-110 transition-transform" />
                            <span className="w-2.5 h-2.5 rounded-full bg-[#d7f45b] absolute top-0.5 right-0.5 border border-[#18221d]" />
                        </>
                    )}
                </button>
            </div>

            {/* Chat Drawer / Popup */}
            {isOpen && (
                <div className="fixed bottom-22 right-6 z-50 w-[440px] max-w-[calc(100vw-2rem)] h-[620px] max-h-[calc(100vh-7.5rem)] bg-[#f7f6f1] border border-[#ccd1ca] shadow-2xl flex flex-col overflow-hidden animate-in slide-in-from-bottom-5 duration-200">
                    {/* Header */}
                    <div className="p-4 bg-[#18221d] text-white border-b border-[#ccd1ca] flex items-center justify-between flex-shrink-0">
                        <div className="flex items-center space-x-2.5">
                            <div className="w-8 h-8 rounded-full bg-white text-[#18221d] flex items-center justify-center font-serif font-bold text-sm relative">
                                A
                                <span className="w-2 h-2 rounded-full bg-[#d7f45b] absolute -top-0.5 -right-0.5 border border-[#18221d]" />
                            </div>
                            <div>
                                <h3 className="font-serif text-lg leading-none">Copiloto Query DSL</h3>
                                <span className="text-[9px] uppercase font-mono tracking-widest text-[#d7f45b] mt-0.5 block">
                                    GENERADOR & VALIDADOR DSL
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center space-x-1">
                            <button
                                type="button"
                                onClick={handleClearChat}
                                title="Limpiar historial"
                                className="p-1.5 text-gray-300 hover:text-white hover:bg-white/10 transition-colors"
                            >
                                <RotateCcw className="w-3.5 h-3.5" />
                            </button>
                            <button
                                type="button"
                                onClick={() => setIsOpen(false)}
                                title="Minimizar chat"
                                className="p-1.5 text-gray-300 hover:text-white hover:bg-white/10 transition-colors"
                            >
                                <ChevronDown className="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    {/* Messages Body */}
                    <div className="flex-1 overflow-y-auto p-4 space-y-4">
                        {messages.map((msg) => {
                            const isUser = msg.role === 'user';
                            return (
                                <div
                                    key={msg.id}
                                    className={`flex flex-col ${isUser ? 'items-end' : 'items-start'}`}
                                >
                                    <div className="text-[11px] text-[#687169] mb-1.5 font-mono flex items-center gap-1.5">
                                        <span className="font-bold text-[#18221d]">{isUser ? 'Usted' : 'Arquitecto DSL'}</span>
                                        <span>·</span>
                                        <span>{msg.created_at.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                                    </div>

                                    <div
                                        className={`border max-w-[94%] shadow-xs ${
                                            isUser
                                                ? 'p-3.5 sm:px-4 sm:py-3 bg-[#18221d] text-white border-[#18221d] text-sm leading-relaxed font-sans'
                                                : 'p-4 sm:p-5 bg-white text-[#18221d] border-[#ccd1ca]'
                                        }`}
                                    >
                                        <MarkdownRenderer content={msg.content} />

                                        {/* DSL Interactive Card (If message has structured DSL) */}
                                        {msg.dsl && (
                                            <div className="mt-3 pt-3 border-t border-[#ccd1ca] space-y-2">
                                                <div className="flex items-center justify-between">
                                                    <span
                                                        className={`text-[9px] font-mono px-2 py-0.5 font-bold uppercase border flex items-center gap-1 ${
                                                            msg.dsl_valid
                                                                ? 'bg-emerald-50 text-emerald-800 border-emerald-300'
                                                                : 'bg-amber-50 text-amber-800 border-amber-300'
                                                        }`}
                                                    >
                                                        {msg.dsl_valid ? (
                                                            <>
                                                                <CheckCircle2 className="w-3 h-3 text-emerald-600" />
                                                                <span>DSL Validado (QueryEngine)</span>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <AlertCircle className="w-3 h-3 text-amber-600" />
                                                                <span>Ajuste Requerido</span>
                                                            </>
                                                        )}
                                                    </span>

                                                    <span className="text-[10px] font-mono text-[#687169]">
                                                        {msg.dsl.metric?.toUpperCase()}
                                                    </span>
                                                </div>

                                                {/* Action Buttons Toolbar */}
                                                <div className="flex flex-wrap gap-1.5 pt-1">
                                                    {/* Copy DSL */}
                                                    <button
                                                        type="button"
                                                        onClick={() => handleCopyDsl(msg.id, msg.dsl!)}
                                                        className="px-2.5 py-1 bg-[#f7f6f1] border border-[#ccd1ca] hover:bg-white text-[10px] font-mono text-[#18221d] flex items-center gap-1 transition-colors"
                                                    >
                                                        {copiedDslId === msg.id ? (
                                                            <>
                                                                <Check className="w-3 h-3 text-emerald-600" />
                                                                <span className="text-emerald-700 font-bold">Copiado</span>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Copy className="w-3 h-3 text-[#687169]" />
                                                                <span>Copiar DSL</span>
                                                            </>
                                                        )}
                                                    </button>

                                                    {/* Run Test (Dry Run) */}
                                                    <button
                                                        type="button"
                                                        disabled={msg.testing}
                                                        onClick={() => handleRunTest(msg.id, msg.dsl!)}
                                                        className="px-2.5 py-1 bg-[#dce4d8] border border-[#ccd1ca] hover:bg-[#d0dfcb] text-[10px] font-mono font-semibold text-[#18221d] flex items-center gap-1 transition-colors"
                                                    >
                                                        <Play className="w-3 h-3 fill-[#18221d]" />
                                                        <span>{msg.testing ? 'Ejecutando...' : '⚡ Probar en DB'}</span>
                                                    </button>

                                                    {/* Load into Tool */}
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleLoadIntoToolEditor(msg.dsl!, msg.suggested_tool)
                                                        }
                                                        className="px-2.5 py-1 bg-[#d7f45b] border border-[#18221d]/40 hover:bg-[#cbf03f] text-[10px] font-mono font-semibold text-[#18221d] flex items-center gap-1 transition-colors"
                                                        title="Cargar en el editor de herramientas DSL"
                                                    >
                                                        <Wrench className="w-3 h-3 text-[#18221d]" />
                                                        <span>Usar en Tool DSL</span>
                                                        <ArrowUpRight className="w-3 h-3 text-[#18221d]" />
                                                    </button>
                                                </div>

                                                {/* Test Execution Results Drawer inside message */}
                                                {msg.test_result && (
                                                    <div className="mt-2 p-2.5 bg-[#f7f6f1] border border-[#ccd1ca] text-[11px] font-mono space-y-1.5">
                                                        <div className="flex items-center justify-between text-[#18221d] font-bold">
                                                            <span className="flex items-center gap-1">
                                                                <Database className="w-3 h-3 text-[#2e5e33]" />
                                                                <span>Resultado: {msg.test_result.count} fila(s)</span>
                                                            </span>
                                                            <span className="text-[10px] text-[#687169] flex items-center gap-1">
                                                                <Clock className="w-2.5 h-2.5" />
                                                                {msg.test_result.duration_ms}ms
                                                            </span>
                                                        </div>

                                                        {msg.test_result.data.length > 0 ? (
                                                            <div className="overflow-x-auto max-h-36 border border-[#ccd1ca] bg-white">
                                                                <table className="w-full text-[10px] text-left border-collapse">
                                                                    <thead className="bg-[#f7f6f1] border-b border-[#ccd1ca]">
                                                                        <tr>
                                                                            {Object.keys(msg.test_result.data[0]).map((col) => (
                                                                                <th key={col} className="p-1 px-1.5 font-bold border-r border-[#ccd1ca] last:border-r-0">
                                                                                    {col}
                                                                                </th>
                                                                            ))}
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody className="divide-y divide-[#ccd1ca]/60">
                                                                        {msg.test_result.data.slice(0, 5).map((row, rIdx) => (
                                                                            <tr key={rIdx}>
                                                                                {Object.values(row).map((val: any, cIdx) => (
                                                                                    <td key={cIdx} className="p-1 px-1.5 border-r border-[#ccd1ca]/60 last:border-r-0 truncate max-w-[100px]">
                                                                                        {typeof val === 'number' ? Math.round(val * 1000) / 1000 : String(val)}
                                                                                    </td>
                                                                                ))}
                                                                            </tr>
                                                                        ))}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        ) : (
                                                            <div className="p-2 text-center text-[#687169] text-[10px]">
                                                                La consulta no retornó registros con los filtros indicados.
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            );
                        })}

                        {loading && (
                            <div className="flex items-center space-x-2 text-xs font-mono text-[#687169] bg-white p-3 border border-[#ccd1ca] max-w-[80%]">
                                <Sparkles className="w-4 h-4 animate-spin text-[#18221d]" />
                                <span>Construyendo y validando Query DSL...</span>
                            </div>
                        )}

                        <div ref={messagesEndRef} />
                    </div>

                    {/* Quick Suggestions Chips */}
                    <div className="px-3 py-2 bg-white border-t border-[#ccd1ca] overflow-x-auto flex items-center space-x-1.5 flex-shrink-0">
                        <span className="text-[10px] font-mono text-[#687169] whitespace-nowrap mr-1 flex items-center gap-0.5">
                            <Sliders className="w-3 h-3" />
                            Ideas:
                        </span>
                        {QUICK_PROMPTS.map((prompt, idx) => (
                            <button
                                key={idx}
                                type="button"
                                onClick={() => handleSendMessage(prompt)}
                                className="text-[10px] font-sans px-2 py-0.5 bg-[#f7f6f1] border border-[#ccd1ca] hover:border-[#18221d] text-[#18221d] whitespace-nowrap transition-colors"
                            >
                                {prompt}
                            </button>
                        ))}
                    </div>

                    {/* Input Composer */}
                    <div className="p-3 bg-[#f7f6f1] border-t border-[#ccd1ca] flex-shrink-0">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                handleSendMessage();
                            }}
                            className="flex items-center space-x-2"
                        >
                            <input
                                type="text"
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                placeholder="Describe la consulta DSL que necesitas..."
                                className="flex-1 px-3 py-2.5 bg-white border border-[#ccd1ca] text-xs sm:text-sm font-sans text-[#18221d] focus:outline-none focus:border-[#18221d]"
                            />

                            <button
                                type="submit"
                                disabled={!input.trim() || loading}
                                className="px-4 py-2.5 bg-[#d7f45b] text-[#18221d] border border-[#18221d] hover:bg-[#cbf03f] disabled:opacity-50 text-xs font-semibold uppercase flex items-center justify-center transition-colors cursor-pointer"
                            >
                                <Send className="w-3.5 h-3.5" />
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </>
    );
}
