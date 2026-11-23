import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import { FiCheck, FiCopy, FiCpu, FiFileText, FiLoader, FiMaximize2, FiMinimize2, FiPlus, FiSend, FiX } from 'react-icons/fi';
import { useAuthorization } from '@/lib/authorization';

const thinkingSteps = [
    'Analizando tu pregunta…',
    'Verificando tu rol y permisos…',
    'Consultando la información autorizada…',
    'Preparando una respuesta…',
];

const assistantModuleLinks = [
    { label: 'módulo de Sesiones', href: '/admin/work-sessions', permission: 'reports.operational.view' },
    { label: 'módulo de Documentación', href: '/admin/documentation', permission: null },
    { label: 'módulo de Plataformas', href: '/admin/platforms', permission: 'platforms.view' },
    { label: 'módulo de Roles y permisos', href: '/admin/access', permission: 'roles.view' },
    { label: 'módulo de Operación diaria', href: '/admin/operations', permission: 'shifts.view_team' },
    { label: 'módulo de Station Manager', href: '/admin/station-manager', permission: 'stations.assign' },
    { label: 'módulo de Workflows', href: '/admin/workflows', permission: 'settings.manage' },
];

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function InlineMarkdown({ text, can }) {
    const visibleLinks = assistantModuleLinks.filter((link) => !link.permission || can(link.permission));
    const linkPattern = visibleLinks.map((link) => escapeRegExp(link.label)).join('|');
    const tokenPattern = linkPattern ? new RegExp(`(\\*\\*[^*]+\\*\\*|` + '`[^`]+`' + `|${linkPattern})`, 'gi') : /(\*\*[^*]+\*\*|`[^`]+`)/g;
    const parts = text.split(tokenPattern).filter(Boolean);

    return <>{parts.map((part, index) => {
        if (part.startsWith('**') && part.endsWith('**')) {
            return <strong key={index} className="font-semibold text-white">{part.slice(2, -2)}</strong>;
        }
        if (part.startsWith('`') && part.endsWith('`')) {
            return <code key={index} className="rounded bg-[#0b0c12] px-1.5 py-0.5 font-mono text-[11px] text-[#e5a9ef]">{part.slice(1, -1)}</code>;
        }
        const moduleLink = visibleLinks.find((link) => link.label.toLocaleLowerCase() === part.toLocaleLowerCase());
        if (moduleLink) {
            return <a key={index} href={moduleLink.href} className="velvet-ai-module-link">{part}</a>;
        }
        return <span key={index}>{part}</span>;
    })}</>;
}

function AssistantMessage({ content, can }) {
    const cleanContent = String(content || '').replace(/^\s*(?:#{1,3}\s*)?respuesta\s*:?[ \t]*(?:\r?\n|$)/i, '');
    const lines = cleanContent.split(/\r?\n/);

    return <div className="space-y-2">
        {lines.map((line, index) => {
            const heading = line.match(/^#{1,3}\s+(.+)$/);
            const bullet = line.match(/^\s*[-*]\s+(.+)$/);
            const numbered = line.match(/^\s*\d+[.)]\s+(.+)$/);

            if (!line.trim()) return <div key={index} className="h-1" />;
            if (heading) return <h3 key={index} className="pt-1 font-semibold text-white"><InlineMarkdown text={heading[1]} can={can} /></h3>;
            if (bullet) return <div key={index} className="flex gap-2 pl-1"><span className="text-[#d56bea]">•</span><span><InlineMarkdown text={bullet[1]} can={can} /></span></div>;
            if (numbered) return <div key={index} className="flex gap-2 pl-1"><span className="text-[#d56bea]">{line.match(/^\s*(\d+)/)[1]}.</span><span><InlineMarkdown text={numbered[1]} can={can} /></span></div>;

            return <p key={index}><InlineMarkdown text={line} can={can} /></p>;
        })}
    </div>;
}

const quickPrompts = [
    { label: 'Ayuda con OBS', message: '¿Cómo puedo consultar el estado de OBS?', permission: 'stations.commands' },
    { label: 'Consultar sesiones', message: 'Muéstrame un resumen de las sesiones operativas.', permission: 'sessions.manage' },
    { label: 'Estado de estaciones', message: '¿Cuál es el estado de mis estaciones?', permission: 'stations.commands' },
    { label: 'Documentación', message: '¿Dónde encuentro la documentación de Velvet Master?', permission: 'documents.view' },
    { label: 'Ayuda con plataformas', message: '¿Qué puedo consultar sobre las plataformas?', permission: 'platforms.view' },
];

export default function GroqChatbot() {
    const page = usePage();
    const { can } = useAuthorization();
    const [open, setOpen] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const [launcherAnchor, setLauncherAnchor] = useState(null);
    const [input, setInput] = useState('');
    const [busy, setBusy] = useState(false);
    const [thinkingStep, setThinkingStep] = useState(0);
    const [messages, setMessages] = useState([]);
    const [copiedIndex, setCopiedIndex] = useState(null);
    const endRef = useRef(null);
    const popoverRef = useRef(null);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, open]);

    useEffect(() => {
        if (!busy) {
            setThinkingStep(0);
            return undefined;
        }

        const timer = window.setInterval(() => {
            setThinkingStep((current) => (current + 1) % thinkingSteps.length);
        }, 900);

        return () => window.clearInterval(timer);
    }, [busy]);

    useEffect(() => {
        if (!open) return undefined;
        const onKeyDown = (event) => {
            if (event.key === 'Escape') setOpen(false);
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open]);

    useEffect(() => {
        const openFromLauncher = () => {
            setOpen(true);
            setExpanded(false);
            setLauncherAnchor(null);
        };
        window.addEventListener('velvet-ai:open', openFromLauncher);
        return () => window.removeEventListener('velvet-ai:open', openFromLauncher);
    }, []);

    useEffect(() => {
        if (!open) return undefined;
        const updateAnchor = () => {
            const button = document.querySelector('.velvet-launcher-toggle');
            const popover = popoverRef.current;
            const rect = button?.getBoundingClientRect();
            const popoverRect = popover?.getBoundingClientRect();
            if (!rect || !popoverRect) return;
            setLauncherAnchor({
                left: Math.max(12, rect.right - popoverRect.width),
                bottom: window.innerHeight - rect.top + 12,
            });
        };
        const firstFrame = window.requestAnimationFrame(() => window.requestAnimationFrame(updateAnchor));
        const observer = popoverRef.current && typeof ResizeObserver !== 'undefined' ? new ResizeObserver(updateAnchor) : null;
        if (observer && popoverRef.current) observer.observe(popoverRef.current);
        window.addEventListener('resize', updateAnchor);
        window.addEventListener('scroll', updateAnchor, { passive: true });
        return () => {
            window.cancelAnimationFrame(firstFrame);
            observer?.disconnect();
            window.removeEventListener('resize', updateAnchor);
            window.removeEventListener('scroll', updateAnchor);
        };
    }, [open, expanded]);

    if (!page.props.auth?.user || !can('ai.chat') || !page.props.groqAssistant?.enabled) return null;

    const sendMessage = async (message) => {
        if (!message || busy) return;
        const history = messages.slice(-12);
        setMessages((current) => [...current, { role: 'user', content: message }]);
        setInput('');
        setBusy(true);
        try {
            const response = await window.axios.post('/ai/chat', { message, history, path: page.url.split('?')[0] });
            setMessages((current) => [...current, { role: 'assistant', content: response.data.message }]);
        } catch (error) {
            setMessages((current) => [...current, { role: 'assistant', content: error.response?.data?.message || 'No pude responder ahora. Intenta nuevamente.' }]);
        } finally {
            setBusy(false);
        }
    };

    const send = (event) => {
        event.preventDefault();
        sendMessage(input.trim());
    };

    const copyMessage = async (content, index) => {
        try {
            await navigator.clipboard.writeText(content);
            setCopiedIndex(index);
            window.setTimeout(() => setCopiedIndex((current) => current === index ? null : current), 1400);
        } catch {
            setCopiedIndex(null);
        }
    };

    const availablePrompts = quickPrompts.filter((prompt) => can(prompt.permission));

    return <>
        {createPortal(open ? <div ref={popoverRef} className={`velvet-ai-popover-shell pointer-events-none fixed bottom-[4.75rem] right-5 z-[80] bg-transparent ${expanded ? 'velvet-ai-popover-shell--expanded' : ''}`} style={launcherAnchor ? { left: launcherAnchor.left, right: 'auto', bottom: launcherAnchor.bottom } : undefined} role="presentation">
            <section role="dialog" aria-modal="false" aria-labelledby="groq-chat-title" className={`velvet-ai-panel pointer-events-auto flex w-full flex-col overflow-hidden border border-[#4c3157] bg-[#11121a] ${expanded ? 'velvet-ai-panel--expanded' : ''}`}>
                <header className="velvet-ai-header flex shrink-0 items-center justify-between border-b border-[#292d39] px-4 py-3.5">
                    <div className="flex min-w-0 items-center gap-3"><span className="velvet-ai-avatar grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#3d1b4a] text-[#e9a8f4]"><FiCpu /></span><div className="min-w-0"><div className="flex items-center gap-2"><h2 id="groq-chat-title" className="truncate text-sm font-semibold text-white">Velvet AI</h2><span className="velvet-ai-status" aria-label="Servicio disponible" /></div><p className="truncate text-[10px] text-[#9296a4]">Asistente virtual</p></div></div>
                    <div className="flex items-center gap-1"><button type="button" onClick={() => setMessages([])} className="velvet-ai-icon-button" aria-label="Nueva conversación" title="Nueva conversación"><FiPlus size={16} /></button><button type="button" onClick={() => setExpanded((current) => !current)} className="velvet-ai-icon-button hidden sm:grid" aria-label={expanded ? 'Contraer asistente' : 'Expandir asistente'} title={expanded ? 'Contraer' : 'Expandir'}>{expanded ? <FiMinimize2 size={15} /> : <FiMaximize2 size={15} />}</button><button type="button" onClick={() => setOpen(false)} className="velvet-ai-icon-button" aria-label="Cerrar asistente" title="Cerrar"><FiX size={17} /></button></div>
                </header>
                <div className="velvet-scrollbar min-h-0 flex-1 space-y-3 overflow-y-auto p-0">
                    {!messages.length && <><div className="velvet-ai-welcome rounded-2xl border border-[#343044] bg-[#181522] p-4 text-xs leading-5 text-[#bdb5c5]"><p className="font-medium text-white">¿En qué puedo ayudarte?</p><p className="mt-1">Consulta módulos, accesos y datos permitidos por tu rol.</p></div><div className="velvet-ai-prompts">{availablePrompts.map((prompt) => <button type="button" key={prompt.label} onClick={() => sendMessage(prompt.message)} disabled={busy} className="velvet-ai-prompt"><FiFileText size={13} />{prompt.label}</button>)}</div></>}
                    {messages.map((item, index) => <div key={`${item.role}-${index}`} className={`velvet-ai-message group max-w-[90%] rounded-2xl px-4 py-3 text-xs leading-5 ${item.role === 'user' ? 'velvet-ai-message--user ml-auto text-white' : 'velvet-ai-message--assistant border border-[#343044] text-[#d4ceda]'}`}>{item.role === 'assistant' ? <><AssistantMessage content={item.content} can={can} /><button type="button" onClick={() => copyMessage(item.content, index)} className="velvet-ai-copy" aria-label="Copiar respuesta" title="Copiar respuesta">{copiedIndex === index ? <FiCheck size={12} /> : <FiCopy size={12} />}</button></> : item.content}</div>)}
                    {busy && <div className="velvet-ai-thinking flex w-fit items-center gap-2 rounded-2xl border border-[#343044] bg-[#181522] px-4 py-3 text-xs text-[#bdb5c5]" role="status" aria-live="polite"><span className="velvet-ai-thinking-dots" aria-hidden="true"><i /><i /><i /></span><span>{thinkingSteps[thinkingStep]}</span></div>}<div ref={endRef} />
                </div>
                <form onSubmit={send} className="velvet-ai-composer shrink-0 border-t border-[#292d39] p-0" style={{ paddingBottom: 'max(.875rem, env(safe-area-inset-bottom))' }}><div className="flex items-end gap-2 rounded-2xl border border-[#343044] bg-[#0f0e16] p-2"><textarea value={input} onChange={(event) => setInput(event.target.value)} rows="1" maxLength="4000" placeholder="Pregúntale a Velvet AI…" className="min-h-10 flex-1 resize-none border-0 bg-transparent px-2 py-2 text-xs text-white outline-none placeholder:text-[#777d8f]" onKeyDown={(event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); send(event); } }} /><button type="submit" disabled={busy || !input.trim()} className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#a92ad8] text-white disabled:cursor-not-allowed disabled:opacity-40" aria-label="Enviar pregunta"><FiSend size={15} /></button></div><p className="mt-2 px-1 text-[9px] text-[#777d8f]">Enter para enviar · Shift + Enter para una nueva línea</p></form>
            </section>
        </div> : null, document.body)}
    </>;
}
