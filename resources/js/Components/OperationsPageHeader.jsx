export default function OperationsPageHeader({ eyebrow, title, description, actions = null }) {
    return (
        <header className="flex flex-col gap-5 border-b border-[#292d39] pb-6 lg:flex-row lg:items-end lg:justify-between">
            <div className="max-w-3xl">
                <p className="block text-[10px] font-semibold uppercase tracking-[.25em] text-[#d56bea]">{eyebrow}</p>
                <h1 className="mt-3 font-editorial text-3xl leading-tight text-white sm:text-4xl xl:text-5xl">{title}</h1>
                <p className="mt-3 max-w-2xl text-sm leading-6 text-[#969baa]">{description}</p>
            </div>
            {actions && <div className="flex shrink-0 flex-wrap gap-3">{actions}</div>}
        </header>
    );
}
