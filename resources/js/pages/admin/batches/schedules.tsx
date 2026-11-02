import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import type { FormEvent } from 'react';
import { DatePicker } from '@/components/date-picker';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { index as batchesIndex } from '@/routes/admin/batches';
import {
    store as schedulesStore,
    update as schedulesUpdate,
} from '@/routes/admin/batches/schedules';

type ScheduleType = 'BIWEEKLY' | 'MENTORING';

type Schedule = {
    id: number;
    schedule_type: ScheduleType;
    sequence_no: number;
    scheduled_on: string;
};

const scheduleTypeItems: { value: ScheduleType; label: string }[] = [
    { value: 'BIWEEKLY', label: 'Bi-weekly' },
    { value: 'MENTORING', label: 'Mentoring' },
];

type Props = {
    batch: { id: number; name: string; starts_on: string; ends_on: string };
    schedules: Schedule[];
};

function ScheduleRow({
    batchId,
    schedule,
}: {
    batchId: number;
    schedule: Schedule;
}) {
    const form = useForm({ scheduled_on: schedule.scheduled_on });

    function save(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.patch(
            schedulesUpdate.url({ batch: batchId, schedule: schedule.id }),
            {
                preserveScroll: true,
            },
        );
    }

    return (
        <form
            onSubmit={save}
            className="flex flex-wrap items-end gap-3 border-b py-3 last:border-0"
        >
            <div className="min-w-44 flex-1">
                <p className="text-sm font-medium">
                    {schedule.schedule_type === 'BIWEEKLY'
                        ? 'Bi-weekly periode'
                        : 'Mentoring sesi'}{' '}
                    {schedule.sequence_no}
                </p>
                <p className="text-xs text-muted-foreground">
                    {schedule.schedule_type === 'BIWEEKLY'
                        ? 'Tenggat pengajuan laporan'
                        : 'Tanggal sesi mentoring'}
                </p>
            </div>
            <div className="w-full sm:w-[200px]">
                <Label className="sr-only" htmlFor={`schedule-${schedule.id}`}>
                    Tanggal jadwal
                </Label>
                <DatePicker
                    id={`schedule-${schedule.id}`}
                    value={form.data.scheduled_on}
                    onValueChange={(value) =>
                        form.setData('scheduled_on', value ?? '')
                    }
                    required
                    aria-invalid={Boolean(form.errors.scheduled_on)}
                    className="h-10"
                />
                {form.errors.scheduled_on && (
                    <p className="mt-1 text-xs text-destructive">
                        {form.errors.scheduled_on}
                    </p>
                )}
            </div>
            <Button
                type="submit"
                className="h-10 px-4 text-sm"
                disabled={form.processing || !form.isDirty}
            >
                Simpan tanggal
            </Button>
        </form>
    );
}

export default function BatchSchedules({ batch, schedules }: Props) {
    const form = useForm<{
        schedule_type: 'BIWEEKLY' | 'MENTORING';
        sequence_no: number;
        scheduled_on: string;
    }>({ schedule_type: 'BIWEEKLY', sequence_no: 1, scheduled_on: '' });

    function add(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post(schedulesStore.url(batch.id), {
            preserveScroll: true,
            onSuccess: () => form.reset('scheduled_on'),
        });
    }

    return (
        <>
            <Head title={`Jadwal ${batch.name}`} />
            <main className="mx-auto w-full max-w-[1100px] flex-1 space-y-6 px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Batch QCC', href: batchesIndex() },
                        { title: 'Jadwal', href: '#' },
                    ]}
                    title={`Jadwal ${batch.name}`}
                    description={`Periode batch ${batch.starts_on}–${batch.ends_on}. Tanggal berikut diisi Admin; sistem tidak membuat tenggat otomatis.`}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CalendarDays
                                className="size-4"
                                aria-hidden="true"
                            />
                            Tambah jadwal
                        </CardTitle>
                        <CardDescription>
                            Pengingat dikirim untuk proyek aktif dalam batch
                            ini, termasuk saat laporan belum dibuat. Jika
                            laporan draft sudah ada, tanggal pada laporan
                            menjadi acuan pengingat.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={add}
                            className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_120px_200px_auto] sm:items-end"
                        >
                            <div className="grid gap-2.5">
                                <Label htmlFor="schedule-type">Jenis</Label>
                                <Select
                                    value={form.data.schedule_type}
                                    items={scheduleTypeItems}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'schedule_type',
                                            (value ??
                                                'BIWEEKLY') as ScheduleType,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="schedule-type"
                                        className="h-10 w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {scheduleTypeItems.map((item) => (
                                            <SelectItem
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2.5">
                                <Label htmlFor="sequence-no">Nomor</Label>
                                <Input
                                    id="sequence-no"
                                    type="number"
                                    min={1}
                                    max={52}
                                    value={form.data.sequence_no}
                                    onChange={(event) =>
                                        form.setData(
                                            'sequence_no',
                                            Number(event.target.value),
                                        )
                                    }
                                    className="h-10 text-sm"
                                    required
                                />
                            </div>
                            <div className="grid gap-2.5">
                                <Label htmlFor="scheduled-on">Tanggal</Label>
                                <DatePicker
                                    id="scheduled-on"
                                    value={form.data.scheduled_on}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'scheduled_on',
                                            value ?? '',
                                        )
                                    }
                                    required
                                    aria-invalid={Boolean(
                                        form.errors.scheduled_on,
                                    )}
                                    className="h-10"
                                />
                            </div>
                            <Button
                                type="submit"
                                className="h-10 px-4 text-sm"
                                disabled={form.processing}
                            >
                                Tambah
                            </Button>
                        </form>
                        {Object.values(form.errors).map((error, index) => (
                            <p
                                key={index}
                                className="mt-2 text-xs text-destructive"
                            >
                                {error}
                            </p>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Jadwal tersimpan
                        </CardTitle>
                        <CardDescription>
                            Tanggal dapat diubah. Riwayat perubahan tercatat
                            dalam audit.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {schedules.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">
                                Belum ada jadwal untuk batch ini.
                            </p>
                        ) : (
                            schedules.map((schedule) => (
                                <ScheduleRow
                                    key={schedule.id}
                                    batchId={batch.id}
                                    schedule={schedule}
                                />
                            ))
                        )}
                    </CardContent>
                </Card>
                <Link
                    href={batchesIndex()}
                    className="text-sm text-primary hover:underline"
                >
                    Kembali ke daftar batch
                </Link>
            </main>
        </>
    );
}
