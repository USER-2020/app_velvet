import { useState } from 'react';
import { addYears, format, isValid, parseISO, subYears } from 'date-fns';
import { es } from 'date-fns/locale';
import { CalendarDays } from 'lucide-react';
import { DayPicker } from 'react-day-picker';
import * as Popover from '@radix-ui/react-popover';
import 'react-day-picker/style.css';

function dateFromValue(value) {
    const rawValue = value?.target?.value ?? value;
    if (!rawValue) return undefined;
    const date = rawValue instanceof Date ? rawValue : parseISO(String(rawValue));
    return isValid(date) ? date : undefined;
}

export default function VelvetDatePicker({ label, value, onChange, error, minDate, maxDate, placeholder = 'dd/mm/aaaa', ariaLabel = 'Seleccionar fecha', bare = false, ...rest }) {
    const [open, setOpen] = useState(false);
    const selected = dateFromValue(value);
    const defaultMaxDate = subYears(new Date(), 18);
    const effectiveMaxDate = maxDate || defaultMaxDate;
    const effectiveMinDate = minDate;

    const selectDate = (date) => {
        if (!date) return;
        onChange({ target: { value: format(date, 'yyyy-MM-dd') } });
        setOpen(false);
    };

    const Wrapper = bare ? 'div' : 'label';

    return (
        <Wrapper className="block text-xs font-medium text-gray-200">
            {!bare && label}
            <Popover.Root open={open} onOpenChange={setOpen}>
                <Popover.Trigger asChild>
                    <button type="button" data-tour={rest['data-tour']} aria-label={ariaLabel} className={`velvet-date-trigger ${selected ? 'text-[#f7f1fb]' : 'text-gray-500'} ${error ? 'border-red-400' : ''}`}>
                        <span>{selected ? format(selected, 'dd/MM/yyyy') : placeholder}</span>
                        <CalendarDays size={17} strokeWidth={1.8} className="text-gray-400" />
                    </button>
                </Popover.Trigger>
                <Popover.Portal>
                    <Popover.Content align="start" sideOffset={8} className="velvet-calendar-popover" onOpenAutoFocus={(event) => event.preventDefault()}>
                        <DayPicker
                            mode="single"
                            locale={es}
                            selected={selected}
                            onSelect={selectDate}
                            defaultMonth={selected || effectiveMinDate || effectiveMaxDate}
                            captionLayout="dropdown"
                            fromYear={effectiveMinDate?.getFullYear() || 1940}
                            toYear={effectiveMaxDate?.getFullYear() || addYears(new Date(), 10).getFullYear()}
                            disabled={{ ...(effectiveMinDate ? { before: effectiveMinDate } : {}), ...(effectiveMaxDate ? { after: effectiveMaxDate } : {}) }}
                            showOutsideDays
                            className="velvet-calendar"
                            classNames={{
                                months: 'rdp-months',
                                month: 'rdp-month',
                                month_caption: 'rdp-month_caption',
                                caption_label: 'rdp-caption_label',
                                dropdowns: 'rdp-dropdowns',
                                dropdown: 'rdp-dropdown',
                                month_grid: 'rdp-month_grid',
                                weekdays: 'rdp-weekdays',
                                weekday: 'rdp-weekday',
                                week: 'rdp-week',
                                day: 'rdp-day',
                                day_button: 'rdp-day_button',
                                selected: 'rdp-selected',
                                today: 'rdp-today',
                                outside: 'rdp-outside',
                                disabled: 'rdp-disabled',
                                button_previous: 'rdp-button_previous',
                                button_next: 'rdp-button_next',
                            }}
                        />
                    </Popover.Content>
                </Popover.Portal>
            </Popover.Root>
            {error && <span className="mt-1 block text-xs text-red-400">{error}</span>}
        </Wrapper>
    );
}
