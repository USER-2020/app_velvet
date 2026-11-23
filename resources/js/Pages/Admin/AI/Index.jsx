import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '@/Pages/Admin/Recruitment/Layout';
import { FiCpu, FiShield, FiX } from 'react-icons/fi';

export default function Index({ settings }) {
    const form = useForm({ enabled: settings.enabled, model: settings.model, models: settings.models, auto_select: settings.auto_select, max_tokens: settings.max_tokens, temperature: settings.temperature });
    const [modelInput, setModelInput] = useState('');

    const addModel = (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        const model = modelInput.trim().replace(/,$/, '');
        if (!model || form.data.models.includes(model)) {
            setModelInput('');
            return;
        }

        form.setData('models', [...form.data.models, model]);
        setModelInput('');
    };

    const removeModel = (model) => {
        form.setData('models', form.data.models.filter((item) => item !== model));
    };

    return <><Head title="Asistente IA" /><Layout><div className="mx-auto max-w-4xl"><div className="flex items-start gap-4"><span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[#3d1b4a] text-[#e9a8f4]"><FiCpu size={24} /></span><div><p className="text-[10px] uppercase tracking-[.28em] text-[#d56bea]">Configuración avanzada</p><h1 className="mt-2 font-editorial text-4xl text-white">Asistente IA</h1><p className="mt-2 max-w-2xl text-sm text-[#969baa]">Controla el acceso al chatbot conectado a Groq. La clave se mantiene en el servidor y nunca se envía al navegador.</p></div></div><div className="mt-8 rounded-xl border border-[#292d39] bg-[#11121a] p-6"><div className="flex items-start gap-3 border-b border-[#292d39] pb-5"><FiShield className="mt-0.5 text-[#d56bea]" /><div><h2 className="text-sm font-semibold text-white">Protección por roles</h2><p className="mt-1 text-xs leading-5 text-[#969baa]">Cada consulta recibe únicamente el rol y los permisos efectivos de la persona autenticada. El asistente no ejecuta acciones ni recibe credenciales.</p></div></div><form onSubmit={(event) => { event.preventDefault(); form.put('/admin/ai-assistant'); }} className="mt-6 space-y-5"><label className="flex items-center gap-3 text-sm text-[#d4ceda]"><input type="checkbox" checked={form.data.enabled} onChange={(event) => form.setData('enabled', event.target.checked)} className="rounded border-[#51405a] bg-[#0f0e16] text-[#a92ad8]" /> Habilitar chatbot para los roles autorizados</label><label className="block text-xs text-[#bdb5c5]">Modelo principal<input value={form.data.model} onChange={(event) => form.setData('model', event.target.value)} className="velvet-input mt-2" required /><span className="mt-1 block text-[10px] text-[#777d8f]">Se usa como primera opción para preguntas sencillas.</span></label><div className="block text-xs text-[#bdb5c5]"><span>Modelos disponibles para selección automática</span><div className="mt-2 flex min-h-[52px] flex-wrap items-center gap-2 rounded-xl border border-[#343044] bg-[#0f0e16] p-2.5 focus-within:border-[#a84bc2]"><div className="contents">{form.data.models.map((model) => <span key={model} className="inline-flex items-center gap-2 rounded-lg border border-[#5e39a5] bg-[#24183b] px-3 py-2 font-mono text-xs text-[#e4caff]">{model}<button type="button" onClick={() => removeModel(model)} className="rounded p-0.5 text-[#c28eea] transition hover:bg-[#492363] hover:text-white" aria-label={`Eliminar modelo ${model}`}><FiX size={14} /></button></span>)}</div><input value={modelInput} onChange={(event) => setModelInput(event.target.value)} onKeyDown={addModel} className="min-w-[220px] flex-1 border-0 bg-transparent px-1 py-2 text-sm text-white outline-none placeholder:text-[#777d8f]" placeholder="Escribe un modelo y presiona Enter" /></div><span className="mt-1 block text-[10px] text-[#777d8f]">Presiona Enter para agregar. El segundo se prioriza para preguntas complejas y los siguientes sirven como fallback.</span></div><label className="flex items-center gap-3 text-sm text-[#d4ceda]"><input type="checkbox" checked={form.data.auto_select} onChange={(event) => form.setData('auto_select', event.target.checked)} className="rounded border-[#51405a] bg-[#0f0e16] text-[#a92ad8]" /> Elegir automáticamente según la pregunta y usar fallback si hay límite</label><div className="grid gap-4 sm:grid-cols-2"><label className="block text-xs text-[#bdb5c5]">Máximo de tokens<input type="number" min="100" max="2000" value={form.data.max_tokens} onChange={(event) => form.setData('max_tokens', Number(event.target.value))} className="velvet-input mt-2" /></label><label className="block text-xs text-[#bdb5c5]">Temperatura<input type="number" min="0.1" max="1" step="0.1" value={form.data.temperature} onChange={(event) => form.setData('temperature', Number(event.target.value))} className="velvet-input mt-2" /></label></div><p className={`text-xs ${settings.configured ? 'text-emerald-300' : 'text-amber-300'}`}>{settings.configured ? 'API key detectada en el servidor.' : 'Falta GROQ_API_KEY en el entorno.'}</p><div className="flex justify-end border-t border-[#292d39] pt-5"><button type="submit" disabled={form.processing} className="velvet-button">{form.processing ? 'Guardando…' : 'Guardar configuración'}</button></div></form></div></div></Layout></>;
}
