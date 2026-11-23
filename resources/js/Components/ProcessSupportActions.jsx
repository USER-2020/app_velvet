import { FiMessageCircle, FiMail } from 'react-icons/fi';

export default function ProcessSupportActions({ whatsappUrl = null }) {
    return <div className="mt-8 border-t border-[#292d39] pt-6">
        <p className="text-sm leading-6 text-[#aaa3b0]">¿Necesitas ayuda con tu proceso?</p>
        <div className="mt-4 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="mailto:info_soporte@thevelvetstudio.co" className="inline-flex items-center justify-center gap-2 rounded-lg border border-[#80508c] px-4 py-3 text-xs font-medium text-[#e5a1f2] transition hover:bg-[#321642]"><FiMail />Escribir a soporte</a>
            {whatsappUrl && <a href={whatsappUrl} target="_blank" rel="noreferrer" className="inline-flex items-center justify-center gap-2 rounded-lg border border-[#286d60] bg-[#12372e] px-4 py-3 text-xs font-medium text-[#9af2cb] transition hover:bg-[#185044]"><FiMessageCircle />Escribir por WhatsApp</a>}
        </div>
    </div>;
}
