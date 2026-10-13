import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { subscribeToCandidateDocuments } from '../../lib/ably';

function formatSize(bytes) {
    if (!bytes) return 'Archivo recibido';
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function ContractingDocumentsSuccess({ candidate, documents = [], realtime = {} }) {
    const [live, setLive] = useState(false);
    const [confirmedCount, setConfirmedCount] = useState(documents.length);

    useEffect(() => subscribeToCandidateDocuments({
        authUrl: realtime.auth_url,
        channelName: realtime.channel,
        onAvailabilityChange: setLive,
        onUpdate: (payload) => {
            if (payload?.candidate_id === undefined || Number(payload.candidate_id) === Number(candidate.id)) {
                setConfirmedCount((current) => Math.max(current, Number(payload?.documents_count || documents.length)));
                setLive(true);
            }
        },
    }), [realtime.auth_url, realtime.channel]);

    const progress = documents.length ? Math.min(100, (confirmedCount / documents.length) * 100) : 0;

    return (
        <>
            <Head title="Documentos recibidos" />
            <main className="public-document-success">
                <div className="public-document-success__hero-mark" aria-hidden="true">&#10003;</div>
                <p className="public-document-wizard__eyebrow">CONTRATACI&Oacute;N SEGURA</p>
                <h1>Documentos recibidos</h1>
                <p className="public-document-success__intro">
                    Gracias, {candidate.name}. Tus archivos fueron subidos exitosamente y ya est&aacute;n listos para revisi&oacute;n.
                </p>
                <p className="public-document-success__code">C&oacute;digo de candidato: {candidate.code}</p>

                <section className="public-document-success__card" aria-live="polite">
                    <div className="public-document-success__summary">
                        <div className="public-document-success__summary-main">
                            <span className="public-document-success__status-icon">&#10003;</span>
                            <div>
                                <strong>Entrega completada</strong>
                                <span>{confirmedCount} archivo{confirmedCount === 1 ? '' : 's'} asociado{confirmedCount === 1 ? '' : 's'} a tu perfil</span>
                            </div>
                        </div>
                        <span className={`public-document-success__live ${live ? 'is-live' : ''}`}>
                            <i /> <span>{live ? 'Sincronizado en tiempo real' : 'Guardado en el sistema'}</span>
                        </span>
                    </div>
                    <div className="public-document-success__progress" aria-hidden="true"><i style={{ width: `${progress}%` }} /></div>
                    <div className="public-document-success__files-heading"><span>Archivos entregados</span><small>{documents.length} de {documents.length} recibidos</small></div>
                    <div className="public-document-success__files">
                        {documents.map((document, index) => (
                            <div className="public-document-success__file" key={`${document.name}-${document.uploaded_at}`}>
                                <span className="public-document-success__file-index">{String(index + 1).padStart(2, '0')}</span>
                                <span className="public-document-success__file-icon" aria-hidden="true">&#128196;</span>
                                <div className="public-document-success__file-copy"><strong>{document.name}</strong><small>{formatSize(document.size)} &middot; Recibido</small></div>
                                <span className="public-document-success__check">&#10003;</span>
                            </div>
                        ))}
                    </div>
                    <div className="public-document-success__note">
                        El equipo administrativo revisar&aacute; cada archivo. Si necesitamos una correcci&oacute;n, te contactaremos por los datos registrados.
                    </div>
                </section>
                <p className="public-document-success__footer">Puedes cerrar esta ventana con seguridad.</p>
            </main>
        </>
    );
}
