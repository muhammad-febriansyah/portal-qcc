import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Award, PencilLine, Plus, Trash2 } from 'lucide-react';
import type { DataTableColumnDef } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import RubricSetController from '@/actions/App/Http/Controllers/Admin/RubricSetController';
import rubricRoutes from '@/routes/admin/rubrics';

type ScoringType = {
    value: string;
    label: string;
};

type RubricCriterion = {
    code: string;
    name: string;
    description: string | null;
    weight_pct: string;
};

type RubricRow = {
    id: number;
    scoring_type: string;
    name: string;
    versions: {
        id: number;
        version_number: number;
        publication_status: string;
        criteria: RubricCriterion[];
    }[];
};

type CriterionInput = {
    code: string;
    name: string;
    description: string;
    weight_pct: string;
};

type RubricForm = {
    scoring_type: string;
    name: string;
    criteria: CriterionInput[];
};

type Props = {
    rubrics: RubricRow[];
    scoringTypes: ScoringType[];
    publishingEnabled: boolean;
};

function emptyCriterion(): CriterionInput {
    return { code: '', name: '', description: '', weight_pct: '' };
}

export default function RubricsIndex({
    rubrics,
    scoringTypes,
    publishingEnabled,
}: Props) {
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingRubric, setEditingRubric] = useState<RubricRow | null>(null);
    const form = useForm<RubricForm>({
        scoring_type: scoringTypes[0]?.value ?? 'proposal',
        name: '',
        criteria: [emptyCriterion()],
    });

    const availableTypes = scoringTypes.filter(
        (type) => !rubrics.some((rubric) => rubric.scoring_type === type.value),
    );

    function openCreateDialog(): void {
        setEditingRubric(null);
        form.resetAndClearErrors();
        form.setData({
            scoring_type: availableTypes[0]?.value ?? '',
            name: '',
            criteria: [emptyCriterion()],
        });
        setIsDialogOpen(true);
    }

    function openEditDialog(rubric: RubricRow): void {
        const version = rubric.versions[0];
        setEditingRubric(rubric);
        form.resetAndClearErrors();
        form.setData({
            scoring_type: rubric.scoring_type,
            name: rubric.name,
            criteria: version?.criteria.map((criterion) => ({
                ...criterion,
                description: criterion.description ?? '',
            })) ?? [emptyCriterion()],
        });
        setIsDialogOpen(true);
    }

    function submitForm(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setIsDialogOpen(false),
        };

        if (editingRubric) {
            form.put(RubricSetController.update.url(editingRubric.id), options);

            return;
        }

        form.post(RubricSetController.store.url(), options);
    }

    function updateCriterion(
        index: number,
        key: keyof CriterionInput,
        value: string,
    ): void {
        form.setData(
            'criteria',
            form.data.criteria.map((criterion, criterionIndex) =>
                criterionIndex === index
                    ? {
                          ...criterion,
                          [key]: key === 'code' ? value.toUpperCase() : value,
                      }
                    : criterion,
            ),
        );
    }

    function addCriterion(): void {
        if (form.data.criteria.length < 30) {
            form.setData('criteria', [...form.data.criteria, emptyCriterion()]);
        }
    }

    function removeCriterion(index: number): void {
        if (form.data.criteria.length > 1) {
            form.setData(
                'criteria',
                form.data.criteria.filter(
                    (_, criterionIndex) => criterionIndex !== index,
                ),
            );
        }
    }

    const columns = useMemo<DataTableColumnDef<RubricRow>[]>(
        () => [
            {
                id: 'scoring_type',
                accessorKey: 'scoring_type',
                header: 'Jenis Penilaian',
                cell: ({ row }) => (
                    <Badge variant="outline">
                        {scoringTypes.find(
                            (type) => type.value === row.original.scoring_type,
                        )?.label ?? row.original.scoring_type}
                    </Badge>
                ),
            },
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Nama Rubrik',
                cell: ({ row }) => (
                    <span className="font-medium">{row.original.name}</span>
                ),
            },
            {
                id: 'version',
                header: 'Versi',
                cell: ({ row }) =>
                    `v${row.original.versions[0]?.version_number ?? 1}`,
            },
            {
                id: 'criteria',
                header: 'Kriteria',
                cell: ({ row }) => {
                    const criteria = row.original.versions[0]?.criteria ?? [];
                    const total = criteria.reduce(
                        (sum, criterion) => sum + Number(criterion.weight_pct),
                        0,
                    );

                    return (
                        <div className="flex flex-col gap-1">
                            <span>{criteria.length} kriteria</span>
                            <span className="text-xs text-muted-foreground">
                                Total bobot draft: {total.toFixed(2)}%
                            </span>
                        </div>
                    );
                },
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge variant="secondary">
                        {row.original.versions[0]?.publication_status ===
                        'published'
                            ? 'Terpublikasi'
                            : 'Draft'}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                header: 'Aksi',
                cell: ({ row }) => (
                    <DataTableRowActions
                        label={`Aksi untuk ${row.original.name}`}
                        actions={[
                            {
                                label: 'Ubah draft',
                                icon: PencilLine,
                                onSelect: () => openEditDialog(row.original),
                                disabled:
                                    row.original.versions[0]
                                        ?.publication_status !== 'draft',
                            },
                        ]}
                    />
                ),
            },
        ],
        [scoringTypes],
    );

    const totalWeight = form.data.criteria.reduce(
        (sum, criterion) => sum + (Number(criterion.weight_pct) || 0),
        0,
    );

    return (
        <>
            <Head title="Rubrik" />
            <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Rubrik', href: rubricRoutes.index() },
                    ]}
                    title="Rubrik"
                    description="Susun draft kriteria dan bobot untuk tiga jenis penilaian QCC."
                    actions={
                        availableTypes.length > 0 ? (
                            <Button type="button" onClick={openCreateDialog}>
                                <Plus
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                Tambah Draft Rubrik
                            </Button>
                        ) : undefined
                    }
                />

                <div className="space-y-5">
                    <Alert>
                        <Award aria-hidden="true" />
                        <AlertTitle>Mode draft</AlertTitle>
                        <AlertDescription>
                            Bobot baseline di PRD belum disahkan. Draft dapat
                            disusun dan disimpan, tetapi publikasi dan
                            penggunaan untuk scoring tetap terkunci sampai
                            keputusan DEC-13 disetujui.
                            {!publishingEnabled &&
                                ' Total bobot 100% belum diwajibkan pada tahap draft.'}
                        </AlertDescription>
                    </Alert>

                    <Card className="gap-0 py-0">
                        <DataTable
                            embedded
                            columns={columns}
                            data={rubrics}
                            rowId={(rubric) => String(rubric.id)}
                            page={1}
                            pageSize={10}
                            total={rubrics.length}
                            onPageChange={() => undefined}
                            emptyTitle="Belum ada draft rubrik"
                            emptyDescription="Tambahkan draft pertama untuk mulai menyusun kriteria dan bobot tanpa menerbitkannya."
                        />
                    </Card>
                </div>
            </div>

            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="max-h-[90vh] gap-0 overflow-y-auto sm:max-w-3xl">
                    <DialogHeader className="pb-5">
                        <DialogTitle>
                            {editingRubric
                                ? 'Ubah Draft Rubrik'
                                : 'Tambah Draft Rubrik'}
                        </DialogTitle>
                        <DialogDescription>
                            Draft belum digunakan scoring dan tidak dapat
                            dipublikasikan sebelum DEC-13 disetujui.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitForm} className="grid gap-5">
                        <FormField
                            id="rubric-scoring-type"
                            label="Jenis Penilaian"
                            required
                            error={form.errors.scoring_type}
                        >
                            <Select
                                value={form.data.scoring_type}
                                onValueChange={(value) =>
                                    value && form.setData('scoring_type', value)
                                }
                                disabled={Boolean(editingRubric)}
                                items={scoringTypes.map((type) => ({
                                    value: type.value,
                                    label: type.label,
                                }))}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Pilih jenis penilaian" />
                                </SelectTrigger>
                                <SelectContent>
                                    {scoringTypes.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField
                            id="rubric-name"
                            label="Nama Rubrik"
                            required
                            error={form.errors.name}
                        >
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Contoh: Rubrik Proposal QCC"
                                maxLength={150}
                            />
                        </FormField>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Kriteria dan Bobot
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Jumlah bobot draft saat ini:{' '}
                                        {totalWeight.toFixed(2)}%
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={addCriterion}
                                    disabled={form.data.criteria.length >= 30}
                                >
                                    <Plus
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    Tambah Kriteria
                                </Button>
                            </div>

                            {form.data.criteria.map((criterion, index) => (
                                <div
                                    key={`criterion-${index}`}
                                    className="grid gap-3 rounded-lg border border-border/70 p-4 md:grid-cols-[1fr_1.5fr_120px_auto]"
                                >
                                    <FormField
                                        id={`criterion-${index}-code`}
                                        label="Kode"
                                        required
                                        error={
                                            form.errors[
                                                `criteria.${index}.code` as keyof typeof form.errors
                                            ]
                                        }
                                    >
                                        <Input
                                            value={criterion.code}
                                            onChange={(event) =>
                                                updateCriterion(
                                                    index,
                                                    'code',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Contoh: GOALS"
                                            maxLength={32}
                                            autoComplete="off"
                                        />
                                    </FormField>
                                    <FormField
                                        id={`criterion-${index}-name`}
                                        label="Nama Kriteria"
                                        required
                                        error={
                                            form.errors[
                                                `criteria.${index}.name` as keyof typeof form.errors
                                            ]
                                        }
                                    >
                                        <Input
                                            value={criterion.name}
                                            onChange={(event) =>
                                                updateCriterion(
                                                    index,
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Masukkan nama kriteria"
                                            maxLength={150}
                                        />
                                    </FormField>
                                    <FormField
                                        id={`criterion-${index}-weight`}
                                        label="Bobot (%)"
                                        required
                                        error={
                                            form.errors[
                                                `criteria.${index}.weight_pct` as keyof typeof form.errors
                                            ]
                                        }
                                    >
                                        <Input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            value={criterion.weight_pct}
                                            onChange={(event) =>
                                                updateCriterion(
                                                    index,
                                                    'weight_pct',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="0"
                                        />
                                    </FormField>
                                    <div className="flex items-end justify-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Hapus kriteria ${index + 1}`}
                                            onClick={() =>
                                                removeCriterion(index)
                                            }
                                            disabled={
                                                form.data.criteria.length === 1
                                            }
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    </div>
                                    <div className="md:col-span-4">
                                        <FormField
                                            id={`criterion-${index}-description`}
                                            label="Deskripsi"
                                            error={
                                                form.errors[
                                                    `criteria.${index}.description` as keyof typeof form.errors
                                                ]
                                            }
                                        >
                                            <Textarea
                                                value={criterion.description}
                                                onChange={(event) =>
                                                    updateCriterion(
                                                        index,
                                                        'description',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Jelaskan aspek yang dinilai (opsional)"
                                                maxLength={2000}
                                                rows={2}
                                            />
                                        </FormField>
                                    </div>
                                </div>
                            ))}
                            {form.errors.criteria && (
                                <p className="text-sm text-destructive">
                                    {form.errors.criteria}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="border-t pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsDialogOpen(false)}
                                disabled={form.processing}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Draft'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
