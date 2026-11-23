import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { FiArrowRight, FiChevronRight, FiLayers, FiPlay, FiSearch } from 'react-icons/fi';
import './functional-guides.css';

function GuideDetail({ guide }) {
    const [showPreview, setShowPreview] = useState(false);

    return <article className="functional-guides__detail" aria-labelledby="functional-guide-title">
        <p className="functional-guides__muted text-xs">{guide.group}</p>
        <h3 id="functional-guide-title" className="mt-1 font-editorial text-2xl">{guide.title}</h3>
        <ol className="mt-5 list-decimal space-y-4 pl-6">
            {guide.steps.map((step) => <li key={step.title} className="pl-1 marker:font-semibold">
                <h4 className="text-sm font-semibold">{step.title}</h4>
                <p className="functional-guides__muted mt-1 text-sm leading-6">{step.description}</p>
            </li>)}
        </ol>
        <div className="mt-6 flex flex-wrap gap-2">
            <button type="button" className="functional-guides__action" aria-expanded={showPreview} onClick={() => setShowPreview(!showPreview)}>
                <FiPlay aria-hidden="true" /> {showPreview ? 'Ocultar demostración' : guide.video_url ? 'Ver video' : 'Ver demostración'}
            </button>
            {guide.url && <Link href={guide.url} className="functional-guides__action">
                Abrir módulo <FiArrowRight aria-hidden="true" />
            </Link>}
        </div>
        {showPreview && (guide.video_url ? <div className="mt-4">
            <video controls playsInline preload="metadata" className="functional-guides__video" aria-label={`Video de ${guide.title}`}>
                <source src={guide.video_url} type="video/mp4" />
                <a href={guide.video_url}>Abrir video MP4</a>
            </video>
            <p className="functional-guides__muted mt-2 text-xs">Simulación con datos ficticios. Las acciones disponibles en el dashboard dependen de tu rol.</p>
        </div> : <div className="mt-4">
            <iframe
                className="functional-guides__preview"
                title={`Demostración paso a paso de ${guide.title}`}
                sandbox="allow-scripts"
                src={guide.preview_url}
            />
            <a href={`${guide.preview_url}?download=1`} className="functional-guides__download mt-2 inline-block text-xs underline">
                Descargar composición HyperFrames
            </a>
        </div>)}
    </article>;
}

const normalize = (text) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');

export default function FunctionalGuides({ guides, profiles }) {
    const [group, setGroup] = useState('');
    const [search, setSearch] = useState('');
    const [selectedKey, setSelectedKey] = useState('');
    const groups = [...new Set(guides.map((guide) => guide.group))];
    const term = normalize(search.trim());
    const matches = guides.filter((guide) => (!group || guide.group === group)
        && normalize([guide.title, guide.group, ...guide.steps.map((step) => step.title)].join(' ')).includes(term));
    const selected = matches.find((guide) => guide.key === selectedKey) || matches[0];

    return <section data-tour="documentation-modules" className="functional-guides mt-8" aria-labelledby="functional-guides-title">
        <div className="flex items-start gap-3">
            <span className="functional-guides__icon grid h-10 w-10 shrink-0 place-items-center rounded-xl"><FiLayers aria-hidden="true" /></span>
            <div>
                <h2 id="functional-guides-title" className="font-editorial text-2xl">Manual funcional por módulo</h2>
                <p className="functional-guides__muted mt-1 text-sm">Encuentra los flujos disponibles para tu cuenta y sigue cada tarea paso a paso.</p>
            </div>
        </div>
        {profiles.length > 0 && <ul className="functional-guides__profiles mt-4 space-y-2">
            {profiles.map((profile) => <li key={profile.name} className="text-sm leading-6">
                <strong>{profile.name}.</strong> <span className="functional-guides__muted">{profile.summary}</span>
            </li>)}
        </ul>}
        <div data-tour="documentation-guide-filters" className="mt-4 grid gap-3 sm:grid-cols-2">
            <label className="text-sm font-medium">
                Buscar un flujo
                <span className="relative mt-2 block">
                    <FiSearch aria-hidden="true" className="functional-guides__muted pointer-events-none absolute left-3 top-3" />
                    <input type="search" className="functional-guides__field pl-9" value={search} placeholder="Ej. Entrevistas" onChange={(event) => setSearch(event.target.value)} />
                </span>
            </label>
            <label className="text-sm font-medium">
                Área
                <select className="functional-guides__field mt-2 px-3" value={group} onChange={(event) => setGroup(event.target.value)}>
                    <option value="">Todas las áreas</option>
                    {groups.map((name) => <option key={name}>{name}</option>)}
                </select>
            </label>
        </div>
        {selected ? <div className="functional-guides__grid mt-4">
            <nav className="functional-guides__list" aria-label="Flujos disponibles">
                {matches.map((guide) => <button
                    key={guide.key}
                    type="button"
                    aria-current={selected.key === guide.key || undefined}
                    onClick={() => setSelectedKey(guide.key)}
                >
                    <span>{guide.title}</span><FiChevronRight aria-hidden="true" className="shrink-0" />
                </button>)}
            </nav>
            <div data-tour="documentation-guide-detail" className="min-w-0">
                <GuideDetail key={selected.key} guide={selected} />
            </div>
        </div> : <p role="status" className="functional-guides__muted mt-4 text-sm">No encontramos flujos con esos filtros.</p>}
    </section>;
}
