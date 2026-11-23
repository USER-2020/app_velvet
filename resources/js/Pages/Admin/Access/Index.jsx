import { Head, Link, useForm } from '@inertiajs/react';
import { FiEdit2, FiKey, FiPlus, FiShield, FiTrash2, FiUsers } from 'react-icons/fi';
import { useState } from 'react';
import ConfirmationDialog from '@/Components/ConfirmationDialog';
import Layout from '../Recruitment/Layout';

export default function Index({ roles, permissionCount, canManageSuperAdmin = false }) {
    const destroy = useForm();
    const [pendingRole, setPendingRole] = useState(null);
    const remove = () => {
        if (!pendingRole) return;
        destroy.delete('/admin/access/' + pendingRole.id, { onFinish: () => setPendingRole(null) });
    };

    return <>
        <Head title="Roles y permisos" />
        <Layout>
            <div className="mx-auto max-w-[1320px]">
                <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                    <div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Configuración</p><h1 className="mt-2 font-editorial text-4xl text-white">Roles y permisos</h1><p className="mt-2 text-sm text-[#969baa]">Define qué puede consultar y gestionar cada perfil interno.</p></div>
                    <Link href="/admin/access/create" className="velvet-button gap-2"><FiPlus />Crear rol</Link>
                </div>
                <div className="mt-7 grid gap-4 sm:grid-cols-3">
                    <div className="velvet-dashboard-panel"><FiShield className="text-[#d56bea]" size={20} /><p className="mt-4 text-2xl text-white">{roles.length}</p><p className="mt-1 text-xs text-[#858a99]">Roles configurados</p></div>
                    <div className="velvet-dashboard-panel"><FiKey className="text-[#d56bea]" size={20} /><p className="mt-4 text-2xl text-white">{permissionCount}</p><p className="mt-1 text-xs text-[#858a99]">Permisos disponibles</p></div>
                    <div className="velvet-dashboard-panel"><FiUsers className="text-[#d56bea]" size={20} /><p className="mt-4 text-2xl text-white">Asignación por usuario</p><p className="mt-1 text-xs text-[#858a99]">Gestiona accesos individuales</p><Link href="/admin/users" className="mt-3 inline-block text-xs text-[#df8aef] hover:text-white">Ver usuarios →</Link></div>
                </div>
                <div className="mt-7 grid gap-4 lg:grid-cols-2">
                    {roles.map((role) => <article key={role.id} className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6 shadow-[0_18px_55px_rgba(0,0,0,.14)]">
                        <div className="flex items-start justify-between gap-4"><div><div className="flex items-center gap-2"><h2 className="font-editorial text-2xl text-white">{role.name}</h2>{role.is_system && <span className="rounded-full border border-[#663473] bg-[#2d1537] px-2 py-1 text-[9px] uppercase tracking-wider text-[#e0a0ed]">Sistema</span>}</div><p className="mt-2 text-xs text-[#858a99]">{role.description || 'Sin descripción'}</p></div><div className="flex gap-1">{(role.slug !== 'super_admin' || canManageSuperAdmin) && <Link href={'/admin/access/' + role.id + '/edit'} aria-label={'Editar ' + role.name} className="rounded-lg p-2 text-[#aeb2c0] hover:bg-[#2a1734] hover:text-white"><FiEdit2 size={15} /></Link>}{!role.is_system && <button type="button" onClick={() => setPendingRole(role)} aria-label={'Eliminar ' + role.name} className="rounded-lg p-2 text-[#aeb2c0] hover:bg-[#4a1e2d] hover:text-[#f29aaa]"><FiTrash2 size={15} /></button>}</div></div>
                        <div className="mt-6 flex items-center justify-between border-y border-[#292d39] py-4 text-xs"><span className="text-[#858a99]">{role.permissions.length} permisos asignados</span><span className="text-[#858a99]">{role.users_count} usuarios</span></div>
                        <div className="mt-5 flex flex-wrap gap-2">{role.permissions.slice(0, 7).map((permission) => <span key={permission.id} className="rounded-md border border-[#343040] bg-[#181522] px-2.5 py-1.5 text-[10px] text-[#c8b8cf]">{permission.name}</span>)}{role.permissions.length > 7 && <span className="px-2 py-1.5 text-[10px] text-[#8d91a0]">+{role.permissions.length - 7} más</span>}</div>
                    </article>)}
                </div>
            </div>
            <ConfirmationDialog open={Boolean(pendingRole)} onOpenChange={(open) => !open && setPendingRole(null)} title="Eliminar rol" description={pendingRole ? '¿Seguro que deseas eliminar el rol ' + pendingRole.name + '? Esta acción no se puede deshacer.' : ''} confirmLabel="Eliminar" onConfirm={remove} processing={destroy.processing} tone="danger" />
        </Layout>
    </>;
}
