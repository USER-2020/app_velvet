import { Head, Link } from '@inertiajs/react';
import Layout from '../Recruitment/Layout';
import { formatFriendlyDateTime, formatRelativeTime } from '../../../lib/date';

export default function Index({ notifications, channel, channels }) {
    const items = notifications?.data || [];
    const links = notifications?.links || [];

    return <>
        <Head title="Historial de notificaciones" />
        <Layout>
            <div className="notifications-page mx-auto max-w-[1200px]">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div><p className="notifications-eyebrow text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Centro de actividad</p><h1 className="notifications-title mt-2 font-editorial text-4xl text-white">Historial de notificaciones</h1><p className="notifications-description mt-2 text-sm text-[#969baa]">Consulta las notificaciones recibidas y accede directamente al módulo relacionado.</p></div>
                    <div className="notifications-filters flex flex-wrap gap-2"><Link href="/admin/notifications/history" className={`notifications-filter rounded-lg border px-3 py-2 text-xs ${!channel ? 'notifications-filter--active border-[#a92ad8] bg-[#3d164d] text-white' : 'border-[#343044] text-[#b9bac8]'}`}>Todas</Link>{channels.map((item) => <Link key={item} href={`/admin/notifications/history?channel=${encodeURIComponent(item)}`} className={`notifications-filter rounded-lg border px-3 py-2 text-xs ${channel === item ? 'notifications-filter--active border-[#a92ad8] bg-[#3d164d] text-white' : 'border-[#343044] text-[#b9bac8]'}`}>{item}</Link>)}</div>
                </div>
                <section className="notifications-history mt-6 overflow-hidden rounded-xl border border-[#292d39] bg-[#11131c]/90"><div className="notifications-history__list divide-y divide-[#292d39]">{items.length ? items.map((item) => { const data = item.data || {}; const href = data.interview_id ? '/admin/interviews' : data.candidate_id ? `/admin/candidates/${data.candidate_id}` : data.lead_id ? `/admin/leads/${data.lead_id}` : '/admin/notifications/history'; const reference = data.code || (data.lead_id ? `Lead #${data.lead_id}` : null); return <article key={item.id} className={`notification-item flex items-center gap-4 px-5 py-4 ${!item.read_at ? 'notification-item--unread' : ''}`}><div className="min-w-0 flex-1"><div className="flex items-center gap-2"><h2 className="notification-item__title text-sm font-medium text-white">{item.title}</h2>{!item.read_at && <span className="notification-item__new rounded-full bg-[#a92ad8] px-2 py-0.5 text-[9px] text-white">Nueva</span>}</div><p className="notification-item__description mt-1 text-xs text-[#a5a7b4]">{item.description}</p>{reference && <p className="notification-item__reference mt-1 text-[10px] font-medium text-[#d56bea]">{reference}</p>}<p className="notification-item__meta mt-2 text-[10px] text-[#777d8f]" title={formatFriendlyDateTime(item.created_at)}>{item.channel}  ·  {formatRelativeTime(item.created_at)}</p></div><Link href={href} className="notification-item__action grid h-8 w-8 shrink-0 place-items-center rounded-md border border-[#713080] text-lg text-[#e5a1f2] hover:bg-[#713080] hover:text-white" title="Abrir módulo relacionado" aria-label="Abrir módulo relacionado">↗</Link></article>; }) : <p className="notification-empty px-5 py-16 text-center text-sm text-[#777d8f]">No hay notificaciones registradas.</p>}</div></section>
                {links.length > 3 && <nav className="notifications-pagination mt-5 flex flex-wrap justify-center gap-2" aria-label="Paginación de notificaciones">{links.map((link, index) => link.url ? <Link key={index} href={link.url} className={`notifications-pagination__item rounded-lg border px-3 py-2 text-xs ${link.active ? 'notifications-pagination__item--active border-[#a92ad8] bg-[#55166e] text-white' : 'border-[#343044] text-[#aaa5b5] hover:border-[#a92ad8] hover:text-white'}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={index} className="notifications-pagination__item rounded-lg border border-[#343044] px-3 py-2 text-xs text-[#555a6b]" dangerouslySetInnerHTML={{ __html: link.label }} />)}</nav>}
                {notifications?.total > 0 && <p className="notifications-count mt-3 text-center text-[11px] text-[#777d8f]">Mostrando {notifications.from}–{notifications.to} de {notifications.total} notificaciones</p>}
            </div>
        </Layout>
    </>;
}

