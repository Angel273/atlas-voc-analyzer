import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ShieldCheck, ShieldAlert, ChevronDown, ChevronRight, Hash, Clock, CheckCircle2, AlertOctagon } from 'lucide-react';

interface AuditEvent {
    id: number;
    event_type: string;
    auditable_type?: string;
    auditable_id?: string;
    payload: any;
    metadata?: any;
    previous_hash?: string;
    event_hash: string;
    created_at: string;
    user?: { name: string; email: string };
}

interface Props {
    events: { data: AuditEvent[]; links: any[] };
    event_types: string[];
    users: { id: number; name: string; email: string }[];
    integrity: { valid: boolean; total_events: number; tampered_count: number; tampered_events: any[] };
    filters: { event_type?: string; user_id?: string };
}

export default function AuditIndex({ events, event_types, users, integrity, filters }: Props) {
    const [expandedIds, setExpandedIds] = useState<Record<number, boolean>>({});
    const [verifying, setVerifying] = useState<boolean>(false);
    const [chainStatus, setChainStatus] = useState<any>(integrity);

    const toggleExpand = (id: number) => {
        setExpandedIds((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const handleVerifyChain = async () => {
        setVerifying(true);
        try {
            const res = await fetch('/audit/verify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });
            const data = await res.json();
            setChainStatus(data);
        } catch (err) {
            console.error(err);
        } finally {
            setVerifying(false);
        }
    };

    return (
        <AppLayout
            title="Tamper-Evident Audit Ledger"
            kicker="CRYPTOGRAPHIC TRACE & AI OBSERVABILITY"
            description="Complete observable execution trace of every user action, privacy transformation, sanitized AI request, tool invocation, and tamper-evident SHA-256 hash chains."
        >
            <Head title="Audit Ledger — ATLAS VOC Analysis" />

            {/* Cryptographic Verification Status Banner */}
            <div className={`border p-6 mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 ${
                chainStatus.valid
                    ? 'border-[#aac69c] bg-[#eef7e8]'
                    : 'border-[#e1a89e] bg-[#fff0ed]'
            }`}>
                <div className="flex items-center space-x-3">
                    {chainStatus.valid ? (
                        <div className="w-10 h-10 rounded-full bg-[#2e5e33] text-white flex items-center justify-center flex-shrink-0">
                            <ShieldCheck className="w-6 h-6" />
                        </div>
                    ) : (
                        <div className="w-10 h-10 rounded-full bg-[#943126] text-white flex items-center justify-center flex-shrink-0">
                            <ShieldAlert className="w-6 h-6" />
                        </div>
                    )}
                    <div>
                        <h4 className="text-sm font-bold text-[#18221d]">
                            {chainStatus.valid
                                ? 'Cadena Criptográfica Íntegra y Sellada (Tamper-Evident)'
                                : 'ALERTA: Se detectaron eventos alterados en la cadena de auditoría'}
                        </h4>
                        <p className="text-xs text-[#687169] mt-0.5">
                            {chainStatus.total_events} eventos evaluados consecutivamente por SHA-256. {chainStatus.tampered_count} inconsistencias.
                        </p>
                    </div>
                </div>

                <button
                    onClick={handleVerifyChain}
                    disabled={verifying}
                    className="px-4 py-2 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black transition-colors"
                >
                    {verifying ? 'Recalculando hashes...' : 'Re-verificar cadena'}
                </button>
            </div>

            {/* Events Timeline List */}
            <div className="border border-[#ccd1ca] bg-white divide-y divide-[#ccd1ca]">
                <div className="p-4 bg-[#f7f6f1] flex items-center justify-between font-bold text-xs uppercase tracking-wider text-[#18221d]">
                    <span>Registro Cronológico Observable</span>
                    <span>{events.data.length} eventos en vista</span>
                </div>

                {events.data.length === 0 ? (
                    <div className="p-8 text-center text-xs text-[#687169]">
                        No hay eventos de auditoría registrados.
                    </div>
                ) : (
                    events.data.map((event) => {
                        const isExpanded = !!expandedIds[event.id];

                        return (
                            <div key={event.id} className="p-4 hover:bg-[#f7f6f1]/40 transition-colors">
                                <div
                                    onClick={() => toggleExpand(event.id)}
                                    className="flex items-center justify-between cursor-pointer select-none"
                                >
                                    <div className="flex items-center space-x-3">
                                        <button className="text-[#687169]">
                                            {isExpanded ? <ChevronDown className="w-4 h-4" /> : <ChevronRight className="w-4 h-4" />}
                                        </button>

                                        <div>
                                            <div className="flex items-center space-x-2">
                                                <span className="font-mono text-[11px] font-bold text-[#18221d] bg-[#f7f6f1] px-1.5 py-0.5 border border-[#ccd1ca]">
                                                    {event.event_type}
                                                </span>
                                                {event.user && (
                                                    <span className="text-xs text-[#687169]">
                                                        por {event.user.name} ({event.user.email})
                                                    </span>
                                                )}
                                            </div>
                                            <div className="font-mono text-[10px] text-[#687169] mt-1 flex items-center space-x-3">
                                                <span>Hash: {event.event_hash.substring(0, 16)}...</span>
                                                <span>Prev: {event.previous_hash ? `${event.previous_hash.substring(0, 12)}...` : 'ROOT'}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="text-xs text-[#687169] font-mono">
                                        {new Date(event.created_at).toLocaleString()}
                                    </div>
                                </div>

                                {isExpanded && (
                                    <div className="mt-4 pt-4 border-t border-[#ccd1ca]/60 space-y-3 pl-7">
                                        <div>
                                            <span className="text-[11px] font-bold uppercase tracking-wider text-[#687169] block mb-1">
                                                Exact Sanitized Payload (Representación Salida Atlas)
                                            </span>
                                            <pre className="p-3 bg-[#f7f6f1] border border-[#ccd1ca] text-[11px] font-mono overflow-x-auto text-[#18221d] max-h-60">
                                                {JSON.stringify(event.payload, null, 2)}
                                            </pre>
                                        </div>

                                        <div className="text-[11px] font-mono text-[#687169] grid grid-cols-1 md:grid-cols-2 gap-2">
                                            <div>
                                                <span className="font-semibold text-[#18221d]">Event Hash Completo:</span>
                                                <div className="break-all">{event.event_hash}</div>
                                            </div>
                                            <div>
                                                <span className="font-semibold text-[#18221d]">Previous Hash Completo:</span>
                                                <div className="break-all">{event.previous_hash || 'None (Initial Genesis)'}</div>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })
                )}
            </div>
        </AppLayout>
    );
}
