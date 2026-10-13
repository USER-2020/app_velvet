import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { FiBell, FiBriefcase, FiCalendar, FiChevronDown, FiClipboard, FiCompass, FiDatabase, FiFileText, FiGrid, FiHome, FiLogOut, FiMenu, FiSearch, FiSettings, FiSun, FiUser, FiUserCheck, FiUsers, FiX } from 'react-icons/fi';
import BrandMark from '@/Components/BrandMark';
import { useAuthorization } from '@/lib/authorization';
import toast from 'react-hot-toast';
import { subscribeToRealtime } from '@/lib/ably';
import { formatFriendlyDateTime, formatRelativeTime } from '@/lib/date';

function notificationHref(item) {
    const data = item?.data || {};
    if (data.interview_id) return '/admin/interviews';
    if (data.candidate_id) return `/admin/candidates/${data.candidate_id}`;
    if (data.lead_id) return `/admin/leads/${data.lead_id}`;
    return '/admin/notifications/history';
}

const sections = [
    { label: 'Guías', items: [['Visitas guiadas', '/admin/guided-tours', FiCompass]] },
    { label: 'Reclutamiento', items: [['Leads', '/admin/leads', FiUsers], ['Candidatos', '/admin/candidates', FiUserCheck], ['Entrevistas', '/admin/interviews', FiCalendar], ['Contratación', '/admin/contracting', FiBriefcase], ['Calendario', '/admin/calendar', FiCalendar], ['Pipeline', '/admin/recruitment', FiGrid]] },
    { label: 'Onboarding', items: [['Procesos', '/admin/processes', FiClipboard], ['Validaciones', '/admin/validations', FiFileText], ['Documentos', '/admin/documents', FiFileText]] },
    { label: 'Personas', items: [['Modelos', '/admin/candidates?type=MODEL', FiUsers], ['Monitores', '/admin/candidates?type=MONITOR', FiBriefcase]] },
    { label: 'Operación', items: [['Operación diaria', '/admin/operations', FiClipboard], ['Sesiones', '/admin/productivity', FiCalendar], ['Plataformas', '/admin/platforms', FiDatabase], ['Administración', '/admin/administration', FiBriefcase], ['Rooms', '/admin/rooms', FiHome]] },
    { label: 'Configuración', items: [['Workflows', '/admin/workflows', FiSettings], ['Datos de prueba', '/admin/training-data', FiDatabase], ['Usuarios', '/admin/users', FiUsers], ['Roles y permisos', '/admin/access', FiSettings]] },
];

function Wordmark() {
    return <BrandMark className="h-11 max-w-[164px]" />;
}

const portalSections = {
    monitor: [{ label: 'Operación', items: [['Dashboard', '/monitor/dashboard', FiGrid]] }],
    model: [{ label: 'Operación', items: [['Dashboard', '/model/dashboard', FiGrid]] }],
};

function isActive(label, url) {
    if (label === 'Dashboard') return url === '/monitor/dashboard' || url === '/model/dashboard';
    if (label === 'Mi perfil') return url === '/model/profile';
    if (label === 'Modelos') return url === '/monitor/models' || url.startsWith('/monitor/models/');
    const [path, query = ''] = url.split('?');
    if (label === 'Modelos') return path === '/admin/candidates' && query.includes('type=MODEL');
    if (label === 'Monitores') return path === '/admin/candidates' && query.includes('type=MONITOR');
    if (label === 'Entrevistas') return path === '/admin/interviews';
    if (label === 'Contratación') return path === '/admin/contracting';
    if (label === 'Calendario') return path === '/admin/calendar';
    if (label === 'Candidatos') return path === '/admin/candidates' && !query.includes('type=') && !query.includes('status=');
    if (label === 'Leads') return path === '/admin/leads' && !query.includes('status=NEW');
    if (label === 'Validaciones') return path === '/admin/validations';
    if (label === 'Documentos') return path === '/admin/documents';
    if (label === 'Pipeline') return path === '/admin/recruitment' || path === '/admin';
    if (label === 'Procesos') return path === '/admin/processes';
    if (label === 'Usuarios') return path === '/admin/users';
    if (label === 'Roles y permisos') return path === '/admin/access';
    if (label === 'Workflows') return path === '/admin/workflows';
    if (label === 'Visitas guiadas') return path === '/admin/guided-tours';
    if (label === 'Datos de prueba') return path === '/admin/training-data';
    if (label === 'Rooms') return path === '/admin/rooms' || path === '/monitor/rooms' || path === '/model/rooms';
    if (label === 'Operación diaria') return path === '/admin/operations';
    if (label === 'Sesiones') return path === '/admin/productivity';
    if (label === 'Plataformas') return path === '/admin/platforms';
    if (label === 'Administración') return path === '/admin/administration';
    return false;
}

