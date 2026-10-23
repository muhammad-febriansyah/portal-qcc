import { Head, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { AlertTriangle, PencilLine, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { PageHeader } from '@/components/page-header';
import { CsvExportButton } from '@/components/csv-export-button';
import { EmptyState } from '@/components/ui-states';
import {
    ActionDialog,
    RiskDialog,
} from '@/components/project-risk/risk-dialogs';
import type {
    ActionRow,
    Option,
    RiskRow,
} from '@/components/project-risk/risk-dialogs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { BadgeVariant } from '@/lib/badge-variants';
import { dashboard } from '@/routes';
import {
    destroy,
    exportMethod as exportRisksCsv,
    index,
} from '@/routes/projects/risks';
import { destroy as destroyAction } from '@/routes/projects/risks/actions';

type Filters = {
    tab: 'risks' | 'actions';
    type: string;
    status: string;
    priority: string;
    pic: string;
    overdue: boolean;
};

type Props = {
    project: { id: number; code: string; title: string };
    filters: Filters;
    risks: RiskRow[];
    actions: ActionRow[];
    summary: {
        open_risks: number;
        open_issues: number;
        overdue_actions: number;
    };
    options: {
        types: Option[];
        likelihoods: Option[];
        priorities: Option[];
        riskStatuses: Option[];
        actionStatuses: Option[];
    };
    members: Option[];
    can_manage: boolean;
};

const priorityVariants: Record<string, BadgeVariant> = {
    LOW: 'secondary',
    MEDIUM: 'info',
    HIGH: 'warning',
    CRITICAL: 'destructive',
};

const statusVariants: Record<string, BadgeVariant> = {
    OPEN: 'info',
    IN_PROGRESS: 'violet',
    BLOCKED: 'warning',
    COMPLETED: 'success',
    RESOLVED: 'success',
    CLOSED: 'secondary',
    CANCELLED: 'secondary',
};

function label(options: Option[], value: string): string {
    return options.find((option) => option.value === value)?.label ?? value;
}

function Due({ date, overdue }: { date: string | null; overdue: boolean }) {
    if (!date) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className={overdue ? 'font-medium text-destructive' : ''}>
            {format(parseISO(date), 'd MMM yyyy', { locale: indonesianLocale })}
            {overdue && ' · lewat'}
        </span>
    );
}

function FilterSelect({
    value,
    onChange,
    label: ariaLabel,
    allLabel,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    label: string;
    allLabel: string;
    options: Option[];
}) {
    const items = [{ value: 'all', label: allLabel }, ...options];

    return (
        <Select
            value={value}
            onValueChange={(next) => onChange(next ?? 'all')}
            items={items}
        >
            <SelectTrigger aria-label={ariaLabel} className="w-full sm:w-44">
                <SelectValue placeholder={allLabel} />
            </SelectTrigger>
            <SelectContent>
                {items.map((item) => (
                    <SelectItem key={item.value} value={item.value}>
                        {item.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export default function ProjectRisks({
    project,
    filters,
    risks,
    actions,
    summary,
    options,
    members,
    can_manage: canManage,
}: Props) {
    const [riskDialog, setRiskDialog] = useState<{
        open: boolean;
        editing: RiskRow | null;
    }>({ open: false, editing: null });
    const [actionDialog, setActionDialog] = useState<{
        open: boolean;
        editing: ActionRow | null;
    }>({ open: false, editing: null });

    function visit(next: Partial<Filters>): void {
        const query = { ...filters, ...next };
        router.get(
            index.url(
                { project: project.id },
                { query: { ...query, overdue: query.overdue ? 1 : undefined } },
            ),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['risks', 'actions', 'filters'],
            },
        );
    }

    const statusOptions =
        filters.tab === 'risks' ? options.riskStatuses : options.actionStatuses;
    const riskChoices = risks.map((risk) => ({
        value: String(risk.id),
        label: `${label(options.types, risk.type)} · ${risk.title}`,
    }));

    return (
        <>
            <Head title="Risiko & Tindakan" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        {
                            title: 'Risiko & Tindakan',
                            href: index.url({ project: project.id }),
                        },
                    ]}
                    title="Risiko, Isu & Tindakan"
                    description={`${project.code} · ${project.title}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <CsvExportButton
                                href={exportRisksCsv.url(
                                    { project: project.id },
                                    { query: filters },
                                )}
                            />
                            {canManage ? (
                                <Button
                                    className="h-10 px-4 text-sm"
                                    onClick={() =>
                                        filters.tab === 'risks'
                                            ? setRiskDialog({
                                                  open: true,
                                                  editing: null,
                                              })
                                            : setActionDialog({
                                                  open: true,
                                                  editing: null,
                                              })
                                    }
                                >
                                    <Plus
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    {filters.tab === 'risks'
                                        ? 'Catat Risiko / Isu'
                                        : 'Catat Tindakan'}
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                    {[
                        { label: 'Risiko terbuka', value: summary.open_risks },
                        { label: 'Isu terbuka', value: summary.open_issues },
                        {
                            label: 'Tindakan lewat tenggat',
                            value: summary.overdue_actions,
                            alert: summary.overdue_actions > 0,
                        },
                    ].map((item) => (
                        <Card key={item.label} className="gap-0 py-0">
                            <CardContent className="grid gap-1 p-5">
                                <p className="text-[13px] font-medium text-muted-foreground">
                                    {item.label}
                                </p>
                                <p
                                    className={`text-3xl leading-none font-semibold ${item.alert ? 'text-destructive' : ''}`}
                                >
                                    {item.value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Tabs
                    value={filters.tab}
                    onValueChange={(value) =>
                        visit({
                            tab: value as Filters['tab'],
                            status: 'all',
                            pic: 'all',
                            overdue: false,
                        })
                    }
                    className="mb-4"
                >
                    <TabsList aria-label="Pilih daftar">
                        <TabsTrigger value="risks">Risiko & Isu</TabsTrigger>
                        <TabsTrigger value="actions">Tindakan</TabsTrigger>
                    </TabsList>
                </Tabs>

                <Card className="gap-0 py-0">
                    <CardContent className="grid gap-4 p-4">
                        <div className="flex flex-wrap items-center gap-3">
                            {filters.tab === 'risks' && (
                                <FilterSelect
                                    value={filters.type}
                                    onChange={(type) => visit({ type })}
                                    label="Filter jenis"
                                    allLabel="Semua jenis"
                                    options={options.types}
                                />
                            )}
                            <FilterSelect
                                value={filters.status}
                                onChange={(status) => visit({ status })}
                                label="Filter status"
                                allLabel="Semua status"
                                options={statusOptions}
                            />
                            {filters.tab === 'risks' && (
                                <FilterSelect
                                    value={filters.priority}
                                    onChange={(priority) => visit({ priority })}
                                    label="Filter prioritas"
                                    allLabel="Semua prioritas"
                                    options={options.priorities}
                                />
                            )}
                            <FilterSelect
                                value={filters.pic}
                                onChange={(pic) => visit({ pic })}
                                label="Filter PIC"
                                allLabel="Semua PIC"
                                options={members}
                            />
                            <Label className="flex items-center gap-2 text-[13px] font-normal">
                                <Checkbox
                                    checked={filters.overdue}
                                    onCheckedChange={(checked) =>
                                        visit({ overdue: checked === true })
                                    }
                                />
                                Lewat tenggat saja
                            </Label>
                        </div>

                        {filters.tab === 'risks' ? (
                            risks.length === 0 ? (
                                <div className="rounded-lg border border-dashed">
                                    <EmptyState
                                        icon={AlertTriangle}
                                        title="Belum ada risiko atau isu"
                                        description="Risiko dan isu yang dicatat untuk proyek ini akan muncul di sini."
                                    />
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Jenis</TableHead>
                                            <TableHead>Judul</TableHead>
                                            <TableHead>Prioritas</TableHead>
                                            <TableHead>PIC</TableHead>
                                            <TableHead>Tenggat</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="w-10">
                                                <span className="sr-only">
                                                    Aksi
                                                </span>
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {risks.map((risk) => (
                                            <TableRow key={risk.id}>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            risk.type ===
                                                            'ISSUE'
                                                                ? 'warning'
                                                                : 'info'
                                                        }
                                                    >
                                                        {label(
                                                            options.types,
                                                            risk.type,
                                                        )}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="max-w-sm">
                                                    <p className="font-medium">
                                                        {risk.title}
                                                    </p>
                                                    {risk.mitigation && (
                                                        <p className="line-clamp-2 text-xs text-muted-foreground">
                                                            Mitigasi:{' '}
                                                            {risk.mitigation}
                                                        </p>
                                                    )}
                                                    {risk.history.length >
                                                        0 && (
                                                        <details className="mt-2 rounded-md bg-muted/60 px-2.5 py-2 text-xs">
                                                            <summary className="cursor-pointer font-medium text-primary">
                                                                Riwayat
                                                                perubahan (
                                                                {
                                                                    risk.history
                                                                        .length
                                                                }
                                                                )
                                                            </summary>
                                                            <ol className="mt-2 grid gap-2 border-l pl-3">
                                                                {risk.history.map(
                                                                    (entry) => (
                                                                        <li
                                                                            key={
                                                                                entry.id
                                                                            }
                                                                            className="grid gap-0.5"
                                                                        >
                                                                            <span className="font-medium text-foreground">
                                                                                {
                                                                                    entry.event
                                                                                }
                                                                            </span>
                                                                            <span className="text-muted-foreground">
                                                                                {
                                                                                    entry.actor
                                                                                }
                                                                                {entry.occurred_at
                                                                                    ? ` · ${format(parseISO(entry.occurred_at), 'd MMM yyyy, HH:mm', { locale: indonesianLocale })}`
                                                                                    : ''}
                                                                            </span>
                                                                            {[
                                                                                ...new Set(
                                                                                    [
                                                                                        ...Object.keys(
                                                                                            entry.before,
                                                                                        ),
                                                                                        ...Object.keys(
                                                                                            entry.after,
                                                                                        ),
                                                                                    ],
                                                                                ),
                                                                            ].map(
                                                                                (
                                                                                    key,
                                                                                ) => {
                                                                                    const previous =
                                                                                        entry
                                                                                            .before[
                                                                                            key
                                                                                        ];
                                                                                    const value =
                                                                                        entry
                                                                                            .after[
                                                                                            key
                                                                                        ];
                                                                                    if (
                                                                                        previous ===
                                                                                        value
                                                                                    )
                                                                                        return null;
                                                                                    const labels: Record<
                                                                                        string,
                                                                                        string
                                                                                    > =
                                                                                        {
                                                                                            type: 'Jenis',
                                                                                            title: 'Judul',
                                                                                            description:
                                                                                                'Deskripsi',
                                                                                            impact: 'Dampak',
                                                                                            priority:
                                                                                                'Prioritas',
                                                                                            likelihood:
                                                                                                'Kemungkinan',
                                                                                            mitigation:
                                                                                                'Mitigasi',
                                                                                            pic_id: 'PIC',
                                                                                            due_date:
                                                                                                'Tenggat',
                                                                                            status: 'Status',
                                                                                            source_type:
                                                                                                'Sumber',
                                                                                            source_id:
                                                                                                'ID sumber',
                                                                                        };
                                                                                    const valueLabel =
                                                                                        (
                                                                                            raw: unknown,
                                                                                        ) => {
                                                                                            if (
                                                                                                raw ===
                                                                                                    null ||
                                                                                                raw ===
                                                                                                    undefined ||
                                                                                                raw ===
                                                                                                    ''
                                                                                            )
                                                                                                return '—';
                                                                                            if (
                                                                                                typeof raw !==
                                                                                                    'string' &&
                                                                                                typeof raw !==
                                                                                                    'number'
                                                                                            ) {
                                                                                                return JSON.stringify(
                                                                                                    raw,
                                                                                                );
                                                                                            }
                                                                                            if (
                                                                                                key ===
                                                                                                'pic_id'
                                                                                            )
                                                                                                return (
                                                                                                    members.find(
                                                                                                        (
                                                                                                            member,
                                                                                                        ) =>
                                                                                                            member.value ===
                                                                                                            String(
                                                                                                                raw,
                                                                                                            ),
                                                                                                    )
                                                                                                        ?.label ??
                                                                                                    `Anggota #${raw}`
                                                                                                );
                                                                                            const choices =
                                                                                                key ===
                                                                                                'status'
                                                                                                    ? [
                                                                                                          ...options.riskStatuses,
                                                                                                          ...options.actionStatuses,
                                                                                                      ]
                                                                                                    : key ===
                                                                                                        'type'
                                                                                                      ? options.types
                                                                                                      : key ===
                                                                                                          'priority'
                                                                                                        ? options.priorities
                                                                                                        : key ===
                                                                                                            'likelihood'
                                                                                                          ? options.likelihoods
                                                                                                          : [];
                                                                                            return (
                                                                                                choices.find(
                                                                                                    (
                                                                                                        choice,
                                                                                                    ) =>
                                                                                                        choice.value ===
                                                                                                        String(
                                                                                                            raw,
                                                                                                        ),
                                                                                                )
                                                                                                    ?.label ??
                                                                                                String(
                                                                                                    raw,
                                                                                                )
                                                                                            );
                                                                                        };
                                                                                    return (
                                                                                        <span
                                                                                            key={
                                                                                                key
                                                                                            }
                                                                                            className="text-muted-foreground"
                                                                                        >
                                                                                            {labels[
                                                                                                key
                                                                                            ] ??
                                                                                                key.replaceAll(
                                                                                                    '_',
                                                                                                    ' ',
                                                                                                )}
                                                                                            :{' '}
                                                                                            {valueLabel(
                                                                                                previous,
                                                                                            )}{' '}
                                                                                            →{' '}
                                                                                            {valueLabel(
                                                                                                value,
                                                                                            )}
                                                                                        </span>
                                                                                    );
                                                                                },
                                                                            )}
                                                                            {entry.reason && (
                                                                                <span className="text-muted-foreground">
                                                                                    Catatan:{' '}
                                                                                    {
                                                                                        entry.reason
                                                                                    }
                                                                                </span>
                                                                            )}
                                                                        </li>
                                                                    ),
                                                                )}
                                                            </ol>
                                                        </details>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            priorityVariants[
                                                                risk.priority
                                                            ] ?? 'secondary'
                                                        }
                                                    >
                                                        {label(
                                                            options.priorities,
                                                            risk.priority,
                                                        )}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {risk.pic}
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap">
                                                    <Due
                                                        date={risk.due_date}
                                                        overdue={
                                                            risk.is_overdue
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            statusVariants[
                                                                risk.status
                                                            ] ?? 'secondary'
                                                        }
                                                    >
                                                        {label(
                                                            options.riskStatuses,
                                                            risk.status,
                                                        )}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {canManage && (
                                                        <DataTableRowActions
                                                            label={`Aksi untuk ${risk.title}`}
                                                            actions={[
                                                                {
                                                                    label: 'Ubah',
                                                                    icon: PencilLine,
                                                                    onSelect:
                                                                        () =>
                                                                            setRiskDialog(
                                                                                {
                                                                                    open: true,
                                                                                    editing:
                                                                                        risk,
                                                                                },
                                                                            ),
                                                                },
                                                                {
                                                                    label: 'Hapus',
                                                                    icon: Trash2,
                                                                    destructive: true,
                                                                    confirmTitle:
                                                                        'Hapus risiko atau isu?',
                                                                    confirmDescription:
                                                                        'Tindakan yang terkait tetap ada, hanya tautannya dilepas.',
                                                                    confirmLabel:
                                                                        'Hapus',
                                                                    onSelect:
                                                                        () =>
                                                                            router.delete(
                                                                                destroy.url(
                                                                                    {
                                                                                        project:
                                                                                            project.id,
                                                                                        risk: risk.id,
                                                                                    },
                                                                                ),
                                                                                {
                                                                                    preserveScroll: true,
                                                                                },
                                                                            ),
                                                                },
                                                            ]}
                                                        />
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )
                        ) : actions.length === 0 ? (
                            <div className="rounded-lg border border-dashed">
                                <EmptyState
                                    icon={AlertTriangle}
                                    title="Belum ada tindakan"
                                    description="Tindakan dengan PIC dan tenggat akan muncul di sini."
                                />
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Tindakan</TableHead>
                                        <TableHead>PIC</TableHead>
                                        <TableHead>Tenggat</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-10">
                                            <span className="sr-only">
                                                Aksi
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {actions.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="max-w-sm">
                                                <p className="flex items-center gap-1.5 font-medium">
                                                    {item.is_overdue && (
                                                        <AlertTriangle
                                                            className="size-4 text-destructive"
                                                            aria-label="Lewat tenggat"
                                                        />
                                                    )}
                                                    {item.title}
                                                </p>
                                                {item.source_type ===
                                                    'RISK' && (
                                                    <p className="text-xs text-muted-foreground">
                                                        Terkait risiko atau isu
                                                    </p>
                                                )}
                                            </TableCell>
                                            <TableCell>{item.pic}</TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                <Due
                                                    date={item.due_date}
                                                    overdue={item.is_overdue}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        statusVariants[
                                                            item.status
                                                        ] ?? 'secondary'
                                                    }
                                                >
                                                    {label(
                                                        options.actionStatuses,
                                                        item.status,
                                                    )}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {canManage && (
                                                    <DataTableRowActions
                                                        label={`Aksi untuk ${item.title}`}
                                                        actions={[
                                                            {
                                                                label: 'Ubah',
                                                                icon: PencilLine,
                                                                onSelect: () =>
                                                                    setActionDialog(
                                                                        {
                                                                            open: true,
                                                                            editing:
                                                                                item,
                                                                        },
                                                                    ),
                                                            },
                                                            {
                                                                label: 'Hapus',
                                                                icon: Trash2,
                                                                destructive: true,
                                                                confirmTitle:
                                                                    'Hapus tindakan?',
                                                                confirmDescription:
                                                                    'Tindakan ini akan dihapus dari daftar.',
                                                                confirmLabel:
                                                                    'Hapus',
                                                                onSelect: () =>
                                                                    router.delete(
                                                                        destroyAction.url(
                                                                            {
                                                                                project:
                                                                                    project.id,
                                                                                action: item.id,
                                                                            },
                                                                        ),
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    ),
                                                            },
                                                        ]}
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            <RiskDialog
                projectId={project.id}
                open={riskDialog.open}
                onOpenChange={(open) =>
                    setRiskDialog((current) => ({ ...current, open }))
                }
                editing={riskDialog.editing}
                options={options}
                members={members}
            />
            <ActionDialog
                projectId={project.id}
                open={actionDialog.open}
                onOpenChange={(open) =>
                    setActionDialog((current) => ({ ...current, open }))
                }
                editing={actionDialog.editing}
                statuses={options.actionStatuses}
                members={members}
                risks={riskChoices}
            />
        </>
    );
}
