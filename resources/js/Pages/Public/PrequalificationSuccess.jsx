import { Head } from '@inertiajs/react';
import { FiCheckCircle } from 'react-icons/fi';
import BrandMark from '../../Components/BrandMark';

export default function PrequalificationSuccess({ candidate = null }) {
    return <><Head title="Requisitos recibidos" /><main className="grid min-h-screen place-items-center bg-[#090a10] px-5 py-10 text-[#f7f1fb]"><section className="w-full max-w-xl rounded-2xl border border-[#292d39] bg-[#11131c] p-8 text-center shadow-[0_18px_55px_rgba(0,0,0,.25)] sm:p-12"><BrandMark className="mx-auto h-10 w-auto" /><span className="mx-auto mt-10 grid h-16 w-16 place-items-center rounded-full bg-[#16483f] text-[#8ee2ca]"><FiCheckCircle size={32} /></span><p className="mt-7 text-xs font-semibold uppercase tracking-[.28em] text-[#d56bea]">Formulario recibido</p><h1 className="mt-3 font-editorial text-4xl text-white">{candidate?.name ? `Gracias, ${candidate.name}.` : 'Gracias.'}</h1><p className="mx-auto mt-4 max-w-md text-sm leading-6 text-[#aaa3b0]">Recibimos tus requisitos iniciales y nuestro equipo continuará con la revisión de tu perfil.</p>{candidate?.code && <p className="mt-7 text-xs text-[#7f8495]">Código de candidata: <strong className="text-[#e0a0ed]">{candidate.code}</strong></p>}</section></main></>;
}

