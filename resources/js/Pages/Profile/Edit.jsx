import { Head, usePage } from '@inertiajs/react';
import { FiCheckCircle, FiClock, FiMail, FiShield, FiUser } from 'react-icons/fi';
import { formatFriendlyDate } from '../../lib/date';
import Layout from '../Admin/Recruitment/Layout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

function InfoItem({ icon: Icon, label, value }) {
    return <div className="flex items-start gap-3"><span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#351044] text-[#db8bee]"><Icon size={16} /></span><div><p className="text-[10px] uppercase tracking-[.16em] text-[#7f8495]">{label}</p><p className="mt-1 text-sm text-[#eee8f2]">{value}</p></div></div>;
}

export default function Edit({ mustVerifyEmail, status, identityVerification = {}, profilePhoto = {} }) {
    const user = usePage().props.auth.user;
    const role = user.roles?.[0];
    const roleLabels = {
        super_admin: 'Super Administrador',
        admin: 'Administrador',
        recruiter: 'Reclutador',
        hr: 'Recursos Humanos',
        manager: 'Manager / Coordinador',
        monitor: 'Monitor',
        model: 'Modelo',
    };
    const roleLabel = role?.name || roleLabels[role?.slug] || 'Sin rol';
    const initials = user.name?.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase() || 'VA';
    const createdAt = formatFriendlyDate(user.created_at);
    const verified = Boolean(user.email_verified_at);

    return <>
        <Head title="Mi perfil" />
        <Layout>
            <div className="mx-auto max-w-[1320px]">
                <div className="mb-8">
                    <p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Configuración de cuenta</p>
                    <h1 className="mt-2 font-editorial text-4xl text-white">Mi perfil</h1>
                    <p className="mt-2 text-sm text-[#969baa]">Administra tu información y las preferencias de acceso a Velvet.</p>
                </div>

                <section className="relative overflow-hidden rounded-xl border border-[#30233a] bg-gradient-to-br from-[#1b1023] via-[#11131c] to-[#10121a] p-6 shadow-[0_20px_60px_rgba(0,0,0,.22)] sm:p-8">
                    <div className="pointer-events-none absolute -right-24 -top-32 h-72 w-72 rounded-full bg-[#a82bd0]/20 blur-3xl" />
                    <div className="relative flex flex-col gap-7 md:flex-row md:items-center md:justify-between">
                        <div className="flex items-center gap-5"><div className="relative h-20 w-20 shrink-0"><span className={`grid h-20 w-20 place-items-center overflow-hidden rounded-2xl border border-[#d56bea]/50 bg-[#542062] text-2xl font-semibold text-white shadow-[0_0_30px_rgba(194,59,234,.25)] ${profilePhoto.url ? 'p-0' : ''}`}>{profilePhoto.url ? <img src={profilePhoto.url} alt="Foto de perfil" className="h-full w-full object-cover" /> : initials}</span>{identityVerification.verified && <span className="absolute -bottom-2 -right-2 grid h-6 w-6 place-items-center rounded-full border-2 border-[#11131c] bg-[#2d8669] text-white" title="Identidad verificada por Didit"><FiCheckCircle size={13} /></span>}</div><div><p className="text-[10px] uppercase tracking-[.24em] text-[#d56bea]">Velvet Studio</p><h2 className="mt-2 font-editorial text-3xl text-white">{user.name}</h2><p className="mt-1 text-sm text-[#a8acbb]">{user.email}</p>{identityVerification.verified && <p className="mt-2 text-[11px] text-[#9af2cb]">Identidad verificada por Didit{profilePhoto.source === 'didit' ? ' · foto importada de forma privada' : ''}</p>}</div></div>
                        <div className="grid gap-5 sm:grid-cols-3 md:min-w-[500px]">
                            <InfoItem icon={FiShield} label="Rol" value={roleLabel} />
                            <InfoItem icon={verified ? FiCheckCircle : FiMail} label="Estado" value={verified ? 'Email verificado' : 'Email pendiente'} />
                            <InfoItem icon={FiClock} label="Miembro desde" value={createdAt} />
                        </div>
                    </div>
                </section>

                <div className="mt-7 grid gap-7 xl:grid-cols-[minmax(0,1.1fr)_minmax(360px,.9fr)]">
                    <section className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6 shadow-[0_18px_55px_rgba(0,0,0,.16)] backdrop-blur-xl sm:p-8">
                        <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} profilePhoto={profilePhoto} />
                    </section>
                    <section className="rounded-xl border border-[#292d39] bg-[#11131c]/90 p-6 shadow-[0_18px_55px_rgba(0,0,0,.16)] backdrop-blur-xl sm:p-8">
                        <UpdatePasswordForm />
                    </section>
                </div>

                <section className="mt-7 rounded-xl border border-[#472633] bg-[#171118] p-6 shadow-[0_18px_55px_rgba(0,0,0,.14)] sm:p-8">
                    <DeleteUserForm />
                </section>
            </div>
        </Layout>
    </>;
}

