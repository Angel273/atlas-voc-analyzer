import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Plus, Tag, Check, X, Edit2, Trash2 } from 'lucide-react';

interface Category {
    id: number;
    name: string;
    description: string;
    examples: string;
    active: boolean;
    verbatim_analyses_count?: number;
}

interface Props {
    categories: Category[];
}

export default function CategoriesIndex({ categories }: Props) {
    const [showModal, setShowModal] = useState<boolean>(false);
    const [editingCategory, setEditingCategory] = useState<Category | null>(null);
    const [name, setName] = useState<string>('');
    const [description, setDescription] = useState<string>('');
    const [examples, setExamples] = useState<string>('');
    const [active, setActive] = useState<boolean>(true);
    const [saving, setSaving] = useState<boolean>(false);

    const openCreateModal = () => {
        setEditingCategory(null);
        setName('');
        setDescription('');
        setExamples('');
        setActive(true);
        setShowModal(true);
    };

    const openEditModal = (cat: Category) => {
        setEditingCategory(cat);
        setName(cat.name);
        setDescription(cat.description || '');
        setExamples(cat.examples || '');
        setActive(cat.active);
        setShowModal(true);
    };

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        const url = editingCategory ? `/categories/${editingCategory.id}` : '/categories';
        const method = editingCategory ? 'PUT' : 'POST';

        try {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ name, description, examples, active }),
            });

            if (!res.ok) {
                const data = await res.json();
                throw new Error(data.message || 'Failed to save category');
            }

            setShowModal(false);
            router.reload({ only: ['categories'] });
        } catch (err: any) {
            alert(err.message);
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async (cat: Category) => {
        if (!confirm(`¿Eliminar la categoría "${cat.name}"?`)) return;

        try {
            await fetch(`/categories/${cat.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });
            router.reload({ only: ['categories'] });
        } catch (err: any) {
            alert(err.message);
        }
    };

    return (
        <AppLayout
            title="Catálogo de Categorías Semánticas"
            kicker="TAXONOMÍA AUTORIZADA DE FEEDBACK"
            description="Catálogo estricto de categorías para clasificación de verbatims. La IA tiene prohibido inventar categorías y debe clasificar dentro de esta taxonomía."
            actions={
                <button
                    onClick={openCreateModal}
                    className="px-4 py-2.5 bg-[#d7f45b] text-[#18221d] border border-[#18221d] font-semibold text-xs uppercase tracking-wider flex items-center gap-1.5 hover:bg-[#cbf03f] transition-colors"
                >
                    <Plus className="w-4 h-4" />
                    <span>Nueva Categoría</span>
                </button>
            }
        >
            <Head title="Categories Catalog — ATLAS VOC Analysis" />

            <div className="border border-[#ccd1ca] bg-white divide-y divide-[#ccd1ca]">
                <div className="p-4 bg-[#f7f6f1] flex items-center justify-between font-bold text-xs uppercase tracking-wider text-[#18221d]">
                    <span>Categorías Activas ({categories.length})</span>
                    <span>Verbatims Clasificados</span>
                </div>

                {categories.map((cat) => (
                    <div key={cat.id} className="p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:bg-[#f7f6f1]/30 transition-colors">
                        <div className="max-w-2xl">
                            <div className="flex items-center space-x-3 mb-1.5">
                                <h4 className="text-sm font-bold text-[#18221d]">{cat.name}</h4>
                                <span className={`text-[10px] font-semibold px-2 py-0.5 border ${
                                    cat.active ? 'bg-[#eef7e8] border-[#aac69c] text-[#2e5e33]' : 'bg-[#fff0ed] border-[#e1a89e] text-[#943126]'
                                }`}>
                                    {cat.active ? 'ACTIVA' : 'INACTIVA'}
                                </span>
                            </div>
                            {cat.description && (
                                <p className="text-xs text-[#687169] mb-2">{cat.description}</p>
                            )}
                            {cat.examples && (
                                <div className="text-[11px] text-[#687169] font-mono bg-[#f7f6f1] p-2 border border-[#ccd1ca]/60">
                                    <span className="font-semibold text-[#18221d]">Ejemplos guía: </span>
                                    {cat.examples}
                                </div>
                            )}
                        </div>

                        <div className="flex items-center space-x-6">
                            <div className="text-right">
                                <span className="font-serif text-2xl text-[#18221d] block">
                                    {(cat.verbatim_analyses_count || 0).toLocaleString()}
                                </span>
                                <span className="text-[10px] text-[#687169] uppercase font-semibold">asociaciones</span>
                            </div>

                            <div className="flex items-center space-x-2 border-l border-[#ccd1ca] pl-4">
                                <button
                                    onClick={() => openEditModal(cat)}
                                    title="Editar"
                                    className="p-2 border border-[#ccd1ca] hover:bg-[#f7f6f1] text-[#18221d]"
                                >
                                    <Edit2 className="w-3.5 h-3.5" />
                                </button>
                                <button
                                    onClick={() => handleDelete(cat)}
                                    title="Eliminar"
                                    className="p-2 border border-[#ccd1ca] hover:bg-[#fff0ed] text-[#943126]"
                                >
                                    <Trash2 className="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Create/Edit Modal */}
            {showModal && (
                <div className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                    <div className="bg-white border border-[#ccd1ca] max-w-lg w-full p-6">
                        <div className="flex justify-between items-center mb-4">
                            <h3 className="font-serif text-2xl text-[#18221d]">
                                {editingCategory ? 'Editar Categoría' : 'Nueva Categoría Semántica'}
                            </h3>
                            <button onClick={() => setShowModal(false)} className="text-[#687169] hover:text-[#18221d]">✕</button>
                        </div>

                        <form onSubmit={handleSave} className="space-y-4">
                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Nombre de la Categoría
                                </label>
                                <input
                                    type="text"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="e.g. Tiempos de Espera"
                                    required
                                    className="w-full p-2.5 bg-white border border-[#ccd1ca] text-xs text-[#18221d]"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Descripción Funcional (Instrucción para el LLM)
                                </label>
                                <textarea
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                    rows={3}
                                    placeholder="Explicación precisa de qué comentarios corresponden a esta categoría..."
                                    className="w-full p-2.5 bg-white border border-[#ccd1ca] text-xs text-[#18221d]"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-bold uppercase tracking-wider text-[#18221d] mb-1">
                                    Ejemplos Representativos
                                </label>
                                <textarea
                                    value={examples}
                                    onChange={(e) => setExamples(e.target.value)}
                                    rows={2}
                                    placeholder="Frases típicas separadas por punto y coma..."
                                    className="w-full p-2.5 bg-white border border-[#ccd1ca] text-xs text-[#18221d]"
                                />
                            </div>

                            <label className="flex items-center space-x-2 cursor-pointer text-xs">
                                <input
                                    type="checkbox"
                                    checked={active}
                                    onChange={(e) => setActive(e.target.checked)}
                                    className="accent-[#18221d]"
                                />
                                <span className="text-[#18221d] font-medium">Categoría activa para clasificación</span>
                            </label>

                            <div className="flex justify-end space-x-2 pt-4 border-t border-[#ccd1ca]">
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="px-4 py-2 border border-[#ccd1ca] text-xs font-semibold uppercase text-[#687169]"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving}
                                    className="px-6 py-2 bg-[#18221d] text-white text-xs font-semibold uppercase tracking-wider hover:bg-black"
                                >
                                    {saving ? 'Guardando...' : 'Guardar Categoría'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
