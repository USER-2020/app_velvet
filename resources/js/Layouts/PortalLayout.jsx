import { Link, usePage } from '@inertiajs/react';
import { FiLogOut, FiMoon } from 'react-icons/fi';
import PortalContractDocuments from '@/Components/PortalContractDocuments';

export default function PortalLayout({ children, eyebrow, title, subtitle, navigation = null }) {
    const page = usePage();
    const user = page.props.auth?.user;
    const documents = page.props.contractDocuments;

    return <div data-portal-shell className="min-h-screen bg-[#08090f] text-[#f5f0f7]">
        <header data-portal-shell-header className="border-b border-[#252733] bg-[#0c0d14]/95">
            <div className="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-5 py-4 lg:px-10">
                <Link href="/dashboard" className="flex items-center gap-3">
                    <span className="font-editorial text-2xl text-white">THE VELVET</span>
                    <span className="hidden border-l border-[#343044] pl-3 text-[10px] uppercase tracking-[.2em] text-[#d56bea] sm:inline">Portal</span>
                </Link>
                <div className="flex items-center gap-3 text-xs text-[#a7a1b0]">
                    <FiMoon className="text-[#d56bea]" />
                    <span className="hidden sm:inline">{user?.name}</span>
                    <Link href={route('logout')} method="post" as="button" className="inline-flex items-center gap-2 rounded-lg border border-[#343044] px-3 py-2 hover:border-[#a92ad8] hover:text-white"><FiLogOut size={13} />Salir</Link>
                </div>
            </div>
        </header>
        <main className="mx-auto max-w-[1440px] px-5 py-10 lg:px-10">
            <div className={`portal-layout${navigation ? ' portal-layout--with-navigation' : ''}`}>
                {navigation && <aside className="portal-layout__sidebar">{navigation}</aside>}
                <div className="portal-layout__content">
                    <p className="text-[10px] uppercase tracking-[.3em] text-[#d56bea]">{eyebrow}</p>
                    <h1 className="mt-3 font-editorial text-4xl text-white sm:text-5xl">{title}</h1>
                    <p className="mt-2 max-w-3xl text-sm text-[#969baa]">{subtitle}</p>
                    {children}
                    {Array.isArray(documents) && <PortalContractDocuments documents={documents} />}
                </div>
            </div>
        </main>
    </div>;
}
