import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxList,
} from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { CompetencyOptionItem } from '@/components/competency-option-item';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/my-competencies';

export type LevelOption = { value: number; label: string };
export type CompetencyOption = {
    id: number;
    label: string;
    description: string | null;
};

export type EditableCompetency = {
    id: number;
    code: string;
    name: string;
    self_level: number;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: EditableCompetency | null;
    competencyOptions: CompetencyOption[];
    levelOptions: LevelOption[];
};

function LevelField({
    levelOptions,
    value,
    onChange,
    error,
}: {
    levelOptions: LevelOption[];
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-2.5">
            <Label>
                Level Kemampuan
                <span className="text-destructive" aria-hidden="true">
                    *
                </span>
            </Label>
            <RadioGroup
                value={value}
                onValueChange={(next) => onChange(String(next))}
                className="grid gap-2 sm:grid-cols-2"
            >
                {levelOptions.map((level) => (
                    <label
                        key={level.value}
                        htmlFor={`level-${level.value}`}
                        className="flex cursor-pointer items-center gap-3 rounded-lg border border-border/70 px-3 py-2.5 text-sm transition-colors hover:bg-muted/50 has-data-checked:border-primary has-data-checked:bg-primary/5"
                    >
                        <RadioGroupItem
                            id={`level-${level.value}`}
                            value={String(level.value)}
                        />
                        <span>
                            <span className="font-medium">{level.value}</span>
                            {' · '}
                            {level.label}
                        </span>
                    </label>
                ))}
            </RadioGroup>
            <input type="hidden" name="self_level" value={value} />
            {error && (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}

function CompetencyForm({
    editing,
    competencyOptions,
    levelOptions,
    onClose,
}: Omit<Props, 'open' | 'onOpenChange'> & { onClose: () => void }) {
    const isEditing = editing !== null;
    const [selected, setSelected] = useState<CompetencyOption | null>(null);
    const [level, setLevel] = useState(
        editing ? String(editing.self_level) : '',
    );

    return (
        <Form
            {...(isEditing ? update.form(editing.id) : store.form())}
            options={{ preserveScroll: true }}
            onSuccess={onClose}
            className="grid gap-6"
        >
            {({ errors, processing }) => (
                <>
                    {isEditing ? (
                        <FormField id="competency-name" label="Kompetensi">
                            <Input
                                value={`${editing.code} - ${editing.name}`}
                                readOnly
                                disabled
                            />
                        </FormField>
                    ) : (
                        <>
                            <FormField
                                id="competency-id"
                                label="Kompetensi"
                                required
                                error={errors.competency_id}
                            >
                                <Combobox
                                    items={competencyOptions}
                                    value={selected}
                                    onValueChange={setSelected}
                                    itemToStringLabel={(
                                        item: CompetencyOption,
                                    ) => item.label}
                                    isItemEqualToValue={(
                                        a: CompetencyOption,
                                        b: CompetencyOption,
                                    ) => a.id === b.id}
                                >
                                    <ComboboxInput placeholder="Cari kompetensi..." />
                                    <ComboboxContent>
                                        <ComboboxEmpty>
                                            Kompetensi tidak ditemukan.
                                        </ComboboxEmpty>
                                        <ComboboxList>
                                            {(item: CompetencyOption) => (
                                                <CompetencyOptionItem
                                                    key={item.id}
                                                    item={item}
                                                />
                                            )}
                                        </ComboboxList>
                                    </ComboboxContent>
                                </Combobox>
                            </FormField>
                            {selected?.description && (
                                <p className="-mt-2 rounded-lg bg-muted/60 px-3 py-2 text-xs leading-relaxed text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        Definisi:{' '}
                                    </span>
                                    {selected.description}
                                </p>
                            )}
                            <input
                                type="hidden"
                                name="competency_id"
                                value={selected?.id ?? ''}
                            />
                        </>
                    )}

                    <LevelField
                        levelOptions={levelOptions}
                        value={level}
                        onChange={setLevel}
                        error={errors.self_level}
                    />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 px-4 text-sm"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            className="h-10 px-4 text-sm"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

export function MemberCompetencyDialog({
    open,
    onOpenChange,
    editing,
    competencyOptions,
    levelOptions,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-6 sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {editing
                            ? 'Ubah Level Kompetensi'
                            : 'Tambah Kompetensi'}
                    </DialogTitle>
                </DialogHeader>
                {open && (
                    <CompetencyForm
                        key={editing?.id ?? 'new'}
                        editing={editing}
                        competencyOptions={competencyOptions}
                        levelOptions={levelOptions}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
