import InputError from '@/Components/InputError';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status, profilePhoto = {} }) {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({ name: user.name, email: user.email, profile_photo: null });

    const submit = (event) => {
        event.preventDefault();
        patch(route('profile.update'), { forceFormData: true });
    };

    return <div>
        <header><p className="text-[10px] uppercase tracking-[.22em] text-[#d56bea]">Información personal</p><h2 className="mt-2 font-editorial text-2xl text-white">Datos de la cuenta</h2><p className="mt-2 text-sm leading-6 text-[#969baa]">Actualiza el nombre y el correo que utilizas dentro de la plataforma.</p></header>
        <form onSubmit={submit} className="mt-7 space-y-5">
            <div><label htmlFor="name" className="text-xs font-medium text-[#e9e5ed]">Nombre</label><input id="name" className="velvet-input" value={data.name} onChange={(event) => setData('name', event.target.value)} required autoComplete="name" /> <InputError className="mt-2" message={errors.name} /></div>
            <div><label htmlFor="email" className="text-xs font-medium text-[#e9e5ed]">Correo electrónico</label><input id="email" type="email" className="velvet-input" value={data.email} onChange={(event) => setData('email', event.target.value)} required autoComplete="username" /> <InputError className="mt-2" message={errors.email} /></div>
            <div data-tour="profile-photo"><label htmlFor="profile_photo" className="text-xs font-medium text-[#e9e5ed]">Foto de perfil</label><input id="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" className="velvet-input file:mr-3 file:rounded-md file:border-0 file:bg-[#351044] file:px-3 file:py-1.5 file:text-xs file:text-[#f0c5fa]" onChange={(event) => setData('profile_photo', event.target.files?.[0] || null)} /><p className="mt-2 text-[11px] leading-5 text-[#7f8495]">JPG, PNG o WebP, máximo 5 MB. {profilePhoto.source === 'didit' ? 'La foto inicial fue importada desde tu validación Didit; puedes reemplazarla.' : 'La imagen se guarda de forma privada.'}</p><InputError className="mt-2" message={errors.profile_photo} /></div>
            {mustVerifyEmail && user.email_verified_at === null && <div className="rounded-lg border border-[#745321] bg-[#302211] p-3 text-xs leading-5 text-[#f1c77c]">Tu correo todavía no está verificado. <Link href={route('verification.send')} method="post" as="button" className="underline hover:text-white">Reenviar enlace de verificación</Link>{status === 'verification-link-sent' && <span className="ml-2 text-[#8ff0bd]">Enviado.</span>}</div>}
            <div className="flex items-center gap-4 pt-2"><button type="submit" disabled={processing} className="velvet-button">{processing ? 'Guardando…' : 'Guardar cambios'}</button><Transition show={recentlySuccessful} enter="transition ease-out duration-200" enterFrom="opacity-0" leave="transition ease-in duration-200" leaveTo="opacity-0"><p className="text-xs text-[#8ff0bd]">Cambios guardados.</p></Transition></div>
        </form>
    </div>;
}