const requiredPermissions = {
    Leads: 'leads.view',
    Candidatos: 'candidates.view',
    Entrevistas: 'interviews.view',
    Contratación: 'contracts.create',
    Calendario: 'calendar.view',
    Pipeline: 'dashboard.view',
    Procesos: 'onboarding.view',
    Validaciones: 'documents.verify',
    Documentos: 'documents.view',
    Modelos: 'models.view_all',
    Monitores: 'models.view_all',
    Rooms: 'rooms.view',
    'Operación diaria': 'shifts.view_team',
    Sesiones: 'reports.operational.view',
    Plataformas: 'platforms.view',
    Administración: 'branches.manage',
    Workflows: 'settings.manage',
    'Visitas guiadas': 'super_admin',
    'Datos de prueba': 'super_admin',
    Usuarios: 'users.view',
    'Roles y permisos': 'roles.view',
};

function Sidebar({ onClose, url, user, can }) {
    const roleSlug = user?.roles?.[0]?.slug;
    if (portalSections[roleSlug]) return <PortalSidebar onClose={onClose} url={url} user={user} can={can} roleSlug={roleSlug} />;
    const canLabel = (label) => can(requiredPermissions[label]);
    return <aside className="flex h-full w-[224px] flex-col border-r border-[#1f222d] bg-[#090a10] px-4 py-5"><div className="px-3"><Wordmark /></div><nav className="sidebar-scroll mt-8 flex-1 space-y-6 overflow-y-auto">{sections.map((section) => { const visibleItems = section.items.filter(([label]) => canLabel(label)); if (!visibleItems.length) return null; return <div key={section.label}><p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-[#8990a1]">{section.label}</p><div className="space-y-1">{visibleItems.map(([label, href, Icon]) => { const active = isActive(label, url); return <Link key={label} href={href} onClick={onClose} className={`group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition ${active ? 'bg-[#55166e] text-white shadow-[0_8px_22px_rgba(136,52,153,.22)]' : 'text-[#b9bac8] hover:bg-[#171522] hover:text-white'}`}><Icon size={17} strokeWidth={1.7} /><span>{label}</span>{active && <span className="ml-auto h-1.5 w-1.5 rounded-full bg-[#e6a0f2]" />}</Link>; })}</div></div>; })}</nav><div className="mt-5 overflow-hidden rounded-lg border border-[#242632] bg-[#11121a]"><div className="relative h-32 overflow-hidden"><img src="/onboarding/modelo.webp" alt="" className="h-full w-full object-cover opacity-70" /><div className="absolute inset-0 bg-gradient-to-t from-[#11121a] via-[#351044]/25 to-transparent" /><p className="absolute bottom-3 left-3 right-3 font-editorial text-lg leading-none text-white">Talento real.<br />Historias más grandes.</p></div></div></aside>;
}

function PortalSidebar({ onClose, url, user, can, roleSlug }) {
    if (roleSlug === 'monitor') return <GroupedPortalSidebar onClose={onClose} url={url} can={can} />;
    const href = roleSlug === 'monitor' ? '/monitor/dashboard' : '/model/dashboard';
    const permission = roleSlug === 'monitor' ? 'rooms.view_team' : 'rooms.view_own';
    const items = [{ label: 'Dashboard', href, icon: FiGrid, permission }, ...(roleSlug === 'model' ? [{ label: 'Rooms', href: '/model/rooms', icon: FiHome, permission: 'rooms.view_own' }, { label: 'Mi perfil', href: '/model/profile', icon: FiUser, permission: 'profile.view_own' }] : []), ...(roleSlug === 'monitor' ? [{ label: 'Rooms', href: '/monitor/rooms', icon: FiHome, permission: 'rooms.view_team' }, { label: 'Modelos', href: '/monitor/models', icon: FiUsers, permission: 'models.view_assigned' }] : [])];
    return <aside className="flex h-full w-[224px] flex-col border-r border-[#1f222d] bg-[#090a10] px-4 py-5"><div className="px-3"><Wordmark /></div><nav className="sidebar-scroll mt-8 flex-1 overflow-y-auto"><p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-[#8990a1]">Operación</p>{items.filter((item) => can(item.permission)).map(({ label, href: itemHref, icon: Icon }) => <Link key={label} href={itemHref} onClick={onClose} className={`group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition ${isActive(label, url) ? 'bg-[#55166e] text-white shadow-[0_8px_22px_rgba(136,52,153,.22)]' : 'text-[#b9bac8] hover:bg-[#171522] hover:text-white'}`}><Icon size={17} strokeWidth={1.7} /><span>{label}</span>{isActive(label, url) && <span className="ml-auto h-1.5 w-1.5 rounded-full bg-[#e6a0f2]" />}</Link>)}</nav><div className="mt-5 overflow-hidden rounded-lg border border-[#242632] bg-[#11121a]"><div className="relative h-32 overflow-hidden"><img src="/onboarding/modelo.webp" alt="" className="h-full w-full object-cover opacity-70" /><div className="absolute inset-0 bg-gradient-to-t from-[#11121a] via-[#351044]/25 to-transparent" /><p className="absolute bottom-3 left-3 right-3 font-editorial text-lg leading-none text-white">Talento real.<br />Historias más grandes.</p></div></div></aside>;
}

function GroupedPortalSidebar({ onClose, url, can }) {
    const groups = [
        { label: 'Operación', items: [{ label: 'Dashboard', href: '/monitor/dashboard', icon: FiGrid, permission: 'rooms.view_team' }, { label: 'Rooms', href: '/monitor/rooms', icon: FiHome, permission: 'rooms.view_team' }] },
        { label: 'Personas', items: [{ label: 'Modelos', href: '/monitor/models', icon: FiUsers, permission: 'models.view_assigned' }] },
    ];
    return <aside className="flex h-full w-[224px] flex-col border-r border-[#1f222d] bg-[#090a10] px-4 py-5"><div className="px-3"><Wordmark /></div><nav className="sidebar-scroll mt-8 flex-1 space-y-6 overflow-y-auto">{groups.map((group) => { const items = group.items.filter((item) => can(item.permission)); if (!items.length) return null; return <div key={group.label}><p className="mb-2 px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-[#8990a1]">{group.label}</p><div className="space-y-1">{items.map(({ label, href, icon: Icon }) => <Link key={label} href={href} onClick={onClose} className={`group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition ${isActive(label, url) ? 'bg-[#55166e] text-white shadow-[0_8px_22px_rgba(136,52,153,.22)]' : 'text-[#b9bac8] hover:bg-[#171522] hover:text-white'}`}><Icon size={17} strokeWidth={1.7} /><span>{label}</span>{isActive(label, url) && <span className="ml-auto h-1.5 w-1.5 rounded-full bg-[#e6a0f2]" />}</Link>)}</div></div>; })}</nav><div className="mt-5 overflow-hidden rounded-lg border border-[#242632] bg-[#11121a]"><div className="relative h-32 overflow-hidden"><img src="/onboarding/modelo.webp" alt="" className="h-full w-full object-cover opacity-70" /><div className="absolute inset-0 bg-gradient-to-t from-[#11121a] via-[#351044]/25 to-transparent" /><p className="absolute bottom-3 left-3 right-3 font-editorial text-lg leading-none text-white">Talento real.<br />Historias más grandes.</p></div></div></aside>;
}

function ProfileMenu({ user, open, onToggle, menuRef }) {
    const initials = user?.name?.split(' ').map((part) => part[0]).join('').slice(0, 2) || 'VA';
    return <div ref={menuRef} className="relative hidden sm:block"><button type="button" onClick={onToggle} aria-haspopup="menu" aria-expanded={open} className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left transition hover:bg-[#171522]"><span className="grid h-9 w-9 place-items-center overflow-hidden rounded-full bg-[#3b2046] text-xs font-semibold text-[#f0c5fa]">{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" className="h-full w-full object-cover" /> : initials}</span><span className="hidden leading-tight lg:block"><span className="block text-xs font-medium text-white">{user?.name || 'Velvet Admin'}</span><span className="block text-[11px] text-[#9296a4]">{user?.roles?.[0]?.name || 'Sin rol'}</span></span><FiChevronDown size={15} className={`ml-1 text-[#a1a4af] transition ${open ? 'rotate-180' : ''}`} /></button>{open && <div role="menu" className="absolute right-0 top-12 z-50 w-56 overflow-hidden rounded-xl border border-[#34303d] bg-[#15121d] p-1.5 shadow-[0_18px_45px_rgba(0,0,0,.5)]"><div className="border-b border-[#292936] px-3 py-2.5"><p className="text-xs font-medium text-white">{user?.name || 'Velvet Admin'}</p><p className="mt-1 truncate text-[11px] text-[#858a99]">{user?.email}</p></div><Link href="/profile" role="menuitem" className="mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-xs text-[#d5d2dd] hover:bg-[#2b1735] hover:text-white"><FiUser size={15} />Visitar perfil</Link><Link href="/logout" method="post" as="button" role="menuitem" className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-xs text-[#d5d2dd] hover:bg-[#2b1735] hover:text-white"><FiLogOut size={15} />Cerrar sesión</Link></div>}</div>;
}

function DashboardSkeleton() {
    const block = (className = '') => <span className={`block animate-pulse rounded-md bg-[#1a1d28] ${className}`} />;

    return <div className="admin-skeleton" aria-busy="true" aria-label="Cargando módulo"><div className="flex flex-col justify-between gap-5 md:flex-row md:items-end"><div className="space-y-3">{block('h-3 w-64')}{block('h-10 w-80')}</div><div className="flex gap-3">{block('h-8 w-24')}{block('h-10 w-36')}</div></div><div className="mt-7 flex items-center justify-between border-b border-[#20232d] pb-5">{block('h-3 w-40')}{block('h-9 w-56')}</div><section className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">{Array.from({ length: 6 }).map((_, index) => <div key={index} className="rounded-lg border border-[#242833] bg-[#10121a] p-4">{block('h-9 w-9')}{block('mt-5 h-3 w-24')}{block('mt-2 h-8 w-16')}{block('mt-4 h-2 w-full')}</div>)}</section><section className="mt-5 grid gap-4 xl:grid-cols-[1.55fr_.85fr]"><div className="rounded-lg border border-[#242833] bg-[#10121a] p-5">{block('h-4 w-48')}{block('mt-3 h-3 w-72')}{block('mt-8 h-56 w-full')}</div><div className="rounded-lg border border-[#242833] bg-[#10121a] p-5">{block('h-4 w-40')}{block('mt-3 h-3 w-56')}{block('mx-auto mt-8 h-40 w-40 rounded-full')}</div></section><section className="mt-4 grid gap-4 xl:grid-cols-2"><div className="rounded-lg border border-[#242833] bg-[#10121a] p-5">{block('h-4 w-36')}{block('mt-6 h-44 w-full')}</div><div className="rounded-lg border border-[#242833] bg-[#10121a] p-5">{block('h-4 w-32')}{Array.from({ length: 4 }).map((_, index) => <div key={index} className="mt-5 flex items-center gap-3">{block('h-8 w-8 rounded-full')}{block('h-3 flex-1')}{block('h-3 w-10')}</div>)}</div></section></div>;
}

export default function Layout({ children }) {
    const page = usePage();
    const [open, setOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const [notifications, setNotifications] = useState(() => (page?.props?.realtime?.notifications || []).filter((item) => !item.read_at).length);
    const [notificationItems, setNotificationItems] = useState(() => page?.props?.realtime?.notifications || []);
    const [notificationSearch, setNotificationSearch] = useState('');
    const [relativeNow, setRelativeNow] = useState(Date.now());
    const [counters, setCounters] = useState(() => page?.props?.realtime?.counters || {});
    const menuRef = useRef(null);
    const user = page.props.auth?.user;
    const { can } = useAuthorization();

    useEffect(() => {
        const headerActions = document.querySelector('header > div:last-child');
        const shouldShow = user?.roles?.some((role) => ['model', 'monitor'].includes(role.slug)) && page.props.operationalAccess?.training_mode;
        if (!headerActions || !shouldShow || headerActions.querySelector('[data-training-mode-badge]')) return;

        const badge = document.createElement('span');
        badge.dataset.trainingModeBadge = 'true';
        badge.className = 'training-mode-badge hidden items-center gap-2 rounded-full border border-[#b8863b] bg-[#342515]/80 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-[.12em] text-[#ffe1a1] sm:inline-flex';
        badge.innerHTML = '<span class="training-mode-dot" aria-hidden="true"></span><span>Modo pruebas</span>';
        headerActions.prepend(badge);
        return () => badge.remove();
    }, [page.props.operationalAccess?.training_mode, user?.roles]);

    useEffect(() => {
        const interval = window.setInterval(() => setRelativeNow(Date.now()), 15000);
        return () => window.clearInterval(interval);
    }, []);

    useEffect(() => {
        const closeMenu = (event) => { if (menuRef.current && !menuRef.current.contains(event.target)) setProfileOpen(false); };
        document.addEventListener('mousedown', closeMenu);
        return () => document.removeEventListener('mousedown', closeMenu);
    }, []);

    useEffect(() => setCounters(page.props.realtime?.counters || {}), [page.props.realtime]);

    useEffect(() => {
        const sidebars = Array.from(document.querySelectorAll('nav.sidebar-scroll'));
        const scrollKey = (index) => `velvet-sidebar-scroll:${user?.id ?? 'guest'}:${index}`;

        const persistScroll = (sidebar, index) => {
            try {
                window.sessionStorage.setItem(scrollKey(index), String(sidebar.scrollTop));
            } catch {
                // La navegación sigue funcionando si el navegador bloquea sessionStorage.
            }
        };

        const revealActiveItem = (sidebar, index) => {
            try {
                const savedPosition = Number(window.sessionStorage.getItem(scrollKey(index)));

                if (Number.isFinite(savedPosition)) {
                    sidebar.scrollTop = savedPosition;
                }
            } catch {
                // Se conserva la posición inicial cuando el almacenamiento no está disponible.
            }

            const activeItem = Array.from(sidebar.querySelectorAll('a')).find((link) =>
                link.className.includes('bg-[#55166e]'),
            );

            if (!activeItem) return;

            const sidebarBounds = sidebar.getBoundingClientRect();
            const activeBounds = activeItem.getBoundingClientRect();
            const safeArea = 12;

            if (activeBounds.top < sidebarBounds.top + safeArea) {
                sidebar.scrollTop += activeBounds.top - sidebarBounds.top - safeArea;
            } else if (activeBounds.bottom > sidebarBounds.bottom - safeArea) {
                sidebar.scrollTop += activeBounds.bottom - sidebarBounds.bottom + safeArea;
            }

            persistScroll(sidebar, index);
        };

        const animationFrame = window.requestAnimationFrame(() => {
            sidebars.forEach(revealActiveItem);
        });
        const listeners = sidebars.map((sidebar, index) => {
            const onScroll = () => persistScroll(sidebar, index);
            sidebar.addEventListener('scroll', onScroll, { passive: true });

            return { sidebar, onScroll };
        });

        return () => {
            window.cancelAnimationFrame(animationFrame);
            listeners.forEach(({ sidebar, onScroll }) => sidebar.removeEventListener('scroll', onScroll));
        };
    }, [page.url, user?.id]);

    useEffect(() => {
        const items = page.props.realtime?.notifications || [];
        setNotificationItems(items);
        setNotifications(items.filter((item) => !item.read_at).length);
    }, [page.props.realtime?.notifications]);

    useEffect(() => subscribeToRealtime((data, eventName) => {
        const portalCandidateId = Number(page.props.auth?.user?.candidate_id || 0);
        if (portalCandidateId && Number(data?.candidate_id || 0) === portalCandidateId && ['candidate.access_status_changed', 'candidate.status_changed'].includes(eventName)) {
            router.reload({ preserveScroll: true, preserveState: false });
            return;
        }
        if (data?.counters) setCounters(data.counters);
        const notification = data?.notification;
        if (!notification) return;
        setNotifications((current) => Math.min(current + 1, 99));
        setNotificationItems((current) => [{
            id: `${Date.now()}-${data.lead_id || data.candidate_id || 'system'}`,
            title: notification.title || 'Nueva notificación',
            description: notification.description || '',
            data,
            created_at: new Date().toISOString(),
        }, ...current].slice(0, 25));
        fetch('/admin/notifications', { headers: { Accept: 'application/json' } })
            .then((response) => response.ok ? response.json() : null)
            .then((result) => {
                if (!result?.notifications) return;
                setNotificationItems(result.notifications);
                setNotifications(result.notifications.filter((item) => !item.read_at).length);
            })
            .catch(() => {});
        toast(notification.description || notification.title, { icon: '⚡', duration: 5000 });
    }), []);

    useEffect(() => {
        const bell = document.querySelector('header button.relative');
        if (!bell) return;
        const staticBadge = bell.querySelector('span:not([data-realtime-badge])');
        if (staticBadge) staticBadge.hidden = true;
        let badge = bell.querySelector('[data-realtime-badge]');
        if (!badge) {
            badge = document.createElement('span');
            badge.dataset.realtimeBadge = 'true';
            badge.className = 'absolute -right-2 -top-2 grid h-4 min-w-4 place-items-center rounded-full bg-[#a92ad8] px-1 text-[9px] text-white';
            bell.appendChild(badge);
        }
        badge.textContent = notifications > 99 ? '99+' : String(notifications);
        badge.hidden = notifications === 0;
        bell.setAttribute('aria-label', notifications ? `${notifications} notificaciones nuevas` : 'Sin notificaciones nuevas');
        const clearNotifications = () => {
            setNotifications(0);
            fetch('/admin/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' },
            }).catch(() => {});
        };
        bell.addEventListener('click', clearNotifications);
        return () => bell.removeEventListener('click', clearNotifications);
    }, [notifications]);

    useEffect(() => {
        const bell = document.querySelector('header button.relative');
        if (!bell || document.querySelector('[data-notification-panel]')) return;
        const panel = document.createElement('section');
        panel.dataset.notificationPanel = 'true';
        panel.hidden = true;
        panel.className = 'notification-dropdown fixed z-[70] w-[360px] overflow-visible rounded-xl border border-[#34303d] bg-[#15121d] shadow-[0_18px_45px_rgba(0,0,0,.55)]';
        document.body.appendChild(panel);
        const position = () => {
            const bellRect = bell.getBoundingClientRect();
            const panelWidth = 360;
            const viewportPadding = 8;
            const bellCenter = bellRect.left + (bellRect.width / 2);
            const left = Math.max(viewportPadding, Math.min(bellCenter - (panelWidth / 2), window.innerWidth - panelWidth - viewportPadding));
            const arrowLeft = Math.max(18, Math.min(panelWidth - 18, bellCenter - left));
            panel.style.left = `${left}px`;
            panel.style.top = `${bellRect.bottom + 10}px`;
            panel.style.setProperty('--notification-origin-x', `${arrowLeft}px`);
        };
        const toggle = (event) => {
            event.stopPropagation();
            if (panel.hidden) position();
            panel.hidden = !panel.hidden;
        };
        const close = (event) => { if (!panel.contains(event.target) && event.target !== bell) panel.hidden = true; };
        bell.addEventListener('click', toggle);
        document.addEventListener('mousedown', close);
        window.addEventListener('resize', position);
        return () => {
            bell.removeEventListener('click', toggle);
            document.removeEventListener('mousedown', close);
            window.removeEventListener('resize', position);
            panel.remove();
        };
    }, []);

    useEffect(() => {
        const panel = document.querySelector('[data-notification-panel]');
        if (!panel) return;
        panel.replaceChildren();
        const header = document.createElement('div');
        header.className = 'flex items-center justify-between border-b border-[#292936] px-4 py-3';
        const title = document.createElement('p');
        title.className = 'text-sm font-semibold text-white';
        title.textContent = 'Notificaciones';
        const count = document.createElement('span');
        count.className = 'rounded-full bg-[#3b2046] px-2 py-0.5 text-[10px] text-[#e6a0f2]';
        count.textContent = notificationItems.length ? String(notificationItems.length) : '0';
        header.append(title, count);
        panel.appendChild(header);
        const search = document.createElement('input');
        search.type = 'search';
        search.value = notificationSearch;
        search.placeholder = 'Buscar notificaciones...';
        search.className = 'mx-4 mt-3 w-[calc(100%-2rem)] rounded-lg border border-[#343044] bg-[#0f0e16] px-3 py-2 text-xs text-white outline-none placeholder:text-[#777d8f] focus:border-[#a92ad8]';
        search.addEventListener('input', (event) => setNotificationSearch(event.target.value));
        panel.appendChild(search);

        const query = notificationSearch.trim().toLowerCase();
        const filteredItems = notificationItems.filter((item) => {
            const data = item.data || {};
            return [item.title, item.description, item.channel, data.code, data.lead_id, data.candidate_id]
                .filter(Boolean)
                .join(' ')
                .toLowerCase()
                .includes(query);
        });

        if (!filteredItems.length) {
            const empty = document.createElement('p');
            empty.className = 'px-4 py-8 text-center text-xs text-[#858a99]';
            empty.textContent = notificationItems.length ? 'No hay resultados para esa búsqueda.' : 'No hay notificaciones nuevas.';
            panel.appendChild(empty);
            return;
        }
        const list = document.createElement('div');
        list.className = 'notification-dropdown-list max-h-[360px] overflow-y-auto';
        filteredItems.forEach((item) => {
            const row = document.createElement('article');
            row.className = 'flex items-center gap-3 border-b border-[#292936] px-4 py-3 last:border-b-0';
            const content = document.createElement('div');
            content.className = 'min-w-0 flex-1';
            const itemTitle = document.createElement('p');
            itemTitle.className = 'text-xs font-medium text-white';
            itemTitle.textContent = item.title;
            const description = document.createElement('p');
            description.className = 'mt-1 text-[11px] leading-4 text-[#a5a7b4]';
            description.textContent = item.description;
            const reference = item.data?.code || (item.data?.lead_id ? `Lead #${item.data.lead_id}` : null);
            if (reference) {
                const referenceText = document.createElement('p');
                referenceText.className = 'mt-1 text-[10px] font-medium text-[#d56bea]';
                referenceText.textContent = reference;
                content.append(itemTitle, description, referenceText);
            } else {
                content.append(itemTitle, description);
            }
            const timestamp = document.createElement('p');
            timestamp.className = 'mt-1 text-[10px] text-[#777d8f]';
            timestamp.textContent = formatRelativeTime(item.created_at, relativeNow);
            timestamp.title = formatFriendlyDateTime(item.created_at);
            content.appendChild(timestamp);
            const action = document.createElement('a');
            action.href = notificationHref(item);
            action.className = 'grid h-7 w-7 shrink-0 place-items-center rounded-md border border-[#713080] text-sm text-[#e5a1f2] transition hover:bg-[#713080] hover:text-white';
            action.title = 'Abrir módulo relacionado';
            action.setAttribute('aria-label', 'Abrir módulo relacionado');
            action.textContent = '↗';
            action.addEventListener('click', (event) => {
                event.preventDefault();
                panel.hidden = true;
                router.visit(action.href);
            });
            row.append(content, action);
            list.appendChild(row);
        });
        const history = document.createElement('a');
        history.href = '/admin/notifications/history';
        history.className = 'block border-t border-[#292936] px-4 py-3 text-center text-[11px] font-medium text-[#d56bea] hover:bg-[#24152d]';
        history.textContent = 'Ver historial de notificaciones';
        history.addEventListener('click', (event) => {
            event.preventDefault();
            panel.hidden = true;
            router.visit(history.href);
        });
        panel.appendChild(history);
        panel.insertBefore(list, history);
        panel.querySelector('input')?.focus();
    }, [notificationItems, notificationSearch, relativeNow]);

    useEffect(() => {
        const items = { '/admin/leads': counters.leads, '/admin/candidates': counters.candidates, '/admin/interviews': counters.interviews };
        Object.entries(items).forEach(([href, count]) => {
            const link = document.querySelector(`aside a[href^="${href}"]`);
            if (!link) return;
            let badge = link.querySelector('[data-sidebar-counter]');
            if (!badge) {
                badge = document.createElement('span');
                badge.dataset.sidebarCounter = 'true';
                badge.className = 'ml-auto min-w-4 rounded-full bg-[#a92ad8] px-1.5 py-0.5 text-center text-[9px] font-semibold text-white';
                link.appendChild(badge);
            }
            badge.textContent = count > 99 ? '99+' : String(count || 0);
            badge.hidden = !count;
        });
    }, [counters]);

    return <div className="min-h-screen bg-[#090a10] text-[#f7f1fb]"><div className={`fixed inset-0 z-40 bg-black/70 transition lg:hidden ${open ? 'visible opacity-100' : 'invisible opacity-0'}`} onClick={() => setOpen(false)} /><div className={`fixed inset-y-0 left-0 z-50 transition-transform duration-300 lg:translate-x-0 ${open ? 'translate-x-0' : '-translate-x-full'}`}><div className="absolute right-3 top-3 lg:hidden"><button type="button" onClick={() => setOpen(false)} className="rounded-lg p-2 text-gray-400 hover:bg-white/10 hover:text-white"><FiX /></button></div><Sidebar onClose={() => setOpen(false)} url={page.url} user={user} can={can} /></div><div className="lg:pl-[224px]"><header className="sticky top-0 z-30 flex h-[66px] items-center justify-between border-b border-[#1f222d] bg-[#090a10]/95 px-5 backdrop-blur-xl lg:px-6"><div className="flex items-center gap-4"><button type="button" onClick={() => setOpen(true)} className="rounded-lg p-2 text-gray-300 hover:bg-white/10 lg:hidden"><FiMenu /></button><div className="hidden h-10 w-[480px] items-center gap-3 rounded-lg border border-[#262a36] bg-[#10121a] px-3 text-sm text-[#868b9b] md:flex"><FiSearch size={17} /><span>Buscar candidatos, leads, entrevistas…</span><kbd className="ml-auto rounded border border-[#2f3340] px-2 py-0.5 text-[10px] text-[#adb0bd]">⌘ K</kbd></div></div><div className="flex items-center gap-4"><button type="button" className="hidden text-[#c8cad3] hover:text-white sm:block"><FiSun size={18} /></button><button type="button" className="relative text-[#c8cad3] hover:text-white"><FiBell size={19} /><span className="absolute -right-2 -top-2 grid h-4 min-w-4 place-items-center rounded-full bg-[#a92ad8] px-1 text-[9px] text-white">3</span></button><ProfileMenu user={user} open={profileOpen} onToggle={() => setProfileOpen((value) => !value)} menuRef={menuRef} /></div></header><main><div className="admin-page-content">{children}</div></main></div></div>;
}

