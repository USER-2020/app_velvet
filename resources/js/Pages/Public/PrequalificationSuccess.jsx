import { Head } from '@inertiajs/react';
import { FiCheckCircle } from 'react-icons/fi';
import BrandMark from '../../Components/BrandMark';
import ProcessSupportActions from '../../Components/ProcessSupportActions';

export default function PrequalificationSuccess({ candidate = null, whatsappUrl = null }) {
    return <><Head title="Requisitos recibidos" /><main className="grid min-h-screen place-items-center bg-[#090a10] px-5 py-10 text-[#f7f1fb]"><section className="w-full max-w-xl rounded-2xl border border-[#292d39] bg-[#11131c] p-8 text-center shadow-[0_18px_55px_rgba(0,0,0,.25)] sm:p-12"><BrandMark className="mx-auto h-10 w-auto" /><span className="mx-auto mt-10 grid h-16 w-16 place-items-center rounded-full bg-[#16483f] text-[#8ee2ca]"><FiCheckCircle size={32} /></span><p className="mt-7 text-xs font-semibold uppercase tracking-[.28em] text-[#d56bea]">Fase 2 · Precalificación completada</p><h1 className="mt-3 font-editorial text-4xl text-white">{candidate?.name ? `¡Excelente, ${candidate.name}!` : '¡Precalificación completada!'}</h1><p className="mx-auto mt-4 max-w-md text-sm leading-6 text-[#aaa3b0]">Recibimos tus requisitos y validaciones. El equipo revisará tu perfil y te enviará el siguiente paso del proceso.</p>{candidate?.code && <p className="mt-7 text-xs text-[#7f8495]">Código de candidata: <strong className="text-[#e0a0ed]">{candidate.code}</strong></p>}<p className="mt-5 text-xs text-[#898493]">Siguiente fase: coordinación de entrevista.</p><ProcessSupportActions whatsappUrl={whatsappUrl} /></section></main></>;
}

