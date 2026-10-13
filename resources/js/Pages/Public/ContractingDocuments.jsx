import { Head, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { FiAlertCircle, FiArrowLeft, FiArrowRight, FiCheck, FiCheckCircle, FiClock, FiDownload, FiEye, FiFileText, FiLock, FiUploadCloud, FiXCircle } from 'react-icons/fi';

const statusConfig = {
    PENDING: { label: 'En revisión', Icon: FiClock, badge: 'border-[#725525] bg-[#352515] text-[#ffe1a1]' },
    VERIFIED: { label: 'Aprobado', Icon: FiCheckCircle, badge: 'border-[#2d8669] bg-[#12372e] text-[#9af2cb]' },
    REJECTED: { label: 'Requiere corrección', Icon: FiXCircle, badge: 'border-[#713246] bg-[#3b1726] text-[#ffb1bd]' },
};

const fileSize = (bytes) => bytes ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : '';

function UploadPreview({ file, storedFile, onClear, canReplace, inputId }) {
    const [previewUrl, setPreviewUrl] = useState(null);
    const isLocalFile = file instanceof Blob;
    const isImage = file?.type?.startsWith('image/') || file?.mime_type?.startsWith('image/');
    const isPdf = file?.type === 'application/pdf' || file?.mime_type === 'application/pdf' || file?.name?.toLowerCase().endsWith('.pdf');

    useEffect(() => {
        if (!isLocalFile) {
            setPreviewUrl(null);
            return undefined;
        }

        const url = URL.createObjectURL(file);
        setPreviewUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [file, isLocalFile]);

    if (!file) return null;

    return <div className="public-document-wizard__selected-preview"><span className="public-document-wizard__selected-preview-media">{isImage && previewUrl ? <img src={previewUrl} alt="Vista previa del archivo seleccionado" /> : isPdf ? <span className="public-document-wizard__pdf-preview" aria-label="Archivo PDF seleccionado"><span className="public-document-wizard__pdf-preview-badge">PDF</span><FiFileText size={28} /><span className="public-document-wizard__pdf-preview-lines"><i /><i /><i /></span></span> : <FiFileText size={26} />}</span><span className="public-document-wizard__selected-preview-copy"><strong>{storedFile ? 'Documento cargado' : isImage ? 'Vista previa lista' : 'Documento PDF seleccionado'}</strong><small>{file.name} · {fileSize(file.size)}</small><em>{storedFile ? 'Disponible para revisión administrativa' : 'Listo para guardar. Puedes reemplazarlo o quitarlo.'}</em></span><span className="public-document-wizard__selected-preview-actions">{storedFile && <><a href={storedFile.preview_url} target="_blank" rel="noreferrer" aria-label={`Ver ${storedFile.name}`}><FiEye size={14} /></a><a href={storedFile.download_url} aria-label={`Descargar ${storedFile.name}`}><FiDownload size={14} /></a></>}{canReplace && <label htmlFor={inputId}>Reemplazar</label>}{onClear && <button type="button" onClick={onClear}>Quitar</button>}</span></div>;
}

function Stepper({ requirements, currentStep, processing, onStep }) {
    return <ol className="public-document-wizard__steps velvet-scrollbar" style={{ '--step-count': requirements.length }}>
        {requirements.map((requirement, index) => {
            const active = index === currentStep;
            const completed = requirement.complete;
            const locked = index > currentStep;
            const config = statusConfig[requirement.status];
            const status = locked ? 'Bloqueado' : config?.label || (completed ? 'Cargado' : 'Pendiente');
            const className = `public-document-wizard__step${active ? ' public-document-wizard__step--active' : ''}${completed ? ' public-document-wizard__step--complete' : ''}`;

            return <li key={requirement.key} className={className}><button type="button" disabled={locked || processing} onClick={() => onStep(index)} aria-current={active ? 'step' : undefined} aria-label={locked ? `${requirement.title}: bloqueado hasta completar el paso anterior` : `Ver ${requirement.title}`}><span className="public-document-wizard__step-number">{completed && !active ? <FiCheck size={16} /> : index + 1}</span><span className="public-document-wizard__step-label">{requirement.title}</span><span className="public-document-wizard__step-status">{status}</span></button></li>;
        })}
    </ol>;
}

export default function ContractingDocuments({ candidate, requirements = [] }) {
    const firstIncomplete = useMemo(() => requirements.findIndex((item) => !item.complete), [requirements]);
    const initialStep = firstIncomplete === -1 ? Math.max(requirements.length - 1, 0) : firstIncomplete;
    const [currentStep, setCurrentStep] = useState(initialStep);
    const [selectedFile, setSelectedFile] = useState(null);
    const [dragging, setDragging] = useState(false);
    const [stepError, setStepError] = useState('');
    const form = useForm({ documents: {}, finalize: false });
    const current = requirements[currentStep];
    const uploadedCount = requirements.filter((item) => item.uploaded).length;
    const approvedCount = requirements.filter((item) => item.status === 'VERIFIED').length;
    const requiredComplete = requirements.filter((item) => item.required).every((item) => item.complete);
    const uploadPercent = requirements.length ? Math.round((uploadedCount / requirements.length) * 100) : 0;

    useEffect(() => { setCurrentStep(initialStep); setSelectedFile(null); setStepError(''); }, [initialStep]);
    if (!current) return null;

    const canReplace = !current.uploaded || current.file?.can_replace;
    const fieldError = stepError || form.errors[`documents.${current.key}`] || form.errors.documents;
    const isFinalStep = currentStep === requirements.length - 1;
    const select = (file) => { if (file && canReplace) { setSelectedFile(file); setStepError(''); } };
    const goToStep = (step) => { setSelectedFile(null); setStepError(''); setCurrentStep(step); };
    const saveAndContinue = () => {
        if (current.complete && !selectedFile) { setCurrentStep((step) => Math.min(step + 1, requirements.length - 1)); return; }
        if (!selectedFile) { setStepError('Selecciona un archivo para guardar este documento y continuar.'); return; }
        form.transform(() => ({ documents: { [current.key]: selectedFile }, finalize: false }));
        form.post(window.location.href, { forceFormData: true, preserveScroll: true, preserveState: false });
    };
    const finalize = () => {
        if (!requiredComplete) { setStepError('Completa los documentos obligatorios antes de finalizar.'); return; }
        form.transform(() => ({ documents: selectedFile ? { [current.key]: selectedFile } : {}, finalize: true }));
        form.post(window.location.href, { forceFormData: true, preserveScroll: true, preserveState: false });
    };
    const currentFile = selectedFile || current.file;

    return <><Head title="Cargar documentos" /><main className="public-document-wizard"><div className="public-document-wizard__container"><header className="public-document-wizard__hero"><span className="public-document-wizard__hero-icon"><FiUploadCloud size={22} /></span><p className="public-document-wizard__eyebrow">Contratación segura</p><h1>Carga tus documentos</h1><p>Hola, {candidate.name}. Guarda cada documento para desbloquear el siguiente paso.</p><p className="public-document-wizard__candidate-code">Código de candidato: {candidate.code}</p></header>
        <form className="public-document-wizard__shell" onSubmit={(event) => event.preventDefault()}><section className="public-document-wizard__progress"><div className="public-document-wizard__progress-heading"><div className="public-document-wizard__progress-title"><span><FiFileText size={18} /></span><div><h2>Progreso de documentos</h2><p>{uploadedCount} de {requirements.length} completados · {approvedCount} aprobados</p></div></div><span className="public-document-wizard__progress-pill">{uploadPercent}% · Paso {currentStep + 1}/{requirements.length}</span></div><div className="public-document-wizard__bar" role="progressbar" aria-label="Progreso de documentos cargados" aria-valuemin="0" aria-valuemax="100" aria-valuenow={uploadPercent}><span style={{ width: `${uploadPercent}%` }} /></div><Stepper requirements={requirements} currentStep={currentStep} processing={form.processing} onStep={goToStep} /></section>
            <section className="public-document-wizard__content"><div className="public-document-wizard__document-card"><div className="public-document-wizard__document-heading"><div className="public-document-wizard__document-title"><span className="public-document-wizard__section-icon"><FiFileText size={20} /></span><div><p className="public-document-wizard__eyebrow">Documento {String(currentStep + 1).padStart(2, '0')}</p><h2>{current.title}</h2><p>{current.description}</p></div></div><span className="public-document-wizard__requirement-pill">{current.required ? 'Obligatorio' : 'Opcional'}</span></div>
                {current.status === 'REJECTED' && <div className="public-document-wizard__notice"><FiAlertCircle /> <strong>Este documento requiere corrección.</strong>{current.review_note && <p>Observación: {current.review_note}</p>}</div>}
                <div className={`public-document-wizard__dropzone${dragging ? ' public-document-wizard__dropzone--dragging' : ''}${currentFile ? ' public-document-wizard__dropzone--selected' : ''}`} onDragEnter={canReplace ? (event) => { event.preventDefault(); setDragging(true); } : undefined} onDragOver={canReplace ? (event) => event.preventDefault() : undefined} onDragLeave={canReplace ? () => setDragging(false) : undefined} onDrop={canReplace ? (event) => { event.preventDefault(); setDragging(false); select(event.dataTransfer.files?.[0]); } : undefined}>{currentFile ? <UploadPreview file={currentFile} storedFile={selectedFile ? null : current.file} onClear={selectedFile ? () => setSelectedFile(null) : null} canReplace={canReplace} inputId={`document-file-${current.key}`} /> : <><span className="public-document-wizard__upload-symbol"><FiUploadCloud size={23} /></span><strong>Arrastra tu documento aquí</strong><small>O selecciona un archivo desde tu dispositivo</small>{canReplace && <label className="public-document-wizard__select-file" htmlFor={`document-file-${current.key}`}><FiFileText size={14} />Seleccionar archivo</label>}</>}<input id={`document-file-${current.key}`} type="file" className="sr-only" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" onChange={(event) => { select(event.target.files?.[0]); event.target.value = ''; }} disabled={form.processing || !canReplace} /></div>
                {!canReplace && <p className="public-document-wizard__notice public-document-wizard__notice--protected">Este documento fue aprobado y está protegido contra reemplazos.</p>}
                {form.progress && <div className="public-document-wizard__upload-progress" aria-live="polite"><div><span>Guardando archivo…</span><span>{form.progress.percentage}%</span></div><div className="public-document-wizard__upload-progress-bar"><span style={{ width: `${form.progress.percentage}%` }} /></div></div>}
                {fieldError && <p className="public-document-wizard__error" role="alert">{fieldError}</p>}{form.processing && <div className="public-document-wizard__saving-overlay" role="status" aria-live="polite" aria-label="Guardando documento"><span className="public-document-wizard__saving-orbit"><i /><i /><i /></span><strong>{isFinalStep ? 'Finalizando documentos…' : `Guardando documento ${currentStep + 1} de ${requirements.length}…`}</strong><small>{form.progress ? `${form.progress.percentage}% cargado de forma segura` : 'Estamos protegiendo y registrando tu archivo.'}</small>{form.progress && <span className="public-document-wizard__saving-progress"><i style={{ width: `${form.progress.percentage}%` }} /></span>}</div>}</div></section>
            <footer className="public-document-wizard__footer"><p className="public-document-wizard__privacy"><span className="public-document-wizard__privacy-icon"><FiLock size={14} /></span>Al continuar, el documento se guarda de forma privada.</p><div className="public-document-wizard__actions">{currentStep > 0 && <button type="button" disabled={form.processing} onClick={() => goToStep(currentStep - 1)} className="public-document-wizard__secondary-action"><FiArrowLeft />Anterior</button>}{isFinalStep ? <button type="button" disabled={form.processing || !requiredComplete} onClick={finalize} className="velvet-button public-document-wizard__primary-action">{form.processing ? 'Guardando…' : <><FiCheckCircle />Finalizar documentos</>}</button> : <button type="button" disabled={form.processing} onClick={saveAndContinue} className="velvet-button public-document-wizard__primary-action">{form.processing ? 'Guardando…' : current.complete && !selectedFile ? 'Continuar' : 'Guardar y continuar'}<FiArrowRight /></button>}</div></footer>
        </form></div></main></>;
}
