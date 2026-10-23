import { useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxChip,
    ComboboxChips,
    ComboboxChipsInput,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxItem,
    ComboboxList,
    ComboboxValue,
    useComboboxAnchor,
} from '@/components/ui/combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/proposals/requirements';

export type RequirementCompetencyOption = {
    id: number;
    code: string;
    name: string;
    label: string;
};

export type ProjectRequirement = {
    competency_id: number;
    minimum_level: number | null;
};

type LevelOption = { value: number; label: string };

type Props = {
    projectId: number;
    competencyOptions: RequirementCompetencyOption[];
    requirements: ProjectRequirement[];
    levelOptions: LevelOption[];
    onDirtyChange?: (dirty: boolean) => void;
};

const NO_LEVEL = 'none';

/**
 * Pick the competencies a project needs from the official master. The same
 * competency cannot be added twice, and a minimum level is optional for each.
 */
export function ProjectRequirementsField({
    projectId,
    competencyOptions,
    requirements,
    levelOptions,
    onDirtyChange,
}: Props) {
    const anchor = useComboboxAnchor();
    const form = useForm<{ requirements: ProjectRequirement[] }>({
        requirements,
    });
    const [saved, setSaved] = useState(requirements);
    const selectedIds = form.data.requirements.map(
        (requirement) => requirement.competency_id,
    );
    const selectedOptions = form.data.requirements
        .map((requirement) =>
            competencyOptions.find(
                (option) => option.id === requirement.competency_id,
            ),
        )
        .filter(
            (option): option is RequirementCompetencyOption =>
                option !== undefined,
        );
    const isDirty =
        JSON.stringify(form.data.requirements) !== JSON.stringify(saved);
    const listError =
        form.errors.requirements ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('requirements.'),
        )?.[1];

    useEffect(() => {
        onDirtyChange?.(isDirty);
    }, [isDirty, onDirtyChange]);

    function pick(options: RequirementCompetencyOption[]): void {
        form.setData(
            'requirements',
            options.map((option) => ({
                competency_id: option.id,
                minimum_level:
                    form.data.requirements.find(
                        (requirement) =>
                            requirement.competency_id === option.id,
                    )?.minimum_level ?? null,
            })),
        );
    }

    function setLevel(competencyId: number, level: number | null): void {
        form.setData(
            'requirements',
            form.data.requirements.map((requirement) =>
                requirement.competency_id === competencyId
                    ? { ...requirement, minimum_level: level }
                    : requirement,
            ),
        );
    }

    function save(): void {
        form.put(update.url(projectId), {
            preserveScroll: true,
            onSuccess: () => setSaved(form.data.requirements),
        });
    }

    return (
        <div className="grid gap-6">
            <FormField
                id="project-requirements"
                label="Kompetensi Dibutuhkan"
                error={listError}
            >
                <Combobox
                    multiple
                    items={competencyOptions.filter(
                        (option) =>
                            selectedIds.includes(option.id) ||
                            selectedIds.length < 20,
                    )}
                    value={selectedOptions}
                    onValueChange={pick}
                    itemToStringLabel={(item: RequirementCompetencyOption) =>
                        item.label
                    }
                    isItemEqualToValue={(
                        a: RequirementCompetencyOption,
                        b: RequirementCompetencyOption,
                    ) => a.id === b.id}
                >
                    <ComboboxChips ref={anchor}>
                        <ComboboxValue>
                            {(values: RequirementCompetencyOption[]) => (
                                <>
                                    {values.map((value) => (
                                        <ComboboxChip key={value.id}>
                                            {value.code}
                                        </ComboboxChip>
                                    ))}
                                    <ComboboxChipsInput
                                        id="project-requirements"
                                        placeholder={
                                            values.length === 0
                                                ? 'Cari kompetensi...'
                                                : ''
                                        }
                                    />
                                </>
                            )}
                        </ComboboxValue>
                    </ComboboxChips>
                    <ComboboxContent anchor={anchor}>
                        <ComboboxEmpty>
                            Kompetensi tidak ditemukan.
                        </ComboboxEmpty>
                        <ComboboxList>
                            {(item: RequirementCompetencyOption) => (
                                <ComboboxItem key={item.id} value={item}>
                                    {item.label}
                                </ComboboxItem>
                            )}
                        </ComboboxList>
                    </ComboboxContent>
                </Combobox>
            </FormField>

            <p className="text-xs text-muted-foreground">
                {selectedIds.length}/20 kompetensi dipilih. Level minimum
                opsional.
            </p>

            {selectedOptions.length > 0 && (
                <ul
                    className="grid gap-3"
                    aria-label="Level minimum per kompetensi"
                >
                    {selectedOptions.map((option) => {
                        const current = form.data.requirements.find(
                            (requirement) =>
                                requirement.competency_id === option.id,
                        );
                        const levelItems = [
                            { value: NO_LEVEL, label: 'Tanpa minimum' },
                            ...levelOptions.map((level) => ({
                                value: String(level.value),
                                label: `${level.value} · ${level.label}`,
                            })),
                        ];

                        return (
                            <li
                                key={option.id}
                                className="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <p className="min-w-0 text-sm">
                                    <span className="font-medium">
                                        {option.code}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {option.name}
                                    </span>
                                </p>
                                <Select
                                    value={
                                        current?.minimum_level
                                            ? String(current.minimum_level)
                                            : NO_LEVEL
                                    }
                                    onValueChange={(value) =>
                                        setLevel(
                                            option.id,
                                            value === NO_LEVEL || value === null
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                    items={levelItems}
                                >
                                    <SelectTrigger
                                        aria-label={`Level minimum ${option.code}`}
                                        className="w-full sm:w-52"
                                    >
                                        <SelectValue placeholder="Tanpa minimum" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {levelItems.map((item) => (
                                            <SelectItem
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </li>
                        );
                    })}
                </ul>
            )}

            <div className="flex justify-end">
                <Button
                    type="button"
                    variant="outline"
                    className="h-10 px-4"
                    disabled={form.processing || !isDirty}
                    onClick={save}
                >
                    {form.processing ? (
                        <Spinner data-icon="inline-start" />
                    ) : (
                        <Save data-icon="inline-start" aria-hidden="true" />
                    )}
                    Simpan Kebutuhan Kompetensi
                </Button>
            </div>
            {isDirty && (
                <p
                    role="status"
                    className="text-xs text-amber-700 dark:text-amber-300"
                >
                    Simpan kebutuhan kompetensi sebelum melanjutkan ke tahap
                    berikutnya.
                </p>
            )}
        </div>
    );
}
