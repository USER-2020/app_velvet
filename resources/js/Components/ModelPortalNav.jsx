import { Link, usePage } from '@inertiajs/react';
import { FiActivity, FiCalendar, FiClock, FiFileText, FiGlobe, FiGrid, FiUser, FiUsers } from 'react-icons/fi';
import { useAuthorization } from '@/lib/authorization';

const links = [
    ['model.dashboard', 'Resumen', FiGrid, 'rooms.view_own'],
    ['model.time', 'Mi tiempo', FiClock, 'metrics.view_own'],
    ['model.activity', 'Bitácora', FiActivity, 'profile.view_own'],
    ['model.agenda', 'Agenda', FiCalendar, 'profile.view_own'],
    ['model.requests', 'Solicitudes', FiFileText, 'requests.view_own'],
    ['model.platform-requests', 'Plataformas', FiGlobe, 'requests.view_own'],
    ['model.rooms', 'Rooms', FiUsers, 'rooms.view_own'],
    ['model.profile', 'Perfil', FiUser, 'profile.view_own'],
];

export default function ModelPortalNav() {
    const { url } = usePage();
    const { can } = useAuthorization();
    const currentPath = url.split('?')[0];

    return <nav aria-label="Navegación del portal de modelo" data-tour="model-portal-nav" className="model-portal-nav">
        <p className="model-portal-nav__label">Tu espacio</p>
        <div className="model-portal-nav__items">
            {links.filter(([, , , permission]) => can(permission)).map(([name, label, Icon]) => {
                const href = route(name);
                const active = currentPath === new URL(href, window.location.origin).pathname;

                return <Link key={name} href={href} aria-current={active ? 'page' : undefined} className={`model-portal-nav__link${active ? ' is-active' : ''}`}>
                    <Icon aria-hidden="true" />
                    <span>{label}</span>
                </Link>;
            })}
        </div>
    </nav>;
}
