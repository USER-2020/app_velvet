export default function BrandMark({ className = '' }) {
    return <div className={`velvet-brand-mark ${className}`}>
        <img src="/THEVELVETSTUDIO_BRANDBOOK-55.png" alt="The Velvet Studio" className="velvet-brand-mark__legacy" />
        <span className="velvet-brand-mark__light" aria-label="The Velvet Studio">
            <img src="/LOGO_OVERLAY.svg" alt="" className="velvet-brand-mark__symbol" />
            <span className="velvet-brand-mark__wordmark">
                <span className="velvet-brand-mark__name">THE VELVET</span>
                <span className="velvet-brand-mark__studio">STUDIO</span>
            </span>
        </span>
    </div>;
}
