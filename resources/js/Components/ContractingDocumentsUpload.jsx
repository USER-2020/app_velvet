import { useForm } from '@inertiajs/react';
import { createPortal } from 'react-dom';
import { useMemo, useRef, useState } from 'react';
import { FiCheckCircle, FiDownload, FiFileText, FiTrash2, FiUpload, FiX } from 'react-icons/fi';

const formatSize = (bytes) => {
    if (!bytes) return 'Tamaño no disponible';
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

export default function ContractingDocumentsUpload({ candidateId, status, documents = [] }) {
    const form = useForm({ documents: [] });
    const inputRef = useRef(null);
    const [selectedFiles, setSelectedFiles] = useState([]);
    const [open, setOpen] = useState(false);
    const [confirmingOnboarding, setConfirmingOnboarding] = useState(false);
    const contractDocuments = useMemo(
        () => documents.filter((document) => document.type === 'contract_document').slice().reverse(),
        [documents],
    );
    const canUpload = Boolean(candidateId) && ['CONTRACTING', 'ONBOARDING'].includes(status);
    const documentSummary = contractDocuments.length
        ? contractDocuments.length + ' archivo' + (contractDocuments.length === 1 ? '' : 's') + ' asociado' + (contractDocuments.length === 1 ? '' : 's') + ' al expediente.'
        : 'Aún no hay archivos firmados asociados al expediente.';

    if (!candidateId || (!canUpload && !contractDocuments.length)) return null;

    const chooseFiles = (event) => {
        const files = Array.from(event.target.files || []).slice(0, 5);
        setSelectedFiles(files);
        form.setData('documents', files);
    };

    const removeFile = (index) => {
        const files = selectedFiles.filter((_, fileIndex) => fileIndex !== index);
        setSelectedFiles(files);
        form.setData('documents', files);
        if (!files.length && inputRef.current) inputRef.current.value = '';
    };

    const upload = () => {
        if (!selectedFiles.length) return;

        form.post(`/admin/contracting/candidates/${candidateId}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setSelectedFiles([]);
                setConfirmingOnboarding(false);
                setOpen(false);
                if (inputRef.current) inputRef.current.value = '';
            },
        });
    };

    const requestUpload = (event) => {
        event.preventDefault();
        if (!selectedFiles.length) return;
        if (status === 'CONTRACTING') {
            setConfirmingOnboarding(true);
            return;
        }
        upload();
    };

    return <section data-tour="contract-documents-upload" className="mt-8 border-t border-[#292d39] pt-7">
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div className="flex items-start gap-3">
                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-[#3b1948] text-[#d56bea]"><FiFileText size={17} /></span>
                <div>
                    <p className="text-[10px] uppercase tracking-[.2em] text-[#d56bea]">Documentos de contratación</p>
                    <p className="mt-2 text-xs leading-5 text-[#969baa]">{documentSummary}</p>
                </div>
            </div>
            <button type="button" onClick={() => setOpen(true)} className="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-[#80508c] bg-[#2b1535] px-3 py-2.5 text-xs font-medium text-[#f1c5f8] transition hover:border-[#c23bea] hover:bg-[#3b1749]"><FiFileText size={14} />{canUpload ? 'Gestionar archivos' : 'Ver archivos'}</button>
        </div>

        {open && createPortal(<div className="fixed inset-0 z-[100] grid h-dvh w-screen place-items-center bg-black/75 p-4 backdrop-blur-sm" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setOpen(false)}>
            <section role="dialog" aria-modal="true" aria-labelledby="contract-documents-title" style={{ width: '32rem', maxWidth: 'calc(100vw - 2rem)' }} className="flex max-h-[calc(100dvh-2rem)] w-[32rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-[#4c3157] bg-[#11121a] shadow-[0_25px_80px_rgba(0,0,0,.6)]">
                <header className="flex shrink-0 items-start justify-between gap-4 border-b border-[#292d39] bg-[#151522] px-5 py-5">
                    <div><p className="text-[10px] uppercase tracking-[.22em] text-[#d56bea]">Expediente contractual</p><h2 id="contract-documents-title" className="mt-2 font-editorial text-2xl text-white">Documentos de contratación</h2><p className="mt-1 text-xs text-[#969baa]">Asocia el contrato firmado y los soportes finales a esta persona.</p></div>
                    <button type="button" onClick={() => setOpen(false)} aria-label="Cerrar documentos de contratación" className="rounded-lg border border-transparent p-2 text-[#9297a7] transition hover:border-[#49334f] hover:bg-[#25152e] hover:text-white"><FiX /></button>
                </header>
                <div className="velvet-scrollbar min-h-0 overflow-y-auto p-4 sm:p-5">
        {canUpload && <form onSubmit={requestUpload} className="mt-5 rounded-xl border border-[#343044] bg-[#151522] p-4">
            <label className="block text-xs text-[#c8c2ce]">
                Archivos firmados
                <input ref={inputRef} type="file" multiple accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png" onChange={chooseFiles} className="mt-2 block w-full cursor-pointer rounded-lg border border-[#343044] bg-[#11121a] px-3 py-2 text-xs text-[#aaa5b5] file:mr-3 file:rounded-md file:border-0 file:bg-[#3b1948] file:px-3 file:py-2 file:text-xs file:text-[#e8b1f3]" />
            </label>
            {selectedFiles.length > 0 && <div className="mt-3 space-y-2">
                {selectedFiles.map((file, index) => <div key={`${file.name}-${file.lastModified}`} className="flex items-center justify-between gap-3 rounded-lg border border-[#343044] bg-[#11121a] px-3 py-2">
                    <span className="flex min-w-0 items-center gap-2"><FiFileText className="shrink-0 text-[#d56bea]" size={15} /><span className="min-w-0"><strong className="block truncate text-xs text-[#eee8f2]">{file.name}</strong><small className="text-[10px] text-[#7f8495]">{formatSize(file.size)}</small></span></span>
                    <button type="button" onClick={() => removeFile(index)} className="shrink-0 rounded-md p-1.5 text-[#ffb1bd] transition hover:bg-[#321622]" aria-label={`Quitar ${file.name}`}><FiTrash2 size={14} /></button>
                </div>)}
            </div>}
            {form.errors.documents && <p className="mt-2 text-xs text-[#ffb1bd]">{form.errors.documents}</p>}
            <button type="submit" disabled={form.processing || !selectedFiles.length} className="velvet-button mt-4 w-full gap-2 disabled:cursor-not-allowed disabled:opacity-50"><FiUpload size={15} />{form.processing ? 'Subiendo documentos…' : 'Subir archivos firmados'}</button>
        </form>}

        {contractDocuments.length > 0 && <div className="mt-4 space-y-2">
            <p className="text-[10px] uppercase tracking-[.18em] text-[#7f8495]">Archivos asociados</p>
            {contractDocuments.map((document) => <div key={document.id} className="flex items-center justify-between gap-3 rounded-lg border border-[#2d8669]/60 bg-[#12372e]/30 px-3 py-3">
                <span className="flex min-w-0 items-center gap-2"><FiCheckCircle className="shrink-0 text-[#9af2cb]" size={15} /><span className="min-w-0"><strong className="block truncate text-xs text-[#e5f8ef]">{document.original_name || 'Documento de contratación'}</strong><small className="text-[10px] text-[#8dcbb0]">{document.status || 'RECIBIDO'} · {formatSize(document.size)}</small></span></span>
                <span className="flex shrink-0 items-center gap-2">{document.id && <><a href={`/admin/documents/${document.id}/preview`} target="_blank" rel="noreferrer" className="text-[10px] font-medium text-[#9af2cb] hover:text-white">Ver</a><a href={`/admin/documents/${document.id}/download`} className="text-[#9af2cb] hover:text-white" aria-label={`Descargar ${document.original_name || 'documento'}`}><FiDownload size={14} /></a></>}</span>
            </div>)}
        </div>}
                </div>
                <footer className="flex shrink-0 justify-end border-t border-[#292d39] bg-[#151522] px-5 py-4"><button type="button" onClick={() => setOpen(false)} className="rounded-lg border border-[#343044] px-4 py-2.5 text-xs text-[#c8c2ce] transition hover:border-[#80508c] hover:text-white">Cerrar</button></footer>
            </section>
            {confirmingOnboarding && <div className="absolute inset-0 grid place-items-center bg-black/65 p-4 backdrop-blur-sm" role="presentation">
                <section role="alertdialog" aria-modal="true" aria-labelledby="confirm-onboarding-title" className="w-full max-w-md rounded-2xl border border-[#80508c] bg-[#171321] p-5 shadow-[0_25px_80px_rgba(0,0,0,.7)] sm:p-6">
                    <div className="flex items-start gap-3">
                        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#3b1948] text-[#e9a4f5]"><FiCheckCircle size={19} /></span>
                        <div><p className="text-[10px] font-semibold uppercase tracking-[.2em] text-[#d56bea]">Confirmar avance</p><h2 id="confirm-onboarding-title" className="mt-2 font-editorial text-xl text-white">Pasar a onboarding</h2><p className="mt-2 text-xs leading-5 text-[#bdb5c6]">Al confirmar, el contrato firmado quedará asociado al expediente y el candidato avanzará a la etapa de onboarding.</p></div>
                    </div>
                    <div className="mt-4 rounded-lg border border-[#343044] bg-[#11121a] px-3 py-2.5 text-xs text-[#d5cedb]">Los archivos se guardarán de forma privada y quedarán disponibles para auditoría.</div>
                    <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" onClick={() => setConfirmingOnboarding(false)} disabled={form.processing} className="rounded-lg border border-[#343044] px-4 py-2.5 text-xs text-[#c8c2ce] transition hover:border-[#80508c] hover:text-white">Cancelar</button><button type="button" onClick={upload} disabled={form.processing} className="velvet-button gap-2 disabled:cursor-not-allowed disabled:opacity-50">{form.processing ? 'Guardando…' : 'Confirmar y pasar a onboarding'}<FiUpload size={14} /></button></div>
                </section>
            </div>}
        </div>, document.body)}
    </section>;
}
