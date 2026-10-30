import { useEffect, useState } from 'react';
import VelvetDatePicker from '@/Components/VelvetDatePicker';
import VelvetTimePicker from '@/Components/VelvetTimePicker';

const today = () => {
    const value = new Date();
    value.setHours(0, 0, 0, 0);
    return value;
};

const nextYear = () => {
    const value = today();
    value.setFullYear(value.getFullYear() + 1);
    return value;
};

export default function VelvetDateTimeField({ label, value = '', onChange, error, timeStep = 60 }) {
    const [date, setDate] = useState(value?.slice(0, 10) || '');
    const [time, setTime] = useState(value?.slice(11, 16) || '');

    useEffect(() => {
        setDate(value?.slice(0, 10) || '');
        setTime(value?.slice(11, 16) || '');
    }, [value]);

    const update = (nextDate, nextTime) => {
        setDate(nextDate);
        setTime(nextTime);
        if (nextDate && nextTime) onChange({ target: { value: `${nextDate}T${nextTime}` } });
    };

    const isStart = label.toLowerCase().includes('inicio');

    return <fieldset className="velvet-datetime-field">
        <legend className="velvet-datetime-field__legend"><span className="velvet-datetime-field__index">{isStart ? '01' : '02'}</span><span>{label}</span><span className="velvet-datetime-field__zone">Bogotá · GMT-5</span></legend>
        <p className="velvet-datetime-field__hint">{isStart ? 'Cuándo comienza el uso' : 'Cuándo termina el uso'}</p>
        <div className="velvet-datetime-field__grid">
            <VelvetDatePicker label="Fecha" value={date} onChange={(event) => update(event.target.value, time)} minDate={today()} maxDate={nextYear()} error={null} ariaLabel={`Seleccionar fecha de ${label.toLowerCase()}`} />
            <VelvetTimePicker label="Hora" value={time} onChange={(event) => update(date, event.target.value)} error={null} step={timeStep} />
        </div>
        {error && <span className="mt-1 block text-xs text-[#ffb1bd]">{error}</span>}
    </fieldset>;
}
