import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();
    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({ password: '' });
    const closeModal = () => { setConfirmingUserDeletion(false); clearErrors(); reset(); };
    const deleteUser = (event) => { event.preventDefault(); destroy(route('profile.destroy'), { preserveScroll: true, onSuccess: closeModal, onError: () => passwordInput.current?.focus(), onFinish: () => reset() }); };

    return <section>
        <header className="profile-danger-header"><p className="profile-danger-eyebrow text-[10px] uppercase tracking-[.22em] text-[#e7839a]">Zona sensible</p><h2 className="profile-danger-title mt-2 font-editorial text-2xl text-white">Eliminar cuenta</h2><p className="profile-danger-description mt-2 max-w-2xl text-sm leading-6 text-[#b7a7ad]">Esta acción elimina permanentemente tu cuenta y sus datos. Solo continúa si estás seguro.</p></header>
        <button type="button" onClick={() => setConfirmingUserDeletion(true)} className="profile-danger-trigger mt-6 rounded-lg border border-[#8d3d55] px-4 py-2.5 text-sm text-[#f29aaa] transition hover:bg-[#4a1e2d] hover:text-white">Eliminar mi cuenta</button>
        <Modal show={confirmingUserDeletion} onClose={closeModal}>
            <form onSubmit={deleteUser} className="profile-delete-modal bg-[#15121d] p-6 text-[#f7f1fb] sm:rounded-xl"><h2 className="font-editorial text-2xl">¿Eliminar tu cuenta?</h2><p className="profile-delete-modal__description mt-2 text-sm leading-6 text-[#aaa5b4]">Esta acción no se puede deshacer. Escribe tu contraseña para confirmar.</p><div className="mt-6"><InputLabel htmlFor="password" value="Contraseña" className="text-[#e9e5ed]" /><input id="password" type="password" name="password" ref={passwordInput} value={data.password} onChange={(event) => setData('password', event.target.value)} className="velvet-input" autoFocus placeholder="Tu contraseña" /><InputError message={errors.password} className="mt-2" /></div><div className="mt-6 flex justify-end gap-3"><button type="button" onClick={closeModal} className="profile-delete-modal__cancel rounded-lg border border-[#343846] px-4 py-2.5 text-sm text-[#c7c4cf] hover:bg-white/5">Cancelar</button><button type="submit" disabled={processing} className="profile-delete-modal__confirm rounded-lg bg-[#8c2445] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#a82d52]">{processing ? 'Eliminando…' : 'Eliminar cuenta'}</button></div></form>
        </Modal>
    </section>;
}

