import React, { useState, useRef, useEffect, useMemo } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Send,
    Plus,
    MessageSquare,
    ShieldCheck,
    Eye,
    EyeOff,
    Sparkles,
    ChevronDown,
    ChevronRight,
    CheckCircle2,
    Calculator,
    Layers,
    Lightbulb,
    Trash2,
    Search,
    Bot,
    Cpu,
    Clock,
    Database,
    AlertCircle,
    SlidersHorizontal,
} from 'lucide-react';
import MarkdownRenderer from '@/Components/MarkdownRenderer';

interface GroundingCitation {
    title: string;
    factType: 'fact' | 'calculation' | 'interpretation' | 'structure';
    resourceId?: string;
    queryHash?: string;
}

interface ToolExecution {
    tool_name: string;
    parameters_redacted: Record<string, any>;
    result_summary: any;
    duration_ms: number;
    status: 'success' | 'error';
    citations?: GroundingCitation[];
}

interface Message {
    id: string;
    sender_type: 'user' | 'assistant' | 'tool';
    display_content: string;
    ai_content: string;
    grounding_context?: GroundingCitation[];
    tokens_used?: number;
    tool_calls?: ToolExecution[];
    metadata?: {
        ai_run_id?: string;
        tokens_used?: number;
        latency_ms?: number;
        grounding_context?: GroundingCitation[];
        tool_executions?: ToolExecution[];
        provider?: string;
        model?: string;
    };
    created_at: string;
}

interface Conversation {
    id: string;
    title: string;
    messages: Message[];
    updated_at: string;
}

interface ActiveModelInfo {
    provider: string;
    provider_name: string;
    model: string;
    is_mock: boolean;
}

interface Props {
    conversations: { id: string; title: string; messages_count: number; updated_at: string }[];
    active_conversation: Conversation | null;
    active_model?: ActiveModelInfo;
}

const SUGGESTED_PROMPTS = [
    '¿Qué supervisores están por debajo del promedio de NPS?',
    'Compara el CSAT y el NPS general.',
    '¿Cuál es la categoría con más comentarios negativos?',
    'Proyecta el NPS para los próximos 14 días.',
];



