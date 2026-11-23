import InputError from '@/Components/InputError';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { FiEye, FiEyeOff } from 'react-icons/fi';

function PasswordField({ id, label, value, onChange, inputRef, autoComplete, error }) {
    const [visible, setVisible] = useState(false);
    const Icon = visible ? FiEyeOff : FiEye;

    return <div>
        <label htmlFor={id} className="profile-field-label text-xs font-medium text-[#e9e5ed]">{label}</label>
        <div className="relative mt-2">
            <input id={id} ref={inputRef} type={visible ? 'text' : 'password'} className="velvet-input mt-0 pr-12" value={value} onChange={onChange} autoComplete={autoComplete} />
            <button type="button" className="password-visibility-toggle absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-2 text-[#756782] transition hover:bg-[#f4e5fa] hover:text-[#72188a] focus:outline-none focus:ring-2 focus:ring-[#a526cc]" onClick={() => setVisible((current) => !current)} aria-label={visible ? `Ocultar ${label.toLowerCase()}` : `Mostrar ${label.toLowerCase()}`} title={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}>
                <Icon size={17} aria-hidden="true" />
            </button>
        </div>
        <InputError message={error} className="mt-2" />
    </div>;
}

export default function UpdatePasswordForm() {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();
    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({ current_password: '', password: '', password_confirmation: '' });

    const updatePassword = (event) => {
        event.preventDefault();
        put(route('password.update'), { preserveScroll: true, onSuccess: () => reset(), onError: (formErrors) => { if (formErrors.password) { reset('password', 'password_confirmation'); passwordInput.current?.focus(); } if (formErrors.current_password) { reset('current_password'); currentPasswordInput.current?.focus(); } } });
    };

    return <div>
        <header><p className="text-[10px] uppercase tracking-[.22em] text-[#d56bea]">Seguridad</p><h2 className="mt-2 font-editorial text-2xl text-white">Cambiar contraseña</h2><p className="profile-muted mt-2 text-sm leading-6 text-[#969baa]">Mantén segura tu cuenta usando una contraseña única y robusta.</p></header>
        <form onSubmit={updatePassword} className="mt-7 space-y-5">
            <PasswordField id="current_password" label="Contraseña actual" inputRef={currentPasswordInput} value={data.current_password} onChange={(event) => setData('current_password', event.target.value)} autoComplete="current-password" error={errors.current_password} />
            <PasswordField id="password" label="Nueva contraseña" inputRef={passwordInput} value={data.password} onChange={(event) => setData('password', event.target.value)} autoComplete="new-password" error={errors.password} />
            <PasswordField id="password_confirmation" label="Confirmar contraseña" value={data.password_confirmation} onChange={(event) => setData('password_confirmation', event.target.value)} autoComplete="new-password" error={errors.password_confirmation} />
            <div className="flex items-center gap-4 pt-2"><button type="submit" disabled={processing} className="velvet-button">{processing ? 'Actualizando…' : 'Actualizar contraseña'}</button><Transition show={recentlySuccessful} enter="transition ease-out duration-200" enterFrom="opacity-0" leave="transition ease-in duration-200" leaveTo="opacity-0"><p className="text-xs text-[#8ff0bd]">Contraseña actualizada.</p></Transition></div>
        </form>
    </div>;
}

