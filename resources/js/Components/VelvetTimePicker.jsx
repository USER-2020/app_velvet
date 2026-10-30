import { Clock3 } from 'lucide-react';
import * as Popover from '@radix-ui/react-popover';
import { useState } from 'react';

export default function VelvetTimePicker({ label, value, onChange, error, min = '00:00', max = '23:45', step = 60 }) {
    const [open, setOpen] = useState(false);
    const optionCount = Math.ceil((24 * 60) / step);
    const timeOptions = Array.from({ length: optionCount }, (_, index) => {
        const minutes = index * step;
        return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
    });
    const options = timeOptions.filter((option) => option >= min && option <= max);
    const selectTime = (time) => {
        onChange({ target: { value: time } });
        setOpen(false);
    };

    return <label className="block text-xs font-medium text-[#c8c2ce]">{label}<Popover.Root open={open} onOpenChange={setOpen}><Popover.Trigger asChild><button type="button" aria-label={`Seleccionar ${label.toLowerCase()}`} className={`velvet-time-trigger ${value ? 'text-[#f7f1fb]' : 'text-[#7f8495]'} ${error ? 'border-red-400' : ''}`}><span>{value || 'Selecciona una hora'}</span><Clock3 size={17} strokeWidth={1.8} className="text-[#8f8a99]" /></button></Popover.Trigger><Popover.Portal><Popover.Content align="start" sideOffset={8} className="velvet-time-popover" onOpenAutoFocus={(event) => event.preventDefault()}><p className="mb-2 px-2 text-[10px] uppercase tracking-[.18em] text-[#7f8495]">Selecciona una hora</p><div className="grid max-h-64 grid-cols-3 gap-1 overflow-y-auto">{options.map((option) => <button type="button" key={option} onClick={() => selectTime(option)} className={`rounded-md px-2 py-2 text-xs transition ${value === option ? 'bg-[#8c2aae] text-white' : 'text-[#d8d3dc] hover:bg-[#2d1c37] hover:text-white'}`}>{option}</button>)}</div></Popover.Content></Popover.Portal></Popover.Root>{error && <span className="mt-1 block text-xs text-red-400">{error}</span>}</label>;
}
