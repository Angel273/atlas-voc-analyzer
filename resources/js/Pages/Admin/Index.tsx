import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { UserPlus, Shield, Key, Check } from 'lucide-react';

interface Role {
    id: number;
    name: string;
    slug: string;
    description: string;
    permissions: { id: number; name: string; slug: string }[];
}

interface User {
    id: number;
    name: string;
    email: string;
    roles: Role[];
}

interface Props {
    users: User[];
    roles: Role[];
    permissions: { id: number; name: string; slug: string; description: string }[];
}

export default function AdminIndex({ users, roles, permissions }: Props) {
    const [showCreateUser, setShowCreateUser] = useState<boolean>(false);
    const [name, setName] = useState<string>('');
    const [email, setEmail] = useState<string>('');
    const [password, setPassword] = useState<string>('');
    const [selectedRoleIds, setSelectedRoleIds] = useState<number[]>([]);
    const [saving, setSaving] = useState<boolean>(false);

    const handleCreateUser = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        try {
            const res = await fetch('/admin/users', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    name,
                    email,
                    password,
                    role_ids: selectedRoleIds,
                }),
            });

            if (!res.ok) {
                const data = await res.json();
                throw new Error(data.message || 'Error creating user');
            }

            setShowCreateUser(false);
            setName('');
            setEmail('');
            setPassword('');
            setSelectedRoleIds([]);
            router.reload({ only: ['users'] });
        } catch (err: any) {
            alert(err.message);
        } finally {
            setSaving(false);
        }
    };

    const handleToggleUserRole = async (user: User, roleId: number) => {
        const currentRoleIds = user.roles.map((r) => r.id);
        const newRoleIds = currentRoleIds.includes(roleId)
            ? currentRoleIds.filter((id) => id !== roleId)
            : [...currentRoleIds, roleId];

        try {
            await fetch(`/admin/users/${user.id}/roles`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ role_ids: newRoleIds }),
            });
            router.reload({ only: ['users'] });
        } catch (err) {
            console.error(err);
        }
    };

    return (
        <AppLayout
            title="System & Access Control"
            kicker="ROLE-BASED ACCESS CONTROL (RBAC)"
            description="Manage users, configurable roles, and granular authorization policies enforced strictly server-side across all analytical endpoints."
            actions={
                <button
                    onClick={() => setShowCreateUser(true)}
                    className="px-4 py-2.5 bg-[#d7f45b] text-[#18221d] border border-[#18221d] font-semibold text-xs uppercase tracking-wider flex items-center gap-1.5 hover:bg-[#cbf03f] transition-colors"
                >
                    <UserPlus className="w-4 h-4" />
                    <span>Crear Usuario</span>
                </button>
            }
        >
            <Head title="Administration — ATLAS VOC Analysis" />

            {/* Users List Table */}
            <div className="border border-[#ccd1ca] bg-white p-6 mb-10">
                <h3 className="font-serif text-2xl text-[#18221d] mb-4">
                    Usuarios del Sistema ({users.length})
                </h3>
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr className="bg-[#f7f6f1] border-b border-[#ccd1ca]">
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Nombre</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Email</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Roles Asignados</th>
                                <th className="py-3 px-4 font-semibold text-[#18221d]">Permisos Efectivos</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-[#ccd1ca]/60">
                            {users.map((u) => (
                                <tr key={u.id} className="hover:bg-[#f7f6f1]/40">
                                    <td className="py-3 px-4 font-medium text-[#18221d]">{u.name}</td>
                                    <td className="py-3 px-4 font-mono text-[#687169]">{u.email}</td>
                                    <td className="py-3 px-4">
                                        <div className="flex flex-wrap gap-1.5">
                                            {roles.map((r) => {
                                                const hasRole = u.roles.some((ur) => ur.id === r.id);
                                                return (
                                                    <button
                                                        key={r.id}
                                                        onClick={() => handleToggleUserRole(u, r.id)}
                                                        className={`px-2 py-0.5 text-[10px] font-semibold border transition-colors ${
                                                            hasRole
                                                                ? 'bg-[#18221d] text-white border-[#18221d]'
                                                                : 'bg-[#f7f6f1] text-[#687169] border-[#ccd1ca] hover:border-[#18221d]'
                                                        }`}
                                                    >
                                                        {r.name}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </td>
                                    <td className="py-3 px-4 text-[#687169] font-mono text-[11px]">
                                        {u.roles.some((r) => r.slug === 'administrator')
                                            ? 'ALL_PERMISSIONS (Superadmin)'
                                            : `${u.roles.flatMap((r) => r.permissions).length} permisos activos`}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Roles and Permissions Matrix */}
            <div className="border border-[#ccd1ca] bg-white p-6">
                <h3 className="font-serif text-2xl text-[#18221d] mb-4">
                    Catálogo de Roles y Permisos Granulares
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {roles.map((r) => (
                        <div key={r.id} className="border border-[#ccd1ca] p-5 bg-[#f7f6f1]/40">
                            <div className="flex items-center space-x-2 mb-2">
                                <Shield className="w-4 h-4 text-[#18221d]" />
                                <h4 className="font-serif text-xl text-[#18221d]">{r.name}</h4>
                            </div>
                            <p className="text-xs text-[#687169] mb-4">{r.description}</p>
                            <div className="space-y-1.5">
                                <span className="text-[10px] font-bold uppercase tracking-wider text-[#687169] block mb-2">
                                    Permisos asignados:
                                </span>
                                {r.permissions.map((p) => (
                                    <div key={p.id} className="text-xs flex items-center space-x-2 font-mono text-[#18221d]">
                                        <Check className="w-3 h-3 text-[#2e5e33]" />
                                        <span>{p.slug}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Create User Modal */}
            {showCreateUser && (
                <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                    <div className="bg-white border border-[#ccd1ca] max-w-md w-full p-6">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="font-serif text-2xl text-[#18221d]">Crear Nuevo Usuario</h3>
                            <button onClick={() => setShowCreateUser(false)} className="text-[#687169] hover:text-[#18221d]">✕</button>
                        </div>

                        <form onSubmit={handleCreateUser} className="space-y-4">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">Nombre Completo</label>
                                <input
                                    type="text"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    required
                                    className="w-full p-2 bg-white border border-[#ccd1ca] text-xs text-[#18221d]"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">Email</label>
                                <input
                                    type="email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    required
                                    className="w-full p-2 bg-white border border-[#ccd1ca] text-xs text-[#18221d]"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">Password</label>
                                <input
                                    type="password"
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    required
                                    minLength={8}
                                    className="w-full p-2 bg-white border border-[#ccd1ca] text-xs text-[#18221d]"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">Asignar Roles</label>
                                <div className="space-y-2 pt-1">
                                    {roles.map((r) => (
                                        <label key={r.id} className="flex items-center space-x-2 text-xs cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={selectedRoleIds.includes(r.id)}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        setSelectedRoleIds((prev) => [...prev, r.id]);
                                                    } else {
                                                        setSelectedRoleIds((prev) => prev.filter((id) => id !== r.id));
                                                    }
                                                }}
                                                className="accent-[#18221d]"
                                            />
                                            <span className="font-semibold text-[#18221d]">{r.name}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div className="flex justify-end space-x-2 pt-4 border-t border-[#ccd1ca]">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateUser(false)}
                                    className="px-4 py-2 border border-[#ccd1ca] text-xs font-semibold uppercase text-[#687169]"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving}
                                    className="px-6 py-2 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black"
                                >
                                    {saving ? 'Creando...' : 'Crear Usuario'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
