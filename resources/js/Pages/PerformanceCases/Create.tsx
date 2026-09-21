import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { ArrowLeft, Save, AlertCircle, User, Calendar, ShieldCheck, CheckCircle2 } from 'lucide-react';

interface WorkforceMember {
    id: number;
    name: string;
    role: string;
    external_id?: string;
    current_team?: {
        name: string;
    };
    supervisor?: {
        name: string;
    };
}

interface ResponsibleUser {
    id: number;
    name: string;
}

interface Props {
    members?: WorkforceMember[];
    responsibles?: ResponsibleUser[];
    users?: ResponsibleUser[];
}

export default function PerformanceCaseCreate(props: Props) {
    const members = props.members || [];
    const responsibles = props.responsibles || props.users || [];
    const today = new Date().toISOString().split('T')[0];

    const { data, setData, post, processing, errors } = useForm({
        workforce_member_id: '',
        type: 'performance',
        priority: 'medium',
        reason: '',
        assigned_to_user_id: '',
        opened_at: today,
        next_review_at: '',
        initial_notes: '',
    });

    const selectedMember = members.find((m) => m.id.toString() === data.workforce_member_id);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/performance-cases');
    };

    return (
        <AppLayout
            title="Apertura de Caso de Desempeño"
            kicker="HERRAMIENTA 004 · SEGUIMIENTO DE DESEMPEÑO"
            description="Inicie un nuevo expediente de acompañamiento operacional, calidad o conducta con captura automática de línea base."
            actions={
                <Link
                    href="/performance-cases"
                    className="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#ccd1ca] bg-white text-xs font-semibold text-[#18221d] rounded hover:bg-[#f2f1ea] transition-colors"
                >
                    <ArrowLeft className="w-3.5 h-3.5" />
                    Volver a Casos
                </Link>
            }
        >
            <Head title="Nuevo Caso de Desempeño" />

            <div className="max-w-3xl mx-auto bg-white border border-[#ccd1ca] rounded-lg shadow-sm p-6 md:p-8">
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Collaborator Selection */}
                    <div>
                        <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                            Colaborador / Agente a Intervenir <span className="text-red-600">*</span>
                        </label>
                        <select
                            value={data.workforce_member_id}
                            onChange={(e) => setData('workforce_member_id', e.target.value)}
                            className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm bg-white focus:outline-none focus:border-[#18221d]"
                            required
                        >
                            <option value="">Seleccione un agente o supervisor...</option>
                            {members.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name} ({m.role.toUpperCase()}) {m.external_id ? `· BMS ${m.external_id}` : ''} {m.current_team ? `· ${m.current_team.name}` : ''}
                                </option>
                            ))}
                        </select>
                        {errors.workforce_member_id && (
                            <p className="text-red-600 text-xs mt-1">{errors.workforce_member_id}</p>
                        )}

                        {selectedMember && (
                            <div className="mt-2.5 p-3 bg-[#f2f1ea] rounded border border-[#ccd1ca] flex items-center justify-between text-xs text-[#334139]">
                                <div>
                                    <span className="font-bold text-[#18221d]">{selectedMember.name}</span>
                                    <span className="ml-2 text-[10px] uppercase font-semibold px-1.5 py-0.5 bg-white rounded border border-[#ccd1ca]">
                                        {selectedMember.role}
                                    </span>
                                    {selectedMember.supervisor && (
                                        <span className="ml-2">Supervisor: {selectedMember.supervisor.name}</span>
                                    )}
                                </div>
                                <span className="text-[10px] text-emerald-800 font-semibold flex items-center gap-1">
                                    <ShieldCheck className="w-3.5 h-3.5" /> Línea base se calculará al guardar
                                </span>
                            </div>
                        )}
                    </div>

                    {/* Type and Priority */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                                Tipo de Caso <span className="text-red-600">*</span>
                            </label>
                            <select
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm bg-white focus:outline-none focus:border-[#18221d]"
                                required
                            >
                                <option value="performance">Desempeño Operacional (NPS / CSAT)</option>
                                <option value="quality">Calidad en Interacciones (Protocolo / Cortesía)</option>
                                <option value="conduct">Conducta y Adherencia Operativa</option>
                            </select>
                            {errors.type && <p className="text-red-600 text-xs mt-1">{errors.type}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                                Nivel de Prioridad <span className="text-red-600">*</span>
                            </label>
                            <select
                                value={data.priority}
                                onChange={(e) => setData('priority', e.target.value)}
                                className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm bg-white focus:outline-none focus:border-[#18221d]"
                                required
                            >
                                <option value="low">Baja (Monitoreo preventivo)</option>
                                <option value="medium">Media (Oportunidad moderada)</option>
                                <option value="high">Alta (Urgente / Crítico)</option>
                            </select>
                            {errors.priority && <p className="text-red-600 text-xs mt-1">{errors.priority}</p>}
                        </div>
                    </div>

                    {/* Reason */}
                    <div>
                        <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                            Motivo Principal de Apertura <span className="text-red-600">*</span>
                        </label>
                        <input
                            type="text"
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                            placeholder="Ej. Caída sostenida de CSAT por debajo del 70% durante las últimas dos semanas"
                            className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm focus:outline-none focus:border-[#18221d]"
                            required
                        />
                        {errors.reason && <p className="text-red-600 text-xs mt-1">{errors.reason}</p>}
                    </div>

                    {/* Dates & Assignment */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                                Fecha de Apertura <span className="text-red-600">*</span>
                            </label>
                            <input
                                type="date"
                                value={data.opened_at}
                                onChange={(e) => setData('opened_at', e.target.value)}
                                className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm focus:outline-none focus:border-[#18221d]"
                                required
                            />
                            {errors.opened_at && <p className="text-red-600 text-xs mt-1">{errors.opened_at}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                                Próxima Revisión
                            </label>
                            <input
                                type="date"
                                value={data.next_review_at}
                                onChange={(e) => setData('next_review_at', e.target.value)}
                                className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm focus:outline-none focus:border-[#18221d]"
                            />
                            {errors.next_review_at && <p className="text-red-600 text-xs mt-1">{errors.next_review_at}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                                Responsable Asignado
                            </label>
                            <select
                                value={data.assigned_to_user_id}
                                onChange={(e) => setData('assigned_to_user_id', e.target.value)}
                                className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm bg-white focus:outline-none focus:border-[#18221d]"
                            >
                                <option value="">Asignar a mí mismo / Sin asignar</option>
                                {responsibles.map((r) => (
                                    <option key={r.id} value={r.id}>{r.name}</option>
                                ))}
                            </select>
                            {errors.assigned_to_user_id && <p className="text-red-600 text-xs mt-1">{errors.assigned_to_user_id}</p>}
                        </div>
                    </div>

                    {/* Initial Notes */}
                    <div>
                        <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1.5">
                            Notas Iniciales y Plan de Acompañamiento
                        </label>
                        <textarea
                            rows={3}
                            value={data.initial_notes}
                            onChange={(e) => setData('initial_notes', e.target.value)}
                            placeholder="Detalle los objetivos del caso, acuerdos iniciales o contexto relevante para el seguimiento..."
                            className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm focus:outline-none focus:border-[#18221d]"
                        />
                        {errors.initial_notes && <p className="text-red-600 text-xs mt-1">{errors.initial_notes}</p>}
                    </div>

                    {/* Form Actions */}
                    <div className="pt-4 border-t border-[#ccd1ca] flex items-center justify-end gap-3">
                        <Link
                            href="/performance-cases"
                            className="px-4 py-2 border border-[#ccd1ca] text-xs font-bold uppercase tracking-wider rounded text-[#687169] hover:bg-[#f2f1ea] transition-colors"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center gap-2 px-5 py-2 bg-[#18221d] text-white text-xs font-bold uppercase tracking-wider rounded shadow-sm hover:bg-[#283830] transition-colors disabled:opacity-50"
                        >
                            <Save className="w-4 h-4" />
                            {processing ? 'Abriendo Caso...' : 'Abrir Caso y Fijar Línea Base'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
