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
import { dashboard } from '@/routes';
import { index as batchesIndex } from '@/routes/admin/batches';
import {
    store as schedulesStore,
    update as schedulesUpdate,
} from '@/routes/admin/batches/schedules';

type Schedule = {
    id: number;
    schedule_type: 'BIWEEKLY' | 'MENTORING';
    sequence_no: number;
    scheduled_on: string;
};

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
            <div>
                <label className="sr-only" htmlFor={`schedule-${schedule.id}`}>
                    Tanggal jadwal
                </label>
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
                variant="default"
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
                            className="grid gap-4 sm:grid-cols-[1fr_120px_170px_auto] sm:items-end"
                        >
                            <div>
                                <label
                                    htmlFor="schedule-type"
                                    className="mb-1 block text-sm font-medium"
                                >
                                    Jenis
                                </label>
                                <select
                                    id="schedule-type"
                                    className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                    value={form.data.schedule_type}
                                    onChange={(event) =>
                                        form.setData(
                                            'schedule_type',
                                            event.target.value as
                                                | 'BIWEEKLY'
                                                | 'MENTORING',
                                        )
                                    }
                                >
                                    <option value="BIWEEKLY">Bi-weekly</option>
                                    <option value="MENTORING">Mentoring</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    htmlFor="sequence-no"
                                    className="mb-1 block text-sm font-medium"
                                >
                                    Nomor
                                </label>
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
                                    required
                                />
                            </div>
                            <div>
                                <label
                                    htmlFor="scheduled-on"
                                    className="mb-1 block text-sm font-medium"
                                >
                                    Tanggal
                                </label>
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
                            <Button type="submit" disabled={form.processing}>
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
