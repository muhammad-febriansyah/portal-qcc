import { router, useForm } from '@inertiajs/react';
import { Camera, Trash2, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { toast } from 'sonner';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { useInitials } from '@/hooks/use-initials';
import { destroy, update } from '@/routes/profile/avatar';

const MAX_SIZE_BYTES = 2 * 1024 * 1024;
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

type Props = {
    name: string;
    avatarUrl: string | null;
};

function formatSize(bytes: number): string {
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function AvatarUploader({ name, avatarUrl }: Props) {
    const getInitials = useInitials();
    const inputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [isRemoving, setIsRemoving] = useState(false);
    const [isConfirmingRemove, setIsConfirmingRemove] = useState(false);
    const form = useForm<{ avatar: File | null }>({ avatar: null });

    useEffect(() => {
        return () => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }
        };
    }, [previewUrl]);

    function chooseFile(event: ChangeEvent<HTMLInputElement>): void {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        if (!ACCEPTED_TYPES.includes(file.type)) {
            toast.error('Format foto harus JPG, PNG, atau WebP.');

            return;
        }

        if (file.size > MAX_SIZE_BYTES) {
            toast.error('Ukuran foto maksimal 2 MB.');

            return;
        }

        form.clearErrors();
        form.setData('avatar', file);
        setPreviewUrl(URL.createObjectURL(file));
    }

    function cancelSelection(): void {
        form.reset();
        form.clearErrors();
        setPreviewUrl(null);
    }

    function saveAvatar(): void {
        form.post(update.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setPreviewUrl(null);
            },
        });
    }

    function removeAvatar(): void {
        router.delete(destroy.url(), {
            preserveScroll: true,
            onStart: () => setIsRemoving(true),
            onFinish: () => {
                setIsRemoving(false);
                setIsConfirmingRemove(false);
            },
        });
    }

    const selectedFile = form.data.avatar;

    return (
        <div className="flex flex-col items-center gap-4">
            <div className="relative">
                <Avatar className="size-28 text-2xl">
                    <AvatarImage
                        src={previewUrl ?? avatarUrl ?? undefined}
                        alt={`Foto profil ${name}`}
                    />
                    <AvatarFallback className="bg-primary/10 text-2xl font-semibold text-primary">
                        {getInitials(name)}
                    </AvatarFallback>
                </Avatar>
                <button
                    type="button"
                    onClick={() => inputRef.current?.click()}
                    aria-label="Pilih foto profil"
                    className="absolute right-0 bottom-0 flex size-9 items-center justify-center rounded-full border-2 border-card bg-primary text-primary-foreground transition-colors outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <Camera className="size-4" aria-hidden="true" />
                </button>
                <input
                    ref={inputRef}
                    type="file"
                    accept={ACCEPTED_TYPES.join(',')}
                    className="sr-only"
                    tabIndex={-1}
                    onChange={chooseFile}
                />
            </div>

            {selectedFile ? (
                <div className="grid w-full gap-3">
                    <div className="text-center text-xs text-muted-foreground">
                        <p className="truncate font-medium text-foreground">
                            {selectedFile.name}
                        </p>
                        <p>{formatSize(selectedFile.size)}</p>
                    </div>
                    {form.progress && (
                        <Progress value={form.progress.percentage ?? 0} />
                    )}
                    {form.errors.avatar && (
                        <p
                            role="alert"
                            className="text-center text-sm text-destructive"
                        >
                            {form.errors.avatar}
                        </p>
                    )}
                    <div className="flex justify-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 px-4 text-sm"
                            onClick={cancelSelection}
                            disabled={form.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            className="h-10 px-4 text-sm"
                            onClick={saveAvatar}
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <Upload aria-hidden="true" />
                            )}
                            Simpan Foto
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="flex flex-wrap justify-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        className="h-10 px-4 text-sm"
                        onClick={() => inputRef.current?.click()}
                    >
                        <Camera aria-hidden="true" />
                        {avatarUrl ? 'Ganti Foto' : 'Unggah Foto'}
                    </Button>
                    {avatarUrl && (
                        <Button
                            type="button"
                            variant="ghost"
                            className="h-10 px-4 text-sm text-destructive hover:text-destructive"
                            onClick={() => setIsConfirmingRemove(true)}
                        >
                            <Trash2 aria-hidden="true" />
                            Hapus
                        </Button>
                    )}
                </div>
            )}

            <AlertDialog
                open={isConfirmingRemove}
                onOpenChange={setIsConfirmingRemove}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus foto profil?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Foto akan dihapus dan diganti dengan inisial nama
                            Anda.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isRemoving}>
                            Batal
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant="destructive"
                            onClick={removeAvatar}
                            disabled={isRemoving}
                        >
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
