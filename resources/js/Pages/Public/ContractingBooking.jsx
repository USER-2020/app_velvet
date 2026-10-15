import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { FiCalendar, FiChevronDown, FiClock, FiLock, FiSearch, FiX } from 'react-icons/fi';
import BrandMark from '../../Components/BrandMark';

const display = (value) => new Intl.DateTimeFormat('es-CO', {
    weekday: 'long', day: 'numeric', month: 'long', hour: 'numeric', minute: '2-digit', timeZone: 'America/Bogota',
}).format(new Date(value));

const time = (value) => new Intl.DateTimeFormat('es-CO', {
    hour: 'numeric', minute: '2-digit', timeZone: 'America/Bogota',
}).format(new Date(value));

const date = (value) => new Intl.DateTimeFormat('es-CO', {
    year: 'numeric', month: '2-digit', day: '2-digit', timeZone: 'America/Bogota',
}).format(new Date(value));

const normalizeSearch = (value) => value.toLocaleLowerCase('es-CO').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
const searchText = (slot) => normalizeSearch(`${display(slot.starts_at)} ${date(slot.starts_at)} ${time(slot.starts_at)} ${time(slot.ends_at)}`);

export default function ContractingBooking({ candidate, slots = [], actionUrl, expiresAt }) {
    const form = useForm({ slot_id: '' });
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const pickerRef = useRef(null);
    const searchRef = useRef(null);
    const selected = slots.find((slot) => String(slot.id) === String(form.data.slot_id));
    const filteredSlots = useMemo(() => {
        const normalized = normalizeSearch(query.trim());
        return normalized ? slots.filter((slot) => searchText(slot).includes(normalized)) : slots;
    }, [query, slots]);

    useEffect(() => {
        const closePicker = (event) => {
            if (pickerRef.current && !pickerRef.current.contains(event.target)) setOpen(false);
        };
        const closeOnEscape = (event) => event.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', closePicker);
        document.addEventListener('keydown', closeOnEscape);
        return () => {
            document.removeEventListener('mousedown', closePicker);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, []);

    useEffect(() => {
        if (open) searchRef.current?.focus();
    }, [open]);

    const selectSlot = (slot) => {
        form.setData('slot_id', String(slot.id));
        setQuery('');
        setOpen(false);
    };

    const submit = (event) => {
        event.preventDefault();
        form.post(actionUrl);
    };

    return <>
        <Head title="Agendar contratación" />
        <main className="min-h-screen bg-[#090a10] px-4 py-10 text-[#f7f1fb] sm:px-6">
            <div className="mx-auto max-w-2xl">
                <header className="flex items-center justify-between border-b border-[#292936] pb-6"><BrandMark className="h-9 w-auto" /><span className="text-[10px] uppercase tracking-[.2em] text-[#9d8fa5]">Contratación segura</span></header>
                <section className="mt-10"><p className="text-xs font-semibold uppercase tracking-[.28em] text-[#d56bea]">Siguiente etapa</p><h1 className="mt-3 font-editorial text-4xl text-white sm:text-5xl">Agenda tu contratación</h1><p className="mt-3 text-sm leading-6 text-[#a9a4b1]">Hola, {candidate.name}. Elige el horario que mejor te funcione para continuar con la formalización de tu ingreso.</p><span className="mt-4 inline-flex rounded-full border border-[#9142a7]/50 bg-[#3d164d] px-3 py-1.5 text-xs text-[#f0c1fa]">Código {candidate.code}</span></section>
                <form onSubmit={submit} className="mt-8 rounded-2xl border border-[#292d39] bg-[#11131c]/90 p-5 shadow-[0_18px_55px_rgba(0,0,0,.2)] sm:p-8">
                    <div className="flex items-start gap-3 border-b border-[#292d39] pb-6"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#3c1749] text-[#e2a0f1]"><FiCalendar /></span><div><h2 className="text-sm font-medium text-white">Horarios disponibles</h2><p className="mt-1 text-xs leading-5 text-[#7f8495]">Todos los horarios están expresados en hora de Colombia.</p></div></div>
                    {slots.length ? <div ref={pickerRef} className="relative mt-6">
                        <label htmlFor="contracting-slot-search" className="mb-2 block text-xs font-medium text-[#d8d3dc]">Selecciona una fecha y hora</label>
                        <button type="button" className={`flex w-full items-center justify-between gap-3 rounded-xl border px-4 py-4 text-left transition ${open ? 'border-[#c23bea] bg-[#1c1425] shadow-[0_0_0_1px_rgba(194,59,234,.2)]' : 'border-[#343044] bg-[#151522] hover:border-[#80508c]'}`} aria-haspopup="listbox" aria-expanded={open} onClick={() => setOpen((value) => !value)}>
                            <span className={selected ? 'min-w-0 text-sm text-[#eee8f2]' : 'text-sm text-[#898493]'}>{selected ? <><strong className="block truncate capitalize font-medium">{display(selected.starts_at)}</strong><small className="mt-1 block text-xs text-[#898493]"><FiClock className="mr-1 inline" />{time(selected.starts_at)} – {time(selected.ends_at)} · 1 hora</small></> : 'Busca y selecciona un horario disponible'}</span>
                            <FiChevronDown className={`shrink-0 text-[#d56bea] transition ${open ? 'rotate-180' : ''}`} />
                        </button>
                        {open && <div className="interview-slot-dropdown absolute z-[60] mt-2 w-full overflow-hidden rounded-xl border border-[#51405b] shadow-[0_18px_45px_rgba(0,0,0,.45)]">
                            <div className="border-b border-[#292d39] p-3"><div className="interview-slot-search-wrap flex items-center gap-2 rounded-lg border border-[#343044] px-3 focus-within:border-[#c23bea]"><FiSearch className="shrink-0 text-[#898493]" /><input ref={searchRef} id="contracting-slot-search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar por fecha u hora…" autoComplete="off" className="interview-slot-search w-full border-0 py-2.5 text-sm outline-none placeholder:text-[#898493] focus:ring-0" />{query && <button type="button" aria-label="Limpiar búsqueda" onClick={() => setQuery('')} className="text-[#898493] transition hover:text-white"><FiX /></button>}</div></div>
                            <div className="interview-slot-options max-h-80 overflow-y-auto p-2" role="listbox" aria-label="Horarios disponibles">{filteredSlots.length ? filteredSlots.map((slot) => <button type="button" role="option" aria-selected={String(selected?.id) === String(slot.id)} key={slot.id} onClick={() => selectSlot(slot)} className={`interview-slot-option flex w-full items-center justify-between gap-3 rounded-lg px-3 py-3 text-left transition ${String(selected?.id) === String(slot.id) ? 'is-selected' : ''}`}><span><strong className="block text-sm capitalize font-medium">{display(slot.starts_at)}</strong><small className="mt-1 block text-xs text-[#898493]">{time(slot.starts_at)} – {time(slot.ends_at)} · 1 hora</small></span><FiClock className="shrink-0 text-[#d56bea]" /></button>) : <p className="px-3 py-6 text-center text-xs text-[#898493]">No encontramos horarios para esa búsqueda.</p>}</div>
                        </div>}
                        {selected && <p className="mt-3 text-xs text-[#b8aabd]">Horario seleccionado: <span className="text-[#f0c1fb]">{date(selected.starts_at)} · {time(selected.starts_at)} – {time(selected.ends_at)}</span></p>}
                    </div> : <p className="mt-6 rounded-lg border border-[#624b2b] bg-[#2b2115] px-4 py-3 text-sm text-[#f2cf91]">No quedan horarios disponibles. Comunícate con nuestro equipo para recibir una nueva invitación.</p>}
                    {form.errors.slot_id && <p className="mt-3 text-xs text-[#ffb1bd]">{form.errors.slot_id}</p>}
                    <div className="mt-7 flex flex-col-reverse items-center justify-between gap-4 border-t border-[#292d39] pt-6 sm:flex-row"><p className="flex items-center gap-2 text-[11px] text-[#7f8495]"><FiLock /> Enlace personal hasta {new Intl.DateTimeFormat('es-CO', { dateStyle: 'short', timeStyle: 'short', timeZone: 'America/Bogota' }).format(new Date(expiresAt))}</p><button type="submit" disabled={form.processing || !selected} className="velvet-button w-full sm:w-auto">{form.processing ? 'Confirmando…' : 'Confirmar contratación →'}</button></div>
                </form>
            </div>
        </main>
    </>;
}
