import { Head, router, useForm } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { useEffect, useMemo, useState } from 'react';
import { CalendarClock, Plus, UserRoundCog } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import type { DataTableColumnDef } from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { DatePicker } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { index, revoke, store } from '@/routes/admin/reviewer-assignments';

type Option = { value: string; label: string };
type ProjectOption = Option & { status: string };
type ReviewerOption = Option & { scopes: string[] };
type Assignment = {
    id: number;
    project: string;
    project_code: string;
    batch: string | null;
    reviewer: string;
    reviewer_email: string | null;
    scope: string;
    scope_label: string;
    status: 'ACTIVE' | 'REVOKED';
    expires_at: string | null;
    granted_by: string | null;
    created_at: string | null;
};
type Filters = { search: string; status: string; per_page: number };
type Props = {
    assignments: {
        data: Assignment[];
        current_page: number;
        per_page: number;
        total: number;
    };
    projects: ProjectOption[];
    reviewers: ReviewerOption[];
    scopeOptions: Option[];
    filters: Filters;
};

function formatDate(value: string | null): string {
    if (!value) return 'Tanpa batas waktu';

    return format(parseISO(value), 'd MMM yyyy', { locale: indonesianLocale });
}

export default function ReviewerAssignmentsIndex({
    assignments,
    projects,
    reviewers,
    scopeOptions,
    filters,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [searchValue, setSearchValue] = useState(filters.search);
    const [projectValue, setProjectValue] = useState<ProjectOption | null>(
        null,
    );
    const [reviewerValue, setReviewerValue] = useState<ReviewerOption | null>(
        null,
    );
    const form = useForm({
        project_id: '',
        reviewer_id: '',
        scope: scopeOptions[0]?.value ?? 'proposal',
        expires_at: '',
    });

    const availableReviewers = useMemo(
        () =>
            reviewers.filter((reviewer) =>
                reviewer.scopes.includes(form.data.scope),
            ),
        [form.data.scope, reviewers],
    );
    const availableProjects = useMemo(
        () =>
            projects.filter((project) =>
                form.data.scope === 'proposal'
                    ? [
                          'SUBMITTED',
                          'UNDER_REVIEW',
                          'REVISION_REQUIRED',
                      ].includes(project.status)
                    : project.status === 'APPROVED',
            ),
        [form.data.scope, projects],
    );

    useEffect(() => {
        setSearchValue(filters.search);
    }, [filters.search]);

    useEffect(() => {
        if (searchValue === filters.search) return;
        const timeout = window.setTimeout(() => {
            router.get(
                index.url({
                    query: { ...filters, search: searchValue, page: 1 },
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['assignments', 'filters'],
                },
            );
        }, 350);
        return () => window.clearTimeout(timeout);
    }, [filters, searchValue]);

    function openDialog(): void {
        form.resetAndClearErrors();
        setProjectValue(null);
        setReviewerValue(null);
        setDialogOpen(true);
    }

    function submitAssignment(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => setDialogOpen(false),
        });
    }

    function updateScope(scope: string): void {
        form.setData('scope', scope);
        const isProjectAvailable = (project: ProjectOption) =>
            scope === 'proposal'
                ? ['SUBMITTED', 'UNDER_REVIEW', 'REVISION_REQUIRED'].includes(
                      project.status,
                  )
                : project.status === 'APPROVED';
        if (projectValue && !isProjectAvailable(projectValue)) {
            setProjectValue(null);
            form.setData('project_id', '');
        }
        if (reviewerValue && !reviewerValue.scopes.includes(scope)) {
            setReviewerValue(null);
            form.setData('reviewer_id', '');
        }
    }

    function changePage(page: number): void {
        router.get(
            index.url({ query: { ...filters, search: searchValue, page } }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['assignments', 'filters'],
            },
        );
    }

    const columns = useMemo<DataTableColumnDef<Assignment>[]>(
        () => [
            {
                id: 'project',
                accessorKey: 'project',
                header: 'Proyek',
                cell: ({ row }) => (
                    <div className="min-w-48">
                        <p className="font-medium text-foreground">
                            {row.original.project}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {row.original.project_code}
                            {row.original.batch
                                ? ` · ${row.original.batch}`
                                : ''}
                        </p>
                    </div>
                ),
            },
            {
                id: 'reviewer',
                accessorKey: 'reviewer',
                header: 'Reviewer',
                cell: ({ row }) => (
                    <div>
                        <p className="font-medium text-foreground">
                            {row.original.reviewer}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {row.original.reviewer_email ?? 'Akun tidak aktif'}
                        </p>
                    </div>
                ),
            },
            { id: 'scope_label', accessorKey: 'scope_label', header: 'Scope' },
            {
                id: 'expires_at',
                accessorKey: 'expires_at',
                header: 'Berlaku sampai',
                cell: ({ row }) => formatDate(row.original.expires_at),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.status === 'ACTIVE'
                                ? 'success'
                                : 'secondary'
                        }
                    >
                        {row.original.status === 'ACTIVE'
                            ? 'Aktif'
                            : 'Dicabut / kedaluwarsa'}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                cell: ({ row }) => (
                    <DataTableRowActions
                        actions={[
                            {
                                label: 'Cabut penugasan',
                                icon: UserRoundCog,
                                destructive: true,
                                hidden: row.original.status !== 'ACTIVE',
                                confirmTitle: 'Cabut penugasan reviewer?',
                                confirmDescription: `${row.original.reviewer} tidak lagi dapat mereview scope ${row.original.scope_label} pada proyek ini.`,
                                confirmLabel: 'Cabut penugasan',
                                onSelect: () =>
                                    router.patch(
                                        revoke.url(row.original.id),
                                        {},
                                        { preserveScroll: true },
                                    ),
                            },
                        ]}
                    />
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Penugasan Reviewer" />
            <PageHeader
                breadcrumbs={[
                    { title: 'Dashboard', href: dashboard() },
                    { title: 'Penugasan Reviewer', href: index() },
                ]}
                title="Penugasan Reviewer"
                description="Tentukan reviewer untuk proyek dan scope tertentu. Permission akun tetap menjadi syarat review."
                actions={
                    <Button onClick={openDialog}>
                        <Plus aria-hidden="true" />
                        Tugaskan Reviewer
                    </Button>
                }
            />

            <Card className="overflow-hidden p-0">
                <DataTable
                    columns={columns}
                    data={assignments.data}
                    page={assignments.current_page}
                    pageSize={assignments.per_page}
                    total={assignments.total}
                    onPageChange={changePage}
                    search={searchValue}
                    onSearchChange={setSearchValue}
                    searchPlaceholder="Cari proyek atau reviewer..."
                    filters={
                        <Select
                            value={filters.status}
                            onValueChange={(status) =>
                                status &&
                                router.get(
                                    index.url({
                                        query: { ...filters, status, page: 1 },
                                    }),
                                    {},
                                    {
                                        preserveState: true,
                                        preserveScroll: true,
                                        only: ['assignments', 'filters'],
                                    },
                                )
                            }
                            items={[
                                { value: 'ACTIVE', label: 'Penugasan aktif' },
                                {
                                    value: 'REVOKED',
                                    label: 'Dicabut / kedaluwarsa',
                                },
                                { value: 'all', label: 'Semua status' },
                            ]}
                        >
                            <SelectTrigger className="w-full sm:w-52">
                                <SelectValue placeholder="Semua status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="ACTIVE">
                                    Penugasan aktif
                                </SelectItem>
                                <SelectItem value="REVOKED">
                                    Dicabut / kedaluwarsa
                                </SelectItem>
                                <SelectItem value="all">
                                    Semua status
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    }
                    emptyTitle="Belum ada penugasan reviewer"
                    emptyDescription="Tugaskan reviewer untuk menentukan siapa yang dapat memproses review proyek."
                />
            </Card>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Tugaskan Reviewer</DialogTitle>
                        <DialogDescription>
                            Reviewer hanya mendapat akses pada proyek dan scope
                            yang dipilih, jika permission akunnya mengizinkan.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitAssignment} className="grid gap-5">
                        <FormField
                            id="review-assignment-project"
                            label="Proyek"
                            required
                            error={form.errors.project_id}
                        >
                            <Combobox
                                items={availableProjects}
                                value={projectValue}
                                onValueChange={(
                                    project: ProjectOption | null,
                                ) => {
                                    setProjectValue(project);
                                    form.setData(
                                        'project_id',
                                        project?.value ?? '',
                                    );
                                }}
                                itemToStringLabel={(project: ProjectOption) =>
                                    project.label
                                }
                                isItemEqualToValue={(
                                    a: ProjectOption,
                                    b: ProjectOption,
                                ) => a.value === b.value}
                            >
                                <ComboboxInput
                                    placeholder="Cari kode atau nama proyek"
                                    aria-label="Pilih proyek"
                                    showClear
                                />
                                <ComboboxContent>
                                    <ComboboxEmpty>
                                        Proyek tidak ditemukan.
                                    </ComboboxEmpty>
                                    <ComboboxList>
                                        {(project: ProjectOption) => (
                                            <ComboboxItem
                                                key={project.value}
                                                value={project}
                                            >
                                                <span className="flex min-w-0 flex-col">
                                                    <span className="truncate">
                                                        {project.label}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {project.status ===
                                                        'APPROVED'
                                                            ? 'Proyek disetujui'
                                                            : 'Proposal menunggu proses'}
                                                    </span>
                                                </span>
                                            </ComboboxItem>
                                        )}
                                    </ComboboxList>
                                </ComboboxContent>
                            </Combobox>
                        </FormField>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <FormField
                                id="review-assignment-scope"
                                label="Scope review"
                                required
                                error={form.errors.scope}
                            >
                                <Select
                                    value={form.data.scope}
                                    onValueChange={(scope) =>
                                        scope && updateScope(scope)
                                    }
                                    items={scopeOptions}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Pilih scope" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {scopeOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                id="review-assignment-reviewer"
                                label="Reviewer"
                                required
                                error={form.errors.reviewer_id}
                            >
                                <Combobox
                                    items={availableReviewers}
                                    value={reviewerValue}
                                    onValueChange={(
                                        reviewer: ReviewerOption | null,
                                    ) => {
                                        setReviewerValue(reviewer);
                                        form.setData(
                                            'reviewer_id',
                                            reviewer?.value ?? '',
                                        );
                                    }}
                                    itemToStringLabel={(
                                        reviewer: ReviewerOption,
                                    ) => reviewer.label}
                                    isItemEqualToValue={(
                                        a: ReviewerOption,
                                        b: ReviewerOption,
                                    ) => a.value === b.value}
                                >
                                    <ComboboxInput
                                        placeholder="Cari nama atau email reviewer"
                                        aria-label="Pilih reviewer"
                                        showClear
                                    />
                                    <ComboboxContent>
                                        <ComboboxEmpty>
                                            Tidak ada reviewer dengan permission
                                            scope ini.
                                        </ComboboxEmpty>
                                        <ComboboxList>
                                            {(reviewer: ReviewerOption) => (
                                                <ComboboxItem
                                                    key={reviewer.value}
                                                    value={reviewer}
                                                >
                                                    {reviewer.label}
                                                </ComboboxItem>
                                            )}
                                        </ComboboxList>
                                    </ComboboxContent>
                                </Combobox>
                            </FormField>
                        </div>

                        <FormField
                            id="review-assignment-expiry"
                            label="Berlaku sampai"
                            hint="Kosongkan jika penugasan berlaku tanpa batas waktu."
                            error={form.errors.expires_at}
                        >
                            <DatePicker
                                value={form.data.expires_at || null}
                                onValueChange={(date) =>
                                    form.setData('expires_at', date ?? '')
                                }
                                placeholder="Tanpa batas waktu"
                            />
                        </FormField>

                        <div className="flex items-start gap-2 rounded-lg border bg-muted/30 p-3 text-xs text-muted-foreground">
                            <CalendarClock
                                aria-hidden="true"
                                className="mt-0.5 size-4 shrink-0"
                            />
                            <p>
                                Proposal dapat ditugaskan saat diajukan. Review
                                8 Step, bi-weekly, dan scoring hanya tersedia
                                setelah proposal disetujui.
                            </p>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialogOpen(false)}
                                disabled={form.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    !form.data.project_id ||
                                    !form.data.reviewer_id
                                }
                            >
                                {form.processing && <Spinner />}
                                {form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Penugasan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
