import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FiCompass, FiEye, FiEyeOff, FiLock, FiUsers } from 'react-icons/fi';
import toast from 'react-hot-toast';
import Layout from '@/Pages/Admin/Recruitment/Layout';

export default function Index({ enabled = true }) {
    const [isEnabled, setIsEnabled] = useState(enabled);
    const [saving, setSaving] = useState(false);

    const update = (nextValue) => {
        if (saving || nextValue === isEnabled) return;

        const previousValue = isEnabled;
        setIsEnabled(nextValue);
        setSaving(true);

        router.put('/admin/guided-tours', { enabled: nextValue }, {
            preserveScroll: true,
            onError: () => {
                setIsEnabled(previousValue);
                toast.error('No fue posible actualizar las visitas guiadas.');
            },
            onFinish: () => setSaving(false),
        });
    };

    return <><Head title="Visitas guiadas" /><Layout><div className="mx-auto max-w-[1040px]"><div className="flex flex-col justify-between gap-5 border-b border-[#20232d] pb-7 md:flex-row md:items-end"><div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Super administración</p><h1 className="mt-2 font-editorial text-4xl text-white">Visitas guiadas</h1><p className="mt-2 max-w-2xl text-sm leading-6 text-[#969baa]">Controla si las guías contextuales están disponibles en toda la plataforma.</p></div><span className="inline-flex w-fit items-center gap-2 rounded-full border border-[#653074] bg-[#27142f] px-3 py-1.5 text-[11px] font-medium text-[#eab1f7]"><FiLock size={13} />Solo Super Administrador</span></div><section className="mt-8 overflow-hidden rounded-xl border border-[#30273a] bg-[#11131c]/90 shadow-[0_18px_55px_rgba(0,0,0,.16)]"><div className="flex flex-col gap-6 p-6 sm:p-8 md:flex-row md:items-center md:justify-between"><div className="flex max-w-2xl items-start gap-4"><span className={`grid h-12 w-12 shrink-0 place-items-center rounded-full border ${isEnabled ? 'border-[#7c3c92] bg-[#351841] text-[#efaafc]' : 'border-[#343746] bg-[#1a1c26] text-[#a2a6b4]'}`}><FiCompass size={21} /></span><div><div className="flex flex-wrap items-center gap-2"><h2 className="text-lg font-medium text-white">Guías por módulo</h2><span className={`rounded-full px-2.5 py-1 text-[10px] font-semibold ${isEnabled ? 'bg-[#12372e] text-[#9af2cb]' : 'bg-[#38222a] text-[#f2a4ad]'}`}>{isEnabled ? 'Activas' : 'Desactivadas'}</span></div><p className="mt-2 text-sm leading-6 text-[#969baa]">Cuando están activas, cada persona recibe la guía de los módulos que puede usar y puede repetirla desde el ícono de ayuda.</p></div></div><button type="button" role="switch" aria-checked={isEnabled} aria-label="Activar visitas guiadas para todos los usuarios" onClick={() => update(!isEnabled)} disabled={saving} className={`relative inline-flex h-8 w-14 shrink-0 items-center rounded-full border p-1 transition ${isEnabled ? 'border-[#bf4ee0] bg-[#74218d]' : 'border-[#414555] bg-[#1a1c26]'} ${saving ? 'cursor-wait opacity-70' : 'cursor-pointer'}`}><span className={`grid h-6 w-6 place-items-center rounded-full bg-white shadow-sm transition-transform ${isEnabled ? 'translate-x-6 text-[#4d175d]' : 'translate-x-0 text-[#73798a]'}`}>{isEnabled ? <FiEye size={13} /> : <FiEyeOff size={13} />}</span></button></div><div className="grid border-t border-[#292d39] sm:grid-cols-2"><div className="flex gap-3 p-5 sm:border-r sm:border-[#292d39]"><FiUsers className="mt-0.5 shrink-0 text-[#d56bea]" size={17} /><p className="text-xs leading-5 text-[#9ca0ae]"><span className="block font-medium text-white">Alcance global</span>El cambio se aplica a usuarios internos, modelos y monitores en su siguiente navegación.</p></div><div className="flex gap-3 p-5"><FiCompass className="mt-0.5 shrink-0 text-[#d56bea]" size={17} /><p className="text-xs leading-5 text-[#9ca0ae]"><span className="block font-medium text-white">Progreso conservado</span>Desactivar las guías no borra lo que cada persona ya revisó.</p></div></div></section></div></Layout></>;
}
