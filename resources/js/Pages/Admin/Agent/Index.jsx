import { Head } from '@inertiajs/react';
import { FiDatabase, FiInfo, FiShield } from 'react-icons/fi';
import Layout from '../Recruitment/Layout';

export default function Index() {
    return <>
        <Head title="VELVET Agent" />
        <Layout>
            <div className="mx-auto max-w-[1100px] px-5 py-8 lg:px-6 xl:px-8">
                <header className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                    <div>
                        <p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Integraciones</p>
                        <h1 className="mt-2 font-editorial text-4xl tracking-[-.04em] text-white sm:text-5xl">VELVET Agent</h1>
                        <p className="mt-2 max-w-3xl text-sm leading-6 text-[#969baa]">Integración técnica para conectar las estaciones Windows con Velvet Master.</p>
                    </div>
                    <span className="inline-flex w-fit items-center gap-2 rounded-full border border-[#755080] bg-[#2b1735] px-4 py-2 text-xs font-medium text-[#e7a8f1]"><FiInfo /> En construcción</span>
                </header>

                <section data-tour="agent-construction" className="mt-8 rounded-2xl border border-[#755080] bg-gradient-to-br from-[#1c1224] via-[#11131c] to-[#11131c] p-8 shadow-[0_0_45px_rgba(173,42,215,.08)] sm:p-12">
                    <div className="mx-auto max-w-2xl text-center">
                        <span className="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-[#2b1735] text-3xl text-[#df9bec]"><FiDatabase /></span>
                        <h2 className="mt-6 font-editorial text-3xl tracking-[-.03em] text-white sm:text-4xl">Integración Agent en construcción</h2>
                        <p className="mt-4 text-sm leading-7 text-[#b2adba]">Estamos preparando la conexión segura entre cada estación Windows y Velvet Master: enrolamiento, OAuth, heartbeat, telemetría y estado de OBS.</p>
                    </div>

                    <div className="mx-auto mt-8 grid max-w-3xl gap-3 sm:grid-cols-3">
                        <div className="rounded-xl border border-[#292d39] bg-[#0d0f16]/70 p-4 text-center"><p className="text-sm font-medium text-white">Enrolamiento</p><p className="mt-1 text-xs leading-5 text-[#858a99]">Credencial de estación</p></div>
                        <div className="rounded-xl border border-[#292d39] bg-[#0d0f16]/70 p-4 text-center"><p className="text-sm font-medium text-white">Conectividad</p><p className="mt-1 text-xs leading-5 text-[#858a99]">OAuth y heartbeat</p></div>
                        <div className="rounded-xl border border-[#292d39] bg-[#0d0f16]/70 p-4 text-center"><p className="text-sm font-medium text-white">OBS</p><p className="mt-1 text-xs leading-5 text-[#858a99]">Estado y telemetría</p></div>
                    </div>

                    <div className="mx-auto mt-8 flex max-w-3xl items-start gap-3 rounded-xl border border-[#343044] bg-[#0d0f16]/70 p-4 text-left"><FiShield className="mt-0.5 shrink-0 text-lg text-[#9af2cb]" /><p className="text-xs leading-5 text-[#a8a3b0]">El módulo técnico permanece temporalmente bloqueado para evitar enrolar estaciones mientras terminamos las validaciones de seguridad y compatibilidad con el Agent de Windows.</p></div>
                </section>
            </div>
        </Layout>
    </>;
}
