import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { FiArrowLeft, FiChevronLeft, FiChevronRight, FiExternalLink, FiHeart, FiTarget, FiUsers } from 'react-icons/fi';
import Layout from '@/Pages/Admin/Recruitment/Layout';
import TeamPlanning from '@/Pages/Admin/ModelPlanning/TeamPlanning';

const money = (value, currency = 'COP') => value == null || value === ''
    ? 'Sin presupuesto'
    : new Intl.NumberFormat('es-CO', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value));

function WishlistCard({ item }) {
    return (
        <article className="flex h-[340px] w-full flex-col overflow-hidden rounded-xl border border-[#292d39] bg-[#11131c]">
            <div className="h-32 shrink-0 bg-gradient-to-br from-[#321344] to-[#11131c]">
                {item.image_url && <img src={item.image_url} alt="" className="h-full w-full object-cover opacity-85" />}
            </div>
            <div className="flex min-h-0 flex-1 flex-col p-4">
                <p className="text-[10px] uppercase tracking-[.18em] text-[#d56bea]">{item.category || 'Wishlist'} · prioridad {item.priority}</p>
                <h3 className="mt-2 text-sm font-medium text-white">{item.title}</h3>
                <p className="mt-2 line-clamp-3 text-xs leading-5 text-[#9993a3]">{item.description || 'Sin descripción.'}</p>
                <div className="mt-auto flex items-center justify-between border-t border-[#292d39] pt-3">
                    <span className="text-xs text-[#e5a5f2]">{money(item.estimated_price, item.currency)}</span>
                    {item.url && <a href={item.url} target="_blank" rel="noreferrer" className="text-[#d56bea]" title="Abrir enlace"><FiExternalLink /></a>}
                </div>
            </div>
        </article>
    );
}

function WishlistCarousel({ items }) {
    const [activeIndex, setActiveIndex] = useState(0);
    const previous = () => setActiveIndex((index) => (index - 1 + items.length) % items.length);
    const next = () => setActiveIndex((index) => (index + 1) % items.length);

    return (
        <div className="relative w-full max-w-[360px]" style={{ width: '100%', maxWidth: '360px' }}>
            <div className="overflow-hidden rounded-xl">
                <div className="flex transition-transform duration-300 ease-out" style={{ transform: `translateX(-${activeIndex * 100}%)` }}>
                    {items.map((item) => <div key={item.id} className="w-full shrink-0"><WishlistCard item={item} /></div>)}
                </div>
            </div>
            {items.length > 1 && <>
                <button type="button" onClick={previous} aria-label="Deseo anterior" className="absolute left-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full border border-[#80508c] bg-[#11131c]/90 text-[#e5a5f2] shadow-lg transition hover:bg-[#321344]"><FiChevronLeft /></button>
                <button type="button" onClick={next} aria-label="Siguiente deseo" className="absolute right-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full border border-[#80508c] bg-[#11131c]/90 text-[#e5a5f2] shadow-lg transition hover:bg-[#321344]"><FiChevronRight /></button>
                <div className="mt-3 flex items-center justify-center gap-2">
                    {items.map((item, index) => <button type="button" key={item.id} onClick={() => setActiveIndex(index)} aria-label={`Ver deseo ${index + 1}`} className={`h-1.5 rounded-full transition-all ${index === activeIndex ? 'w-8 bg-[#d56bea]' : 'w-2 bg-[#51445b]'}`} />)}
                    <span className="ml-2 text-[11px] text-[#858a99]">{activeIndex + 1} / {items.length}</span>
                </div>
            </>}
        </div>
    );
}

function AllWishlists({ models, allWishlists, baseUrl }) {
    const wishCount = allWishlists.reduce((total, group) => total + group.wishlist.length, 0);

    return (
        <>
            <Head title="Wishlist y estrategias" />
            <Layout>
                <div className="admin-page-content">
                    <header className="border-b border-[#20232d] pb-7">
                        <p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Acompañamiento del equipo</p>
                        <h1 className="mt-2 font-editorial text-4xl text-white sm:text-5xl">Wishlist y estrategias</h1>
                        <p className="mt-2 max-w-3xl text-sm text-[#969baa]">Consulta los deseos compartidos de las modelos dentro de tu alcance y conviértelos en estrategias de acompañamiento.</p>
                    </header>
                    <section data-tour="planning-summary" className="mt-7 grid gap-3 sm:grid-cols-3">
                        <div className="rounded-xl border border-[#492d53] bg-[#171322] p-5"><FiHeart className="text-[#ed8ef9]" /><p className="mt-4 text-3xl text-white">{wishCount}</p><p className="mt-1 text-xs text-[#aaa4b3]">Deseos compartidos</p></div>
                        <div className="rounded-xl border border-[#292d39] bg-[#11131c] p-5"><FiUsers className="text-[#c994ed]" /><p className="mt-4 text-3xl text-white">{allWishlists.length}</p><p className="mt-1 text-xs text-[#aaa4b3]">Modelos con wishlist</p></div>
                        <div className="rounded-xl border border-[#292d39] bg-[#11131c] p-5"><FiTarget className="text-[#c994ed]" /><p className="mt-4 text-3xl text-white">{models.length}</p><p className="mt-1 text-xs text-[#aaa4b3]">Modelos en tu alcance</p></div>
                    </section>
                    <section data-tour="planning-all-wishlists" className="mt-8">
                        <div className="mb-4 flex items-center justify-between gap-4"><h2 className="font-editorial text-2xl text-white">Todas las wishlists</h2><span className="text-xs text-[#858a99]">{wishCount} registros</span></div>
                        {allWishlists.length ? <div className="space-y-6">{allWishlists.map((group) => <section key={group.model.id} className="w-full rounded-2xl border border-[#292d39] bg-[#0d0f17] p-5"><div className="mb-4 flex flex-wrap items-center justify-between gap-3"><div className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-full bg-[#3b2046] text-[#e5a5f2]"><FiUsers /></span><div><h3 className="text-base font-medium text-white">{group.model.name}</h3><p className="mt-1 text-[11px] text-[#858a99]">{group.model.code} · {group.wishlist.length} deseos</p></div></div><Link href={`${baseUrl}?model=${group.model.id}`} className="rounded-lg border border-[#80508c] px-3 py-2 text-xs text-[#e5a5f2] transition hover:bg-[#321344]">Ver detalle y crear estrategia</Link></div><WishlistCarousel items={group.wishlist} /></section>)}</div> : <div className="rounded-2xl border border-dashed border-[#3a3044] p-10 text-center"><FiHeart className="mx-auto text-3xl text-[#e5a5f2]" /><p className="mt-3 text-sm text-white">Aún no hay wishlists compartidas</p><p className="mt-1 text-xs text-[#858a99]">Cuando una modelo comparta un deseo, aparecerá aquí.</p></div>}
                    </section>
                </div>
            </Layout>
        </>
    );
}

export default function Index({ models = [], allWishlists = [], selectedModel, canManageStrategy = false }) {
    const baseUrl = usePage().url.split('?')[0];
    if (!selectedModel) return <AllWishlists models={models} allWishlists={allWishlists} baseUrl={baseUrl} />;
    return <><Head title={`Wishlist y estrategias · ${selectedModel.model.name}`} /><div className="relative"><Link href={baseUrl} className="fixed left-5 top-5 z-40 inline-flex items-center gap-2 rounded-lg border border-[#343044] bg-[#11131c] px-3 py-2 text-xs text-[#d7d2dc]"><FiArrowLeft />Volver a todas</Link><TeamPlanning {...selectedModel} models={models} canManageStrategy={canManageStrategy} /></div></>;
}
