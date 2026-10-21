import {
    AlertCircle,
    Ban,
    FileQuestion,
    Inbox,
    RefreshCw,
    type LucideIcon,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Spinner } from '@/components/ui/spinner';
import { Skeleton } from '@/components/ui/skeleton';

export function EmptyState({
    title = 'Belum ada data',
    description = 'Data akan tampil di sini setelah tersedia.',
    icon: Icon = Inbox,
    action,
}: {
    title?: string;
    description?: string;
    icon?: LucideIcon;
    action?: React.ReactNode;
}) {
    return (
        <Empty className="min-h-56">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Icon aria-hidden="true" />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
            {action && <EmptyContent>{action}</EmptyContent>}
        </Empty>
    );
}

export function LoadingState({ label = 'Memuat data' }: { label?: string }) {
    return (
        <div
            role="status"
            className="flex min-h-40 items-center justify-center gap-3 text-sm text-muted-foreground"
        >
            <Spinner aria-hidden="true" />
            {label}
            <span className="sr-only">Sedang memuat</span>
        </div>
    );
}

export function LoadingSkeleton({ rows = 4 }: { rows?: number }) {
    return (
        <div role="status" aria-label="Memuat konten" className="space-y-3">
            {Array.from({ length: rows }, (_, index) => (
                <Skeleton key={index} className="h-12 w-full" />
            ))}
        </div>
    );
}

export function ErrorState({
    title = 'Terjadi kendala',
    description = 'Coba muat ulang data.',
    onRetry,
}: {
    title?: string;
    description?: string;
    onRetry?: () => void;
}) {
    return (
        <Alert variant="destructive">
            <AlertCircle aria-hidden="true" />
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription className="flex flex-wrap items-center justify-between gap-3">
                {description}
                {onRetry && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onRetry}
                    >
                        <RefreshCw aria-hidden="true" />
                        Coba lagi
                    </Button>
                )}
            </AlertDescription>
        </Alert>
    );
}

export function ForbiddenState() {
    return (
        <EmptyState
            icon={Ban}
            title="Akses tidak diizinkan"
            description="Akun ini tidak memiliki izin untuk melihat halaman ini."
        />
    );
}

export function NotFoundState() {
    return (
        <EmptyState
            icon={FileQuestion}
            title="Halaman tidak ditemukan"
            description="Alamat halaman mungkin berubah atau sudah tidak tersedia."
        />
    );
}