function GroundingEvidenceDrawer({
    citations = [],
    toolExecutions = [],
}: {
    citations: GroundingCitation[];
    toolExecutions: ToolExecution[];
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [inspectingTool, setInspectingTool] = useState<number | null>(null);

    const hasContent = (citations && citations.length > 0) || (toolExecutions && toolExecutions.length > 0);
    if (!hasContent) return null;

    const getCitationBadge = (factType: string) => {
        switch (factType) {
            case 'calculation':
                return {
                    icon: <Calculator className="w-3 h-3 text-emerald-700" />,
                    bg: 'bg-emerald-50 text-emerald-800 border-emerald-200',
                    label: 'CÁLCULO',
                };
            case 'structure':
                return {
                    icon: <Layers className="w-3 h-3 text-purple-700" />,
                    bg: 'bg-purple-50 text-purple-800 border-purple-200',
                    label: 'ESTRUCTURA',
                };
            case 'interpretation':
                return {
                    icon: <Lightbulb className="w-3 h-3 text-amber-700" />,
                    bg: 'bg-amber-50 text-amber-800 border-amber-200',
                    label: 'INTERPRETACIÓN',
                };
            case 'fact':
            default:
                return {
                    icon: <CheckCircle2 className="w-3 h-3 text-blue-700" />,
                    bg: 'bg-blue-50 text-blue-800 border-blue-200',
                    label: 'HECHO',
                };
        }
    };

    return (
        <div className="mt-3 pt-3 border-t border-[#ccd1ca]/60">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex items-center gap-1.5 text-[11px] font-mono uppercase tracking-wider text-[#687169] hover:text-[#18221d] transition-colors group"
            >
                {isOpen ? (
                    <ChevronDown className="w-3.5 h-3.5 text-[#18221d]" />
                ) : (
                    <ChevronRight className="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" />
                )}
                <span className="font-semibold text-[#18221d]">Fuentes y evidencia gobernada</span>
                <span className="bg-[#f7f6f1] border border-[#ccd1ca] px-1.5 py-0.2 rounded text-[10px] text-[#18221d]">
                    {citations.length} ref{citations.length === 1 ? '' : 's'}
                </span>
                {toolExecutions.length > 0 && (
                    <span className="text-[10px] text-[#687169]">
                        · {toolExecutions.length} tool{toolExecutions.length === 1 ? '' : 's'}
                    </span>
                )}
            </button>

            {isOpen && (
                <div className="mt-3 space-y-3 bg-[#f7f6f1]/70 p-3 border border-[#ccd1ca]/80 text-xs">
                    {/* Citations list */}
                    {citations.length > 0 && (
                        <div>
                            <div className="text-[10px] font-mono uppercase tracking-wider text-[#687169] mb-1.5 flex items-center gap-1">
                                <Database className="w-3 h-3" />
                                <span>Citas de grounding verificadas</span>
                            </div>
                            <div className="space-y-1.5">
                                {citations.map((c, i) => {
                                    const badge = getCitationBadge(c.factType);
                                    return (
                                        <div
                                            key={i}
                                            className="p-2 bg-white border border-[#ccd1ca] flex items-start justify-between gap-2"
                                        >
                                            <div className="flex items-start gap-2 flex-1 min-w-0">
                                                <span className="mt-0.5">{badge.icon}</span>
                                                <div className="flex-1 min-w-0">
                                                    <span className="text-xs text-[#18221d] leading-snug block">
                                                        {c.title}
                                                    </span>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        <span
                                                            className={`text-[9px] font-mono px-1.5 py-0.5 border font-semibold ${badge.bg}`}
                                                        >
                                                            {badge.label}
                                                        </span>
                                                        {c.resourceId && (
                                                            <span className="text-[10px] font-mono text-[#687169]">
                                                                ID: {c.resourceId}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            {c.queryHash && (
                                                <span
                                                    title={`Hash de consulta auditado: ${c.queryHash}`}
                                                    className="text-[9px] font-mono bg-[#f7f6f1] border border-[#ccd1ca] px-1.5 py-0.5 text-[#687169] flex-shrink-0"
                                                >
                                                    #{c.queryHash}
                                                </span>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Tool Executions list */}
                    {toolExecutions.length > 0 && (
                        <div>
                            <div className="text-[10px] font-mono uppercase tracking-wider text-[#687169] mb-1.5 flex items-center gap-1">
                                <Cpu className="w-3 h-3" />
                                <span>Trazabilidad de herramientas (ReAct Loop)</span>
                            </div>
                            <div className="space-y-1.5">
                                {toolExecutions.map((t, idx) => {
                                    const isSelected = inspectingTool === idx;
                                    return (
                                        <div
                                            key={idx}
                                            className="bg-white border border-[#ccd1ca] overflow-hidden"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => setInspectingTool(isSelected ? null : idx)}
                                                className="w-full text-left p-2 flex items-center justify-between hover:bg-[#f7f6f1] transition-colors"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <span className="font-mono text-xs font-bold text-[#18221d]">
                                                        {t.tool_name}
                                                    </span>
                                                    <span className="text-[10px] font-mono px-1.5 py-0.2 border bg-[#f7f6f1] border-[#ccd1ca] text-[#687169] flex items-center gap-1">
                                                        <Clock className="w-2.5 h-2.5" />
                                                        {t.duration_ms}ms
                                                    </span>
                                                    <span
                                                        className={`text-[9px] font-mono px-1 py-0.2 font-semibold uppercase ${
                                                            t.status === 'success'
                                                                ? 'bg-emerald-100 text-emerald-800'
                                                                : 'bg-red-100 text-red-800'
                                                        }`}
                                                    >
                                                        {t.status === 'success' ? 'Éxito' : 'Error'}
                                                    </span>
                                                </div>
                                                <span className="text-[10px] font-mono text-[#687169]">
                                                    {isSelected ? 'Ocultar JSON' : 'Inspeccionar'}
                                                </span>
                                            </button>

                                            {isSelected && (
                                                <div className="p-3 bg-[#18221d] text-white text-[10px] font-mono border-t border-[#ccd1ca] overflow-x-auto space-y-2">
                                                    <div>
                                                        <span className="text-[#d7f45b] block font-bold mb-0.5">
                                                            // Parámetros Sanitizados (Sin Credenciales):
                                                        </span>
                                                        <pre>{JSON.stringify(t.parameters_redacted, null, 2)}</pre>
                                                    </div>
                                                    <div>
                                                        <span className="text-[#d7f45b] block font-bold mb-0.5">
                                                            // Resultado Técnico:
                                                        </span>
                                                        <pre>{JSON.stringify(t.result_summary, null, 2)}</pre>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function AssistantIndex({ conversations, active_conversation, active_model }: Props) {
    const [input, setInput] = useState<string>('');
    const [sending, setSending] = useState<boolean>(false);
    const [messages, setMessages] = useState<Message[]>(active_conversation?.messages || []);
    const [showAiTranscript, setShowAiTranscript] = useState<boolean>(false);
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [isDeleting, setIsDeleting] = useState<string | null>(null);
    const scrollRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setMessages(active_conversation?.messages || []);
    }, [active_conversation]);

    useEffect(() => {
        scrollRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const filteredConversations = useMemo(() => {
        if (!searchQuery.trim()) return conversations;
        const q = searchQuery.toLowerCase();
        return conversations.filter(
            (c) => c.title.toLowerCase().includes(q) || new Date(c.updated_at).toLocaleDateString().includes(q)
        );
    }, [conversations, searchQuery]);

    const handleNewConversation = async () => {
        try {
            const res = await fetch('/assistant/conversations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ title: 'Nueva Consulta VOC' }),
            });
            const contentType = res.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const data = await res.json();
                if (data.success) {
                    router.visit(`/assistant?conversation_id=${data.conversation.id}`);
                }
            }
        } catch (err) {
            console.error('Error creating conversation:', err);
        }
    };

    const handleDeleteConversation = async (convId: string, e: React.MouseEvent) => {
        e.stopPropagation();
        if (!confirm('¿Estás seguro de eliminar esta conversación?')) return;

        setIsDeleting(convId);
        try {
            const res = await fetch(`/assistant/conversations/${convId}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });

            if (res.ok) {
                if (active_conversation?.id === convId) {
                    router.visit('/assistant');
                } else {
                    router.reload({ only: ['conversations'] });
                }
            }
        } catch (err) {
            console.error('Error deleting conversation:', err);
        } finally {
            setIsDeleting(null);
        }
    };

    const handleSendMessage = async (textToSend?: string) => {
        const messageText = textToSend || input;
        if (!messageText.trim() || !active_conversation || sending) return;

        setSending(true);
        setInput('');

        // Optimistic UI append
        const tempMsg: Message = {
            id: 'temp_' + Date.now(),
            sender_type: 'user',
            display_content: messageText,
            ai_content: messageText,
            created_at: new Date().toISOString(),
        };
        setMessages((prev) => [...prev, tempMsg]);

        try {
            const res = await fetch(`/assistant/conversations/${active_conversation.id}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ content: messageText }),
            });

            const contentType = res.headers.get('content-type') || '';
            const isJson = contentType.includes('application/json');
            const data = isJson ? await res.json() : null;

            if (!res.ok) {
                const errorMsg = data?.message || data?.error || `Error del servidor (${res.status})`;
                throw new Error(errorMsg);
            }

            if (!data?.user_message || !data?.assistant_message) {
                throw new Error('Respuesta del servidor incompleta.');
            }

            setMessages((prev) => [
                ...prev.filter((m) => m.id !== tempMsg.id),
                data.user_message,
                data.assistant_message,
            ]);
        } catch (err: any) {
            console.error('Chat error:', err);
            setMessages((prev) => [
                ...prev.filter((m) => m.id !== tempMsg.id),
                tempMsg,
                {
                    id: 'err_' + Date.now(),
                    sender_type: 'assistant',
                    display_content: `⚠️ ${err.message || 'No se pudo obtener respuesta del asistente. Por favor intenta de nuevo.'}`,
                    ai_content: `[ERROR] ${err.message}`,
                    created_at: new Date().toISOString(),
                },
            ]);
        } finally {
            setSending(false);
        }
    };

    const currentModelName = active_model?.provider_name || 'Atlas Engine';
    const currentModelDetail = active_model?.model || 'gemini-flash-latest';

    return (
        <AppLayout
            title="AI Analytical Assistant"
            kicker="ATLAS GOVERNED REACT RUNTIME"
            description="Asistente multi-turno gobernado de solo lectura con evidencia auditada (Grounding Citations), privacidad estricta y Query DSL determinista."
            actions={
                <div className="flex items-center gap-2">
                    <button
                        onClick={() => setShowAiTranscript(!showAiTranscript)}
                        className={`px-3 py-2 text-xs font-semibold uppercase tracking-wider border flex items-center gap-1.5 transition-colors ${
                            showAiTranscript
                                ? 'bg-[#18221d] text-white border-[#18221d]'
                                : 'bg-white border-[#ccd1ca] text-[#18221d] hover:bg-[#f7f6f1]'
                        }`}
                        title="Alternar entre transcripción para usuario y transcripción técnica pseudonimizada"
                    >
                        {showAiTranscript ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                        <span>{showAiTranscript ? 'Display Transcript' : 'AI Transcript (Pseudónimos)'}</span>
                    </button>
                </div>
            }
        >
            <Head title="Assistant — ATLAS VOC Analysis" />

            <div className="grid grid-cols-12 gap-6 h-[calc(100vh-270px)] min-h-[580px] max-h-[850px]">
                {/* Left Sidebar: Conversations List */}
                <div className="col-span-12 md:col-span-4 border border-[#ccd1ca] bg-white flex flex-col h-full overflow-hidden">
                    <div className="p-3.5 border-b border-[#ccd1ca] flex items-center justify-between flex-shrink-0 bg-[#f7f6f1]/60">
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-bold uppercase tracking-wider text-[#18221d]">Consultas</span>
                            <span className="text-[10px] font-mono bg-white border border-[#ccd1ca] px-1.5 py-0.2 rounded text-[#687169]">
                                {conversations.length}
                            </span>
                        </div>
                        <button
                            onClick={handleNewConversation}
                            className="px-2.5 py-1.5 bg-[#18221d] hover:bg-black text-white text-xs font-semibold uppercase tracking-wider transition-colors flex items-center gap-1 shadow-sm"
                            title="Nueva consulta analítica"
                        >
                            <Plus className="w-3.5 h-3.5" />
                            <span>Nueva</span>
                        </button>
                    </div>

                    {/* Search box */}
                    <div className="p-2.5 border-b border-[#ccd1ca] bg-white flex items-center gap-2 flex-shrink-0">
                        <Search className="w-3.5 h-3.5 text-[#687169]" />
                        <input
                            type="text"
                            placeholder="Buscar en el historial..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full text-xs text-[#18221d] placeholder:text-[#687169] focus:outline-none bg-transparent"
                        />
                    </div>

                    <div className="flex-1 min-h-0 overflow-y-auto divide-y divide-[#ccd1ca]/60">
                        {filteredConversations.length === 0 ? (
                            <div className="p-6 text-center text-xs text-[#687169]">
                                {searchQuery ? 'No se encontraron consultas coincidentes.' : 'No hay conversaciones aún.'}
                            </div>
                        ) : (
                            filteredConversations.map((c) => {
                                const isActive = active_conversation?.id === c.id;
                                return (
                                    <div
                                        key={c.id}
                                        onClick={() => router.visit(`/assistant?conversation_id=${c.id}`)}
                                        className={`w-full text-left p-3.5 hover:bg-[#f7f6f1] transition-colors flex items-start justify-between group cursor-pointer ${
                                            isActive ? 'bg-[#f7f6f1] border-l-4 border-l-[#18221d]' : ''
                                        }`}
                                    >
                                        <div className="flex items-start space-x-2.5 min-w-0 flex-1 pr-2">
                                            <MessageSquare className="w-3.5 h-3.5 text-[#687169] mt-0.5 flex-shrink-0" />
                                            <div className="truncate flex-1">
                                                <div className="text-xs font-semibold text-[#18221d] truncate">
                                                    {c.title}
                                                </div>
                                                <div className="text-[10px] text-[#687169] mt-0.5">
                                                    {new Date(c.updated_at).toLocaleDateString()} · {c.messages_count} msgs
                                                </div>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            onClick={(e) => handleDeleteConversation(c.id, e)}
                                            disabled={isDeleting === c.id}
                                            className="opacity-0 group-hover:opacity-100 p-1 hover:bg-red-50 text-[#687169] hover:text-red-700 transition-opacity"
                                            title="Eliminar conversación"
                                        >
                                            <Trash2 className="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                );
                            })
                        )}
                    </div>

                    {/* Sidebar Footer: Privacy & Operational Guarantee */}
                    <div className="p-3 border-t border-[#ccd1ca] bg-[#f7f6f1] text-[11px] text-[#687169] flex items-center justify-between flex-shrink-0">
                        <div className="flex items-center space-x-1.5">
                            <ShieldCheck className="w-4 h-4 text-[#2e5e33]" />
                            <span className="font-medium text-[#18221d]">Solo Lectura Gobernado</span>
                        </div>
                        <span className="text-[9px] font-mono uppercase bg-white border border-[#ccd1ca] px-1.5 py-0.2">
                            25 Turnos ReAct
                        </span>
                    </div>
                </div>

                {/* Right Area: Chat Header, Transcript & Composer */}
                <div className="col-span-12 md:col-span-8 border border-[#ccd1ca] bg-white flex flex-col h-full overflow-hidden">
                    {/* Chat Header: Active Model Badge */}
                    <div className="px-6 py-3 border-b border-[#ccd1ca] bg-[#f7f6f1]/80 flex items-center justify-between flex-shrink-0">
                        <div className="flex items-center gap-2">
                            <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                            <span className="text-xs font-semibold text-[#18221d] flex items-center gap-1.5">
                                <Cpu className="w-3.5 h-3.5 text-[#18221d]" />
                                {currentModelName}
                            </span>
                            <span className="text-[10px] font-mono bg-white border border-[#ccd1ca] px-1.5 py-0.5 text-[#687169]">
                                {currentModelDetail}
                            </span>
                        </div>

                        <div className="flex items-center gap-2 text-[10px] font-mono text-[#687169]">
                            <span className="hidden sm:inline">Pseudonimización Activa</span>
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-600" />
                        </div>
                    </div>

                    {/* Transcript Area */}
                    <div className="flex-1 min-h-0 p-6 overflow-y-auto space-y-6">
                        {messages.length === 0 ? (
                            <div className="h-full flex flex-col items-center justify-center text-center max-w-md mx-auto py-6">
                                <div className="w-12 h-12 rounded-full bg-[#18221d] flex items-center justify-center mb-3 text-white shadow-sm">
                                    <Sparkles className="w-6 h-6 text-[#d7f45b]" />
                                </div>
                                <h3 className="font-serif text-2xl text-[#18221d] mb-1">
                                    Atlas VOC Assistant
                                </h3>
                                <p className="text-xs text-[#687169] mb-6 leading-relaxed">
                                    Asistente de Inteligencia Operacional gobernado. Cada respuesta está respaldada por cálculos y evidencia técnica auditada.
                                </p>

                                <div className="space-y-2 w-full text-left">
                                    <span className="text-[11px] font-bold uppercase tracking-wider text-[#687169] block mb-1">
                                        Consultas recomendadas:
                                    </span>
                                    {SUGGESTED_PROMPTS.map((prompt, i) => (
                                        <button
                                            key={i}
                                            onClick={() => handleSendMessage(prompt)}
                                            className="w-full text-left text-xs p-3 border border-[#ccd1ca] bg-white hover:border-[#18221d] hover:bg-[#f7f6f1] transition-all flex items-center justify-between group shadow-sm"
                                        >
                                            <span className="text-[#18221d] font-medium">{prompt}</span>
                                            <span className="text-[#687169] group-hover:translate-x-1 transition-transform font-mono">→</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            messages.map((msg, idx) => {
                                const isUser = msg.sender_type === 'user';
                                const contentToShow = showAiTranscript ? msg.ai_content : msg.display_content;
                                const citations = msg.grounding_context || msg.metadata?.grounding_context || [];
                                const toolCalls = msg.tool_calls || msg.metadata?.tool_executions || [];
                                const tokensUsed = msg.tokens_used || msg.metadata?.tokens_used;
                                const latencyMs = msg.metadata?.latency_ms;

                                return (
                                    <div
                                        key={idx}
                                        className={`flex flex-col ${isUser ? 'items-end' : 'items-start'}`}
                                    >
                                        <div className="flex items-center space-x-2 mb-1.5 text-[11px] text-[#687169]">
                                            {!isUser && (
                                                <div className="w-4 h-4 rounded-full bg-[#18221d] text-white flex items-center justify-center text-[9px] font-serif font-bold">
                                                    A
                                                </div>
                                            )}
                                            <span className="font-bold text-[#18221d]">
                                                {isUser ? 'Usted' : 'Atlas Assistant'}
                                            </span>
                                            {showAiTranscript && (
                                                <span className="bg-[#18221d] text-white px-1.5 py-0.2 rounded text-[9px] font-mono">
                                                    PSEUDONIMIZADO
                                                </span>
                                            )}
                                            <span className="text-[10px] text-[#687169]">
                                                {new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                            </span>
                                        </div>

                                        <div
                                            className={`p-4 text-xs leading-relaxed border rounded-none ${
                                                isUser
                                                    ? 'bg-[#f7f6f1] border-[#ccd1ca] text-[#18221d] max-w-[85%] whitespace-pre-wrap font-sans'
                                                    : 'bg-white border-[#ccd1ca] text-[#18221d] shadow-sm max-w-[95%] md:max-w-[92%]'
                                            }`}
                                        >
                                            {isUser ? (
                                                contentToShow
                                            ) : (
                                                <>
                                                    <MarkdownRenderer content={contentToShow} />

                                                    {/* Grounding & Technical Evidence Drawer */}
                                                    <GroundingEvidenceDrawer
                                                        citations={citations}
                                                        toolExecutions={toolCalls}
                                                    />

                                                    {(tokensUsed || latencyMs) && (
                                                        <div className="mt-3 pt-2 border-t border-[#ccd1ca]/60 text-[10px] font-mono text-[#687169] flex items-center justify-between">
                                                            <span>Tokens consumidos: {tokensUsed || 0}</span>
                                                            {latencyMs && <span>Latencia ReAct: {latencyMs}ms</span>}
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </div>
                                );
                            })
                        )}
                        <div ref={scrollRef} />
                    </div>

                    {/* Composer Input Bar */}
                    <div className="p-4 border-t border-[#ccd1ca] bg-[#f7f6f1] flex-shrink-0">
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
                                placeholder={
                                    active_conversation
                                        ? 'Formula una consulta analítica sobre métricas, supervisores, cohortes o tendencias...'
                                        : 'Selecciona o crea una consulta en el panel lateral para comenzar'
                                }
                                disabled={sending || !active_conversation}
                                className="flex-1 px-4 py-3 bg-white border border-[#ccd1ca] text-xs text-[#18221d] focus:outline-none focus:border-[#18221d] placeholder:text-[#687169]"
                            />
                            <button
                                type="submit"
                                disabled={sending || !input.trim() || !active_conversation}
                                className="px-5 py-3 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black transition-colors disabled:opacity-50 flex items-center gap-2 shadow-sm"
                            >
                                {sending ? (
                                    <>
                                        <div className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                                        <span>Razonando...</span>
                                    </>
                                ) : (
                                    <>
                                        <Send className="w-3.5 h-3.5" />
                                        <span>Enviar</span>
                                    </>
                                )}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
