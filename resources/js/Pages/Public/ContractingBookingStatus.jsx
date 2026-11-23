import { Head } from '@inertiajs/react';
import { FiCalendar, FiCheckCircle, FiClock } from 'react-icons/fi';
import BrandMark from '../../Components/BrandMark';
import ProcessSupportActions from '../../Components/ProcessSupportActions';

const states = {
    used: {
        title: 'Ya utilizaste esta invitación',
        description: 'La cita se agendó con este enlace. Revisa el correo de confirmación para consultar la fecha y los próximos pasos.',
        icon: FiCheckCircle,
    },
    confirmation: {
        title: 'Consulta tu confirmación',
        description: 'Abre el correo de confirmación de tu cita para revisar la fecha y los próximos pasos.',
        icon: FiCalendar,
    },
    expired: {
        title: 'Tu invitación venció',
        description: 'Escríbenos para que el equipo revise tu proceso y te envíe un enlace nuevo si corresponde.',
        icon: FiClock,
    },
    unavailable: {
        title: 'La invitación ya no está disponible',
        description: 'Revisa si recibiste una invitación más reciente. Si necesitas agendar tu cita, contacta al equipo.',
        icon: FiCalendar,
    },
    invalid: {
        title: 'El enlace de contratación no es válido',
        description: 'Abre el enlace completo desde el correo de invitación o escríbenos para recibir ayuda.',
        icon: FiCalendar,
    },
    conflict: {
        title: 'Ya hay una cita programada',
        description: 'Consulta el correo de confirmación. Si necesitas cambiar el horario, contacta al equipo.',
        icon: FiCalendar,
    },
};

export default function ContractingBookingStatus({ state, whatsappUrl = null }) {
    const { title, description, icon: Icon } = states[state] ?? states.unavailable;
    const used = state === 'used';

    return <>
        <Head title={title} />
        <main className="grid min-h-screen place-items-center bg-[#090a10] px-4 py-10 text-[#f7f1fb]">
            <section className="w-full max-w-xl rounded-2xl border border-[#292d39] bg-[#11131c] p-6 text-center sm:p-12" aria-labelledby="contracting-status-title">
                <BrandMark className="mx-auto h-10 w-auto" />
                <span className={`mx-auto mt-8 grid h-14 w-14 place-items-center rounded-full ${used ? 'bg-[#12372e] text-[#9af2cb]' : 'bg-[#321344] text-[#edb4f8]'}`}>
                    <Icon size={25} aria-hidden="true" />
                </span>
                <p className="mt-6 text-xs font-semibold uppercase tracking-[.2em] text-[#d56bea]">Agenda de contratación</p>
                <h1 id="contracting-status-title" className="mt-3 font-editorial text-3xl text-white sm:text-4xl">{title}</h1>
                <p className="mt-4 text-sm leading-6 text-[#c8c2ce]">{description}</p>
                <ProcessSupportActions whatsappUrl={whatsappUrl} />
            </section>
        </main>
    </>;
}
