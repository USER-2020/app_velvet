import { useForm } from '@inertiajs/react';
import { FiCheck, FiX } from 'react-icons/fi';

const inputClass = 'mt-2 w-full rounded-lg border border-[#353846] bg-[#0f1017] px-4 py-3 text-sm text-white outline-none transition placeholder:text-[#6f7382] focus:border-[#bd38df] focus:ring-1 focus:ring-[#bd38df]';

export default function UserFormModal({ user, roles, onClose }) {
    const editing = Boolean(user);
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        roles: user?.roles?.map((role) => role.slug) ?? [],
    });
    const toggle = (slug) => form.setData('roles', form.data.roles.includes(slug) ? form.data.roles.filter((role) => role !== slug) : [...form.data.roles, slug]);
    const submit = (event) => {
        event.preventDefault();
        const options = { onSuccess: onClose };
        editing ? form.put(`/admin/users/${user.id}`, options) : form.post('/admin/users', options);
    };

    return (
        <div className="fixed inset-0 z-[80] grid place-items-center bg-black/75 p-3 backdrop-blur-sm sm:p-6" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && !form.processing && onClose()}>
            <section role="dialog" aria-modal="true" aria-labelledby="user-modal-title" className="user-form-modal flex w-full max-w-6xl flex-col rounded-2xl border border-[#4c3157] bg-[#11121a] shadow-[0_25px_80px_rgba(0,0,0,.6)]">
                <header className="flex shrink-0 items-start justify-between gap-4 border-b border-[#292d39] px-6 py-5 sm:px-8 sm:py-6 lg:px-10">
                    <div>
                        <p className="text-[10px] uppercase tracking-[.25em] text-[#d56bea]">{editing ? 'Editar acceso' : 'Nuevo usuario'}</p>
                        <h2 id="user-modal-title" className="mt-2 font-editorial text-3xl text-white lg:text-4xl">{editing ? user.name : 'Crear usuario'}</h2>
                        <p className="mt-1 text-sm text-[#858a99]">{editing ? 'Actualiza los datos y roles permitidos.' : 'Crea una cuenta interna con acceso controlado.'}</p>
                    </div>
                    <button type="button" onClick={onClose} disabled={form.processing} aria-label="Cerrar" className="rounded-lg p-2 text-[#858a99] hover:bg-[#25152e] hover:text-white"><FiX size={20} /></button>
                </header>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    <div className="velvet-scrollbar min-h-0 flex-1 overflow-y-auto px-6 py-6 sm:px-8 lg:px-10">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <label className="text-sm text-[#c9c5d0]">Nombre<input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass} autoComplete="name" />{form.errors.name && <span className="mt-1 block text-xs text-[#f29aaa]">{form.errors.name}</span>}</label>
                            <label className="text-sm text-[#c9c5d0]">Correo electrónico<input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} className={inputClass} autoComplete="email" />{form.errors.email && <span className="mt-1 block text-xs text-[#f29aaa]">{form.errors.email}</span>}</label>
                            <label className="text-sm text-[#c9c5d0]">{editing ? 'Nueva contraseña (opcional)' : 'Contraseña'}<input type="password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} className={inputClass} autoComplete="new-password" />{form.errors.password && <span className="mt-1 block text-xs text-[#f29aaa]">{form.errors.password}</span>}</label>
                            <label className="text-sm text-[#c9c5d0]">Confirmar contraseña<input type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} className={inputClass} autoComplete="new-password" /></label>
                        </div>

                        <div className="mt-7 border-t border-[#292d39] pt-6">
                            <p className="text-sm font-medium text-white">Roles asignables</p>
                            <p className="mt-1 text-xs text-[#858a99]">Solo aparecen los roles permitidos para tu nivel de acceso.</p>
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                {roles.map((role) => {
                                    const checked = form.data.roles.includes(role.slug);
                                    return <label key={role.id} className={`flex min-h-[118px] cursor-pointer items-center justify-between rounded-lg border p-4 transition ${checked ? 'border-[#9f35b9] bg-[#32163e]' : 'border-[#292d39] bg-[#151722] hover:border-[#5b3267]'}`}><span><span className="block text-sm text-white">{role.name}</span><span className="mt-1 block text-xs text-[#858a99]">{role.description}</span></span><span className={`grid h-5 w-5 shrink-0 place-items-center rounded border ${checked ? 'border-[#c23bea] bg-[#c23bea] text-white' : 'border-[#5c6070] text-transparent'}`}><input type="checkbox" className="sr-only" checked={checked} onChange={() => toggle(role.slug)} /><FiCheck size={13} /></span></label>;
                                })}
                            </div>
                            {form.errors.roles && <p className="mt-2 text-xs text-[#f29aaa]">{form.errors.roles}</p>}
                        </div>
                    </div>

                    <footer className="flex shrink-0 justify-end gap-3 border-t border-[#292d39] bg-[#11121a] px-6 py-4 sm:px-8 lg:px-10">
                        <button type="button" onClick={onClose} disabled={form.processing} className="rounded-lg border border-[#343044] px-4 py-2.5 text-xs text-[#c8c2ce] hover:border-[#80508c]">Cancelar</button>
                        <button type="submit" disabled={form.processing} className="velvet-button">{form.processing ? 'Guardando…' : editing ? 'Guardar cambios →' : 'Crear usuario →'}</button>
                    </footer>
                </form>
            </section>
        </div>
    );
}
