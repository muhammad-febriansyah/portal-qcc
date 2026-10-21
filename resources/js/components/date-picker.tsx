import { format, isValid, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import type { DateRange, Matcher } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type DatePickerControlProps = {
    id?: string;
    name?: string;
    disabled?: boolean;
    required?: boolean;
    placeholder?: string;
    disabledDates?: Matcher | Matcher[];
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
    className?: string;
};

type DatePickerProps = DatePickerControlProps & {
    value?: string | null;
    onValueChange: (value: string | null) => void;
};

export type DateRangeValue = {
    from: string | null;
    to: string | null;
};

type DateRangePickerProps = DatePickerControlProps & {
    value?: DateRangeValue | null;
    onValueChange: (value: DateRangeValue | null) => void;
};

function parseDateValue(value?: string | null): Date | undefined {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return undefined;
    }

    const date = parseISO(value);

    return isValid(date) && format(date, 'yyyy-MM-dd') === value
        ? date
        : undefined;
}

function formatDateValue(date: Date): string {
    return format(date, 'yyyy-MM-dd');
}

function DatePickerButton({
    placeholder,
    value,
    className,
    describedBy,
    invalid,
    required,
    ...props
}: Omit<React.ComponentProps<typeof Button>, 'value' | 'placeholder'> & {
    placeholder: string;
    value: string;
    describedBy?: string;
    invalid?: boolean;
    required?: boolean;
}) {
    return (
        <Button
            type="button"
            variant="outline"
            aria-required={required || undefined}
            aria-invalid={invalid || undefined}
            aria-describedby={describedBy}
            data-empty={!value}
            className={cn(
                'w-full justify-start text-left font-normal data-[empty=true]:text-muted-foreground',
                className,
            )}
            {...props}
        >
            <CalendarDays data-icon="inline-start" aria-hidden="true" />
            {value || placeholder}
        </Button>
    );
}

export function DatePicker({
    id,
    name,
    value,
    onValueChange,
    disabled,
    required,
    placeholder = 'Pilih tanggal',
    disabledDates,
    'aria-describedby': describedBy,
    'aria-invalid': invalid,
    className,
}: DatePickerProps) {
    const [open, setOpen] = useState(false);
    const selectedDate = parseDateValue(value);
    const displayValue = selectedDate
        ? format(selectedDate, 'd MMMM yyyy', { locale: indonesianLocale })
        : '';

    function selectDate(date?: Date): void {
        onValueChange(date ? formatDateValue(date) : null);

        if (date) {
            setOpen(false);
        }
    }

    return (
        <>
            {name && <input type="hidden" name={name} value={value ?? ''} />}
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger
                    render={
                        <DatePickerButton
                            id={id}
                            disabled={disabled}
                            required={required}
                            placeholder={placeholder}
                            value={displayValue}
                            className={className}
                            describedBy={describedBy}
                            invalid={invalid}
                        />
                    }
                />
                <PopoverContent align="start" className="w-auto p-0">
                    <Calendar
                        mode="single"
                        selected={selectedDate}
                        onSelect={selectDate}
                        disabled={disabledDates}
                        autoFocus
                    />
                    {selectedDate && (
                        <div className="border-t p-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="w-full"
                                onClick={() => selectDate(undefined)}
                            >
                                Hapus tanggal
                            </Button>
                        </div>
                    )}
                </PopoverContent>
            </Popover>
        </>
    );
}

export function DateRangePicker({
    id,
    name,
    value,
    onValueChange,
    disabled,
    required,
    placeholder = 'Pilih rentang tanggal',
    disabledDates,
    'aria-describedby': describedBy,
    'aria-invalid': invalid,
    className,
}: DateRangePickerProps) {
    const [open, setOpen] = useState(false);
    const selectedRange: DateRange | undefined = value
        ? {
              from: parseDateValue(value.from),
              to: parseDateValue(value.to),
          }
        : undefined;
    const displayValue = selectedRange?.from
        ? selectedRange.to
            ? `${format(selectedRange.from, 'd MMM yyyy', { locale: indonesianLocale })} – ${format(selectedRange.to, 'd MMM yyyy', { locale: indonesianLocale })}`
            : `${format(selectedRange.from, 'd MMM yyyy', { locale: indonesianLocale })} – Pilih tanggal akhir`
        : '';

    function selectRange(range?: DateRange): void {
        onValueChange(
            range?.from
                ? {
                      from: formatDateValue(range.from),
                      to: range.to ? formatDateValue(range.to) : null,
                  }
                : null,
        );

        if (range?.from && range.to) {
            setOpen(false);
        }
    }

    return (
        <>
            {name && (
                <>
                    <input
                        type="hidden"
                        name={`${name}[from]`}
                        value={value?.from ?? ''}
                    />
                    <input
                        type="hidden"
                        name={`${name}[to]`}
                        value={value?.to ?? ''}
                    />
                </>
            )}
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger
                    render={
                        <DatePickerButton
                            id={id}
                            disabled={disabled}
                            required={required}
                            placeholder={placeholder}
                            value={displayValue}
                            className={className}
                            describedBy={describedBy}
                            invalid={invalid}
                        />
                    }
                />
                <PopoverContent align="start" className="w-auto p-0">
                    <Calendar
                        mode="range"
                        selected={selectedRange}
                        onSelect={selectRange}
                        disabled={disabledDates}
                        numberOfMonths={2}
                        autoFocus
                    />
                    {selectedRange?.from && (
                        <div className="border-t p-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="w-full"
                                onClick={() => selectRange(undefined)}
                            >
                                Hapus rentang
                            </Button>
                        </div>
                    )}
                </PopoverContent>
            </Popover>
        </>
    );
}
