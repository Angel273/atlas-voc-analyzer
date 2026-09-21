import React, { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { 
    Users, Plus, Edit2, Trash2, RefreshCw, Check, X, Shield, 
    UserCheck, UserPlus, FileSpreadsheet, ChevronRight, AlertCircle
} from 'lucide-react';

interface WorkforceMember {
    id: number;
    name: string;
    role: string;
    external_id?: string;
}

interface TeamMemberItem {
    id: number;
    name: string;
    role: string;
    external_id?: string;
    pivot?: {
        role: string;
        effective_from: string;
        effective_to?: string;
    };
}

interface TeamItem {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
    supervisor?: WorkforceMember;
    members?: TeamMemberItem[];
    surveys_count: number;
}

interface Props {
    teams?: TeamItem[];
    supervisors?: WorkforceMember[];
    agents?: WorkforceMember[];
}

export default function TeamsIndex(props: Props) {
    const teams = props.teams || [];
    const supervisors = props.supervisors || [];
    const agents = props.agents || [];

    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingTeam, setEditingTeam] = useState<TeamItem | null>(null);
    const [managingMembersTeam, setManagingMembersTeam] = useState<TeamItem | null>(null);
    const [showMemberModal, setShowMemberModal] = useState(false);
    const [syncing, setSyncing] = useState(false);

    // Form for Create/Edit Team
    const { data: teamData, setData: setTeamData, reset: resetTeam, processing: savingTeam, errors: teamErrors } = useForm({
        name: '',
        code: '',
        supervisor_id: '',
        is_active: true,
    });

    // Form for Add Member to Team
    const { data: memberData, setData: setMemberData, post: postMember, reset: resetMember, processing: addingMember } = useForm({
        workforce_member_id: '',
        effective_from: new Date().toISOString().split('T')[0],
    });

    // Form for Direct Workforce Member Creation (Supervisor/Agent)
    const { data: newMemberData, setData: setNewMemberData, post: postNewMember, reset: resetNewMember, processing: savingNewMember, errors: newMemberErrors } = useForm({
        name: '',
        role: 'supervisor',
        external_id: '',
    });

    const openCreateModal = () => {
        resetTeam();
        setEditingTeam(null);
        setTeamData({
            name: '',
            code: '',
            supervisor_id: '',
            is_active: true,
        });
        setShowCreateModal(true);
    };

    const openEditModal = (team: TeamItem) => {
        setEditingTeam(team);
        setTeamData({
            name: team.name,
            code: team.code,
            supervisor_id: team.supervisor?.id ? team.supervisor.id.toString() : '',
            is_active: team.is_active,
        });
        setShowCreateModal(true);
    };

    const handleSaveTeam = (e: React.FormEvent) => {
        e.preventDefault();
        const payload = {
            ...teamData,
            supervisor_id: teamData.supervisor_id ? parseInt(teamData.supervisor_id, 10) : null,
        };

        if (editingTeam) {
            router.put(`/teams/${editingTeam.id}`, payload, {
                onSuccess: () => {
                    setShowCreateModal(false);
                    resetTeam();
                },
            });
        } else {
            router.post('/teams', payload, {
                onSuccess: () => {
                    setShowCreateModal(false);
                    resetTeam();
                },
            });
        }
    };

    const handleSaveNewMember = (e: React.FormEvent) => {
        e.preventDefault();
        postNewMember('/teams/workforce-members', {
            onSuccess: () => {
                setShowMemberModal(false);
                resetNewMember();
            },
        });
    };

    const handleDeleteTeam = (team: TeamItem) => {
        if (confirm(`¿Está seguro de eliminar o desactivar el equipo "${team.name}"?`)) {
            router.delete(`/teams/${team.id}`);
        }
    };

    const handleAddMember = (e: React.FormEvent) => {
        e.preventDefault();
        if (!managingMembersTeam || !memberData.workforce_member_id) return;

        postMember(`/teams/${managingMembersTeam.id}/members`, {
            onSuccess: () => {
                resetMember();
                // Refresh local team members
                router.reload({ only: ['teams'] });
            },
        });
    };

    const handleRemoveMember = (memberId: number) => {
        if (!managingMembersTeam) return;
        if (confirm('¿Desvincular a este colaborador del equipo?')) {
            router.delete(`/teams/${managingMembersTeam.id}/members/${memberId}`, {
                onSuccess: () => {
                    router.reload({ only: ['teams'] });
                },
            });
        }
    };

    const handleSyncBackfill = () => {
        setSyncing(true);
        router.post('/teams/sync-backfill', {}, {
            onFinish: () => setSyncing(false),
        });
    };

    const activeTeamsCount = teams.filter((t) => t.is_active).length;
    const totalMembersAssigned = teams.reduce((acc, t) => acc + (t.members?.length || 0), 0);

    return (
        <AppLayout
            title="Definición y Gestión de Equipos"
            kicker="HERRAMIENTA 006 · ESTRUCTURA ORGANIZACIONAL"
            description="Administre la estructura de equipos, supervise asignaciones de colaboradores y configure equipos para los reportes de desempeño."
            actions={
                <div className="flex items-center gap-2">
                    <button
                        onClick={handleSyncBackfill}
                        disabled={syncing}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#ccd1ca] bg-white text-xs font-semibold text-[#18221d] rounded hover:bg-[#f2f1ea] transition-colors disabled:opacity-50"
                        title="Escanear encuestas y autocompletar supervisores y equipos faltantes"
                    >
                        <RefreshCw className={`w-3.5 h-3.5 ${syncing ? 'animate-spin' : ''}`} />
                        {syncing ? 'Sincronizando...' : 'Sincronizar desde Encuestas'}
                    </button>

                    <button
                        onClick={() => {
                            resetNewMember();
                            setShowMemberModal(true);
                        }}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 border border-[#ccd1ca] bg-white text-xs font-semibold text-[#18221d] rounded hover:bg-[#f2f1ea] transition-colors"
                        title="Registrar un supervisor o agente manualmente"
                    >
                        <UserPlus className="w-3.5 h-3.5" />
                        Nuevo Supervisor / Colaborador
                    </button>

                    <button
                        onClick={openCreateModal}
                        className="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider rounded shadow-sm hover:bg-[#283830] transition-colors"
                    >
                        <Plus className="w-3.5 h-3.5" />
                        Nuevo Equipo
                    </button>
                </div>
            }
        >
            <Head title="Definición y Gestión de Equipos" />

            <div className="space-y-6">
                {/* Stat Counters */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white border border-[#ccd1ca] p-4 rounded shadow-sm">
                        <div className="text-[#687169] text-xs font-bold uppercase tracking-wider mb-1">Total Equipos</div>
                        <div className="text-2xl font-serif font-bold text-[#18221d]">{teams.length}</div>
                        <p className="text-[11px] text-[#687169] mt-0.5">Definidos en el sistema</p>
                    </div>

                    <div className="bg-white border border-[#ccd1ca] p-4 rounded shadow-sm">
                        <div className="text-[#687169] text-xs font-bold uppercase tracking-wider mb-1">Equipos Activos</div>
                        <div className="text-2xl font-serif font-bold text-emerald-800">{activeTeamsCount}</div>
                        <p className="text-[11px] text-[#687169] mt-0.5">Disponibles para reportes</p>
                    </div>

                    <div className="bg-white border border-[#ccd1ca] p-4 rounded shadow-sm">
                        <div className="text-[#687169] text-xs font-bold uppercase tracking-wider mb-1">Supervisores</div>
                        <div className="text-2xl font-serif font-bold text-[#18221d]">{supervisors.length}</div>
                        <p className="text-[11px] text-[#687169] mt-0.5">Disponibles para liderazgo</p>
                    </div>

                    <div className="bg-white border border-[#ccd1ca] p-4 rounded shadow-sm">
                        <div className="text-[#687169] text-xs font-bold uppercase tracking-wider mb-1">Integrantes Asignados</div>
                        <div className="text-2xl font-serif font-bold text-[#18221d]">{totalMembersAssigned}</div>
                        <p className="text-[11px] text-[#687169] mt-0.5">Colaboradores en equipos</p>
                    </div>
                </div>

                {/* Teams Table */}
                <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-sm overflow-hidden">
                    <div className="p-4 bg-[#f7f6f1] border-b border-[#ccd1ca] flex items-center justify-between">
                        <h2 className="text-xs font-bold uppercase tracking-wider text-[#18221d]">
                            Catálogo de Equipos de Operaciones
                        </h2>
                        <Link
                            href="/reports/teams"
                            className="text-xs text-[#18221d] font-semibold hover:underline flex items-center gap-1"
                        >
                            Ir a Generar Reportes PDF <ChevronRight className="w-3.5 h-3.5" />
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr className="border-b border-[#ccd1ca] bg-[#f2f1ea] text-[#687169] uppercase font-semibold">
                                    <th className="py-3 px-4">Equipo / Nombre</th>
                                    <th className="py-3 px-4">Código Único</th>
                                    <th className="py-3 px-4">Supervisor Asignado</th>
                                    <th className="py-3 px-4 text-center">Integrantes</th>
                                    <th className="py-3 px-4 text-center">Encuestas</th>
                                    <th className="py-3 px-4 text-center">Estado</th>
                                    <th className="py-3 px-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[#ecebe4]">
                                {teams.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="py-12 text-center text-[#687169]">
                                            <p className="font-semibold text-sm">No hay equipos definidos todavía.</p>
                                            <p className="text-xs mt-1">Cree un equipo con el botón "Nuevo Equipo" o utilice "Sincronizar desde Encuestas".</p>
                                        </td>
                                    </tr>
                                ) : (
                                    teams.map((t) => (
                                        <tr key={t.id} className="hover:bg-[#fafaf8] transition-colors">
                                            <td className="py-3.5 px-4 font-bold text-[#18221d]">
                                                {t.name}
                                            </td>
                                            <td className="py-3.5 px-4 font-mono font-semibold text-[#687169]">
                                                <span className="bg-[#f2f1ea] px-2 py-0.5 rounded border border-[#ccd1ca]">
                                                    {t.code}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4">
                                                {t.supervisor ? (
                                                    <div className="flex items-center gap-1.5 font-medium text-[#18221d]">
                                                        <UserCheck className="w-3.5 h-3.5 text-emerald-700" />
                                                        {t.supervisor.name}
                                                    </div>
                                                ) : (
                                                    <span className="text-[#9aa099] italic">Sin supervisor asignado</span>
                                                )}
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                <button
                                                    onClick={() => setManagingMembersTeam(t)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-0.5 bg-[#fafaf8] border border-[#ccd1ca] rounded hover:bg-[#f2f1ea] text-[#18221d] font-semibold text-[11px]"
                                                >
                                                    <Users className="w-3 h-3 text-[#687169]" />
                                                    {t.members?.length || 0} integrantes
                                                </button>
                                            </td>
                                            <td className="py-3.5 px-4 text-center font-mono font-semibold text-[#18221d]">
                                                {t.surveys_count}
                                            </td>
                                            <td className="py-3.5 px-4 text-center">
                                                <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${
                                                    t.is_active ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-gray-100 text-gray-600'
                                                }`}>
                                                    {t.is_active ? 'Activo' : 'Inactivo'}
                                                </span>
                                            </td>
                                            <td className="py-3.5 px-4 text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        onClick={() => openEditModal(t)}
                                                        className="p-1 hover:bg-[#f2f1ea] rounded text-[#687169] hover:text-[#18221d]"
                                                        title="Editar Equipo"
                                                    >
                                                        <Edit2 className="w-3.5 h-3.5" />
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteTeam(t)}
                                                        className="p-1 hover:bg-red-50 rounded text-red-600 hover:text-red-800"
                                                        title="Eliminar o desactivar equipo"
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* MODAL: CREATE / EDIT TEAM */}
            {showCreateModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-lg max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-[#ccd1ca] pb-3">
                            <h3 className="font-serif font-bold text-[#18221d]">
                                {editingTeam ? 'Editar Equipo' : 'Definir Nuevo Equipo'}
                            </h3>
                            <button onClick={() => setShowCreateModal(false)} className="text-[#687169] hover:text-[#18221d]">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveTeam} className="space-y-4">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Nombre del Equipo <span className="text-red-600">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={teamData.name}
                                    onChange={(e) => setTeamData('name', e.target.value)}
                                    placeholder="Ej. Equipo Diana Prince"
                                    className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm focus:outline-none focus:border-[#18221d]"
                                    required
                                />
                                {teamErrors.name && <p className="text-red-600 text-xs mt-1">{teamErrors.name}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Código Único del Equipo <span className="text-red-600">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={teamData.code}
                                    onChange={(e) => setTeamData('code', e.target.value.toUpperCase())}
                                    placeholder="Ej. TEAM-DP-01"
                                    className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm font-mono focus:outline-none focus:border-[#18221d]"
                                    required
                                />
                                {teamErrors.code && <p className="text-red-600 text-xs mt-1">{teamErrors.code}</p>}
                            </div>

                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d]">
                                        Supervisor a Cargo
                                    </label>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setShowCreateModal(false);
                                            resetNewMember();
                                            setNewMemberData('role', 'supervisor');
                                            setShowMemberModal(true);
                                        }}
                                        className="text-[11px] text-emerald-800 hover:underline font-semibold flex items-center gap-1"
                                    >
                                        <Plus className="w-3 h-3" /> Registrar nuevo supervisor
                                    </button>
                                </div>
                                <select
                                    value={teamData.supervisor_id}
                                    onChange={(e) => setTeamData('supervisor_id', e.target.value)}
                                    className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm bg-white focus:outline-none focus:border-[#18221d]"
                                >
                                    <option value="">
                                        {supervisors.length === 0 ? 'Sin supervisores registrados aún...' : 'Seleccione un supervisor...'}
                                    </option>
                                    {supervisors.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name} {s.external_id ? `(${s.external_id})` : ''}
                                        </option>
                                    ))}
                                </select>
                                {supervisors.length === 0 && (
                                    <p className="text-[11px] text-amber-800 mt-1 bg-amber-50/80 p-2 rounded border border-amber-200 leading-normal">
                                        No hay supervisores en el catálogo. Puedes pulsar <strong>"Registrar nuevo supervisor"</strong> arriba o utilizar <strong>"Sincronizar desde Encuestas"</strong> en la pantalla principal.
                                    </p>
                                )}
                                {teamErrors.supervisor_id && <p className="text-red-600 text-xs mt-1">{teamErrors.supervisor_id}</p>}
                            </div>

                            <div className="pt-2">
                                <label className="flex items-center gap-2 text-xs text-[#18221d] cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={teamData.is_active}
                                        onChange={(e) => setTeamData('is_active', e.target.checked)}
                                        className="rounded border-[#ccd1ca] text-[#18221d]"
                                    />
                                    <span className="font-semibold">Equipo Activo (disponible para reportes y encuestas)</span>
                                </label>
                            </div>

                            <div className="pt-3 border-t border-[#ccd1ca] flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateModal(false)}
                                    className="px-3.5 py-1.5 border border-[#ccd1ca] rounded text-xs text-[#687169] hover:bg-[#f2f1ea]"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={savingTeam}
                                    className="px-4 py-1.5 bg-[#18221d] text-white rounded text-xs font-bold uppercase tracking-wider hover:bg-[#283830] transition-colors disabled:opacity-50"
                                >
                                    {savingTeam ? 'Guardando...' : (editingTeam ? 'Actualizar Equipo' : 'Crear Equipo')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* MODAL: MANAGE TEAM MEMBERS */}
            {managingMembersTeam && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-lg max-w-2xl w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-[#ccd1ca] pb-3">
                            <div>
                                <h3 className="font-serif font-bold text-[#18221d]">
                                    Integrantes de {managingMembersTeam.name}
                                </h3>
                                <p className="text-xs text-[#687169]">
                                    Supervisor: {managingMembersTeam.supervisor?.name || 'No asignado'} ({managingMembersTeam.code})
                                </p>
                            </div>
                            <button onClick={() => setManagingMembersTeam(null)} className="text-[#687169] hover:text-[#18221d]">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Add Member Inline Form */}
                        <form onSubmit={handleAddMember} className="p-3 bg-[#fafaf8] border border-[#ccd1ca] rounded space-y-3">
                            <label className="block text-[11px] font-bold uppercase tracking-wider text-[#18221d]">
                                Asignar Nuevo Agente al Equipo
                            </label>
                            <div className="flex flex-col sm:flex-row gap-2">
                                <select
                                    value={memberData.workforce_member_id}
                                    onChange={(e) => setMemberData('workforce_member_id', e.target.value)}
                                    className="flex-1 px-3 py-1.5 border border-[#ccd1ca] rounded text-xs bg-white"
                                    required
                                >
                                    <option value="">Seleccione un agente...</option>
                                    {agents.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name} {a.external_id ? `(BMS ${a.external_id})` : ''}
                                        </option>
                                    ))}
                                </select>

                                <input
                                    type="date"
                                    value={memberData.effective_from}
                                    onChange={(e) => setMemberData('effective_from', e.target.value)}
                                    className="px-3 py-1.5 border border-[#ccd1ca] rounded text-xs bg-white"
                                    required
                                />

                                <button
                                    type="submit"
                                    disabled={addingMember}
                                    className="inline-flex items-center justify-center gap-1 px-4 py-1.5 bg-[#18221d] text-white text-xs font-semibold rounded hover:bg-[#283830] transition-colors disabled:opacity-50"
                                >
                                    <UserPlus className="w-3.5 h-3.5" />
                                    {addingMember ? 'Asignando...' : 'Asignar'}
                                </button>
                            </div>
                        </form>

                        {/* Members List */}
                        <div className="max-h-64 overflow-y-auto border border-[#ccd1ca] rounded">
                            <table className="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr className="bg-[#f2f1ea] text-[#687169] uppercase font-semibold border-b border-[#ccd1ca]">
                                        <th className="py-2 px-3">Agente / Colaborador</th>
                                        <th className="py-2 px-3">BMS ID</th>
                                        <th className="py-2 px-3">Fecha de Ingreso</th>
                                        <th className="py-2 px-3 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[#ecebe4]">
                                    {(!managingMembersTeam.members || managingMembersTeam.members.length === 0) ? (
                                        <tr>
                                            <td colSpan={4} className="py-8 text-center text-[#687169]">
                                                No hay colaboradores asignados actualmente a este equipo.
                                            </td>
                                        </tr>
                                    ) : (
                                        managingMembersTeam.members.map((m) => (
                                            <tr key={m.id} className="hover:bg-[#fafaf8]">
                                                <td className="py-2.5 px-3 font-semibold text-[#18221d]">{m.name}</td>
                                                <td className="py-2.5 px-3 font-mono text-[11px] text-[#687169]">{m.external_id || '—'}</td>
                                                <td className="py-2.5 px-3 text-[#687169]">{m.pivot?.effective_from || '—'}</td>
                                                <td className="py-2.5 px-3 text-right">
                                                    <button
                                                        onClick={() => handleRemoveMember(m.id)}
                                                        className="text-red-600 hover:text-red-800 text-[11px] font-semibold hover:underline"
                                                    >
                                                        Desvincular
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="text-right pt-2">
                            <button
                                onClick={() => setManagingMembersTeam(null)}
                                className="px-4 py-1.5 bg-[#18221d] text-white rounded text-xs font-semibold"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* MODAL: DIRECT WORKFORCE MEMBER CREATION */}
            {showMemberModal && (
                <div className="fixed inset-0 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                    <div className="bg-white border border-[#ccd1ca] rounded-lg shadow-lg max-w-md w-full p-6 space-y-4">
                        <div className="flex items-center justify-between border-b border-[#ccd1ca] pb-3">
                            <h3 className="font-serif font-bold text-[#18221d] flex items-center gap-2">
                                <UserPlus className="w-4 h-4 text-[#18221d]" />
                                Registrar Colaborador / Supervisor
                            </h3>
                            <button onClick={() => setShowMemberModal(false)} className="text-[#687169] hover:text-[#18221d]">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveNewMember} className="space-y-4">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Nombre Completo <span className="text-red-600">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={newMemberData.name}
                                    onChange={(e) => setNewMemberData('name', e.target.value)}
                                    placeholder="Ej. Diana Prince"
                                    className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm focus:outline-none focus:border-[#18221d]"
                                    required
                                />
                                {newMemberErrors.name && <p className="text-red-600 text-xs mt-1">{newMemberErrors.name}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Rol en la Organización <span className="text-red-600">*</span>
                                </label>
                                <select
                                    value={newMemberData.role}
                                    onChange={(e) => setNewMemberData('role', e.target.value)}
                                    className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm bg-white focus:outline-none focus:border-[#18221d]"
                                >
                                    <option value="supervisor">Supervisor (Liderazgo de equipo)</option>
                                    <option value="agent">Agente (Atención de llamadas/encuestas)</option>
                                    <option value="both">Ambos (Supervisor y Agente activo)</option>
                                </select>
                                {newMemberErrors.role && <p className="text-red-600 text-xs mt-1">{newMemberErrors.role}</p>}
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Identificador Externo / Código BMS (Opcional)
                                </label>
                                <input
                                    type="text"
                                    value={newMemberData.external_id}
                                    onChange={(e) => setNewMemberData('external_id', e.target.value)}
                                    placeholder="Ej. 6404592 o SUP-DP"
                                    className="w-full px-3 py-2 border border-[#ccd1ca] rounded text-sm font-mono focus:outline-none focus:border-[#18221d]"
                                />
                                {newMemberErrors.external_id && <p className="text-red-600 text-xs mt-1">{newMemberErrors.external_id}</p>}
                            </div>

                            <div className="pt-3 border-t border-[#ccd1ca] flex items-center justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setShowMemberModal(false)}
                                    className="px-3.5 py-1.5 border border-[#ccd1ca] rounded text-xs text-[#687169] hover:bg-[#f2f1ea]"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={savingNewMember}
                                    className="px-4 py-1.5 bg-[#18221d] text-white rounded text-xs font-bold uppercase tracking-wider hover:bg-[#283830] transition-colors disabled:opacity-50"
                                >
                                    {savingNewMember ? 'Guardando...' : 'Guardar Colaborador'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
