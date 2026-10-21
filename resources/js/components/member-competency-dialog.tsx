import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
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
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/my-competencies';

export type LevelOption = { value: number; label: string };
export type GroupOption = { id: number; label: string };
export type CompetencyOption = {
    id: number;
    group_id: number;
    label: string;
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
    groupOptions: GroupOption[];
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
    groupOptions,
    competencyOptions,
    levelOptions,
    onClose,
}: Omit<Props, 'open' | 'onOpenChange'> & { onClose: () => void }) {
    const isEditing = editing !== null;
    const [groupId, setGroupId] = useState('all');
    const [selected, setSelected] = useState<CompetencyOption | null>(null);
    const [level, setLevel] = useState(
        editing ? String(editing.self_level) : '',
    );

    const visibleOptions = useMemo(
        () =>
            groupId === 'all'
                ? competencyOptions
                : competencyOptions.filter(
                      (option) => String(option.group_id) === groupId,
                  ),
        [competencyOptions, groupId],
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
                            <FormField id="competency-group" label="Kelompok">
                                <Select
                                    value={groupId}
                                    onValueChange={(value) => {
                                        setGroupId(value ?? 'all');
                                        setSelected(null);
                                    }}
                                    items={[
                                        {
                                            value: 'all',
                                            label: 'Semua kelompok',
                                        },
                                        ...groupOptions.map((group) => ({
                                            value: String(group.id),
                                            label: group.label,
                                        })),
                                    ]}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih kelompok" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua kelompok
                                        </SelectItem>
                                        {groupOptions.map((group) => (
                                            <SelectItem
                                                key={group.id}
                                                value={String(group.id)}
                                            >
                                                {group.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>

                            <FormField
                                id="competency-id"
                                label="Kompetensi"
                                required
                                error={errors.competency_id}
                            >
                                <Combobox
                                    items={visibleOptions}
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
                                                <ComboboxItem
                                                    key={item.id}
                                                    value={item}
                                                >
                                                    {item.label}
                                                </ComboboxItem>
                                            )}
                                        </ComboboxList>
                                    </ComboboxContent>
                                </Combobox>
                            </FormField>
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
    groupOptions,
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
                        groupOptions={groupOptions}
                        competencyOptions={competencyOptions}
                        levelOptions={levelOptions}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
