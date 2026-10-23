import { cloneElement, type ReactElement } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

type FieldControlProps = {
    id?: string;
    required?: boolean;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
};

type Props = {
    id: string;
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    children: ReactElement<FieldControlProps>;
};

const validationMessages: Record<string, string> = {
    'validation.email': 'Format email tidak valid.',
    'validation.max.string': 'Maksimal 255 karakter.',
    'validation.required': 'Field wajib diisi.',
    'validation.unique': 'Nilai ini sudah digunakan.',
};

export function FormField({
    id,
    label,
    required = false,
    hint,
    error,
    children,
}: Props) {
    const describedBy = [
        hint ? `${id}-hint` : null,
        error ? `${id}-error` : null,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div className="grid gap-2.5">
            <Label htmlFor={id} className="gap-0.5">
                {label}
                {required && (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                )}
            </Label>
            {hint && (
                <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                    {hint}
                </p>
            )}
            {cloneElement(children, {
                id,
                required,
                'aria-invalid': error ? true : undefined,
                'aria-describedby': describedBy || undefined,
            })}
            <InputError
                id={`${id}-error`}
                role="alert"
                className="text-sm text-destructive"
                message={error ? (validationMessages[error] ?? error) : error}
            />
        </div>
    );
}
