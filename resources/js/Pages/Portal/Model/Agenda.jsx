import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { FiCalendar, FiClock, FiMapPin } from 'react-icons/fi';
import Layout from '@/Pages/Admin/Recruitment/Layout';
import { subscribeToRealtime } from '@/lib/ably';

const date = (value) => value ? new Intl.DateTimeFormat('es-CO', { dateStyle: 'full', timeStyle: 'short' }).format(new Date(value)) : 'Fecha pendiente';
const status = { SCHEDULED: 'Programado', IN_PROGRESS: 'En curso', IN_USE: 'En uso', COMPLETED: 'Completado', CANCELLED: 'Cancelado', NO_SHOW: 'No se presentó' };

export default function Agenda({ items = [] }) {
    useEffect(() => subscribeToRealtime((_data, event) => {
        if (!['operation.shift.created', 'operation.attendance.updated', 'room.reservation.created', 'room.reservation.updated'].includes(event)) return;
        router.reload({ only: ['items'], preserveScroll: true, preserveState: true });
    }), []);

    return <>
        <Head title="Mi agenda" />
        <Layout><div className="admin-page-content" data-portal-shell><header className="flex flex-col justify-between gap-5 border-b border-[#20232d] pb-7 md:flex-row md:items-end"><div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Portal de modelo</p><h1 className="mt-2 font-editorial text-4xl text-white sm:text-5xl">Mi agenda</h1><p className="mt-2 max-w-3xl text-sm text-[#969baa]">Reúne tus turnos, rooms y citas de contratación para que puedas preparar cada actividad.</p></div></header>
            <section data-tour="model-agenda-list" className="model-module-card mt-8">
                <div className="model-module-card__header"><div><p className="model-module-kicker">Próximas actividades</p><h2>Agenda operativa</h2><p>La agenda se ordena por la hora local de Bogotá y conserva el estado actual de cada actividad.</p></div><FiCalendar aria-hidden="true" className="model-module-icon" /></div>
                {items.length ? <ol className="model-agenda-list">{items.map((item) => <li key={item.id}><div className="model-agenda-list__date"><FiClock aria-hidden="true" /><time dateTime={item.startsAt}>{date(item.startsAt)}</time></div><div className="model-agenda-list__body"><div className="model-activity-list__meta"><strong>{item.title}</strong><span className="model-module-status">{status[item.status] || item.status || 'Pendiente'}</span></div>{item.room && <p><FiMapPin aria-hidden="true" />{item.room}</p>}{item.notes && <small>{item.notes}</small>}</div></li>)}</ol> : <div className="model-module-empty"><FiCalendar aria-hidden="true" /><p>No tienes actividades próximas.</p><span>Cuando se programe un turno, una reserva o una cita, aparecerá aquí.</span></div>}
            </section>
        </div></Layout>
    </>;
}
