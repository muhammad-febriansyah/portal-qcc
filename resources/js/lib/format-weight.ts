/**
 * Weights are stored with two decimals ("30.00"). They read better without the trailing
 * zeros: 30 stays 30 and 12.5 shows as 12,5.
 */
export function formatWeight(
    value: string | number | null | undefined,
): string {
    const number = Number(value);

    if (
        value === null ||
        value === undefined ||
        value === '' ||
        !Number.isFinite(number)
    ) {
        return '—';
    }

    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(
        number,
    );
}

/**
 * Value for a number input: "30.00" becomes "30", "12.50" becomes "12.5".
 */
export function weightInputValue(
    value: string | number | null | undefined,
): string {
    const number = Number(value);

    return value === null ||
        value === undefined ||
        value === '' ||
        !Number.isFinite(number)
        ? ''
        : String(number);
}
