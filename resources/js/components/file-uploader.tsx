import {
    FileImage,
    FileText,
    Eye,
    RotateCcw,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

export type UploadItem = {
    id: string;
    file: File;
    status?: 'queued' | 'uploading' | 'processing' | 'done' | 'error';
    progress?: number;
    error?: string;
    previewUrl?: string;
};

type FileUploaderProps = {
    files: UploadItem[];
    onFilesAdded: (files: File[]) => void;
    onRemove: (file: UploadItem) => void;
    onCancel?: (file: UploadItem) => void;
    onRetry?: (file: UploadItem) => void;
    accept?: string;
    maxFiles?: number;
    maxFileSizeBytes?: number;
    disabled?: boolean;
    error?: string;
    id?: string;
};

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function FileUploader({
    files,
    onFilesAdded,
    onRemove,
    onCancel,
    onRetry,
    accept,
    maxFiles,
    maxFileSizeBytes,
    disabled,
    error,
    id = 'file-upload',
}: FileUploaderProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [localError, setLocalError] = useState<string>();
    const [preview, setPreview] = useState<UploadItem | null>(null);
    const objectUrls = useRef(new Map<string, string>());

    useEffect(
        () => () => {
            objectUrls.current.forEach((url) => URL.revokeObjectURL(url));
            objectUrls.current.clear();
        },
        [],
    );
    useEffect(() => {
        const activeIds = new Set(files.map((item) => item.id));
        objectUrls.current.forEach((url, fileId) => {
            if (!activeIds.has(fileId)) {
                URL.revokeObjectURL(url);
                objectUrls.current.delete(fileId);
            }
        });
    }, [files]);

    function addFiles(fileList: FileList | File[]): void {
        setLocalError(undefined);
        const incoming = Array.from(fileList);
        if (
            maxFiles !== undefined &&
            files.length + incoming.length > maxFiles
        ) {
            setLocalError(`Maksimal ${maxFiles} file.`);
            return;
        }
        if (
            maxFileSizeBytes !== undefined &&
            incoming.some((file) => file.size > maxFileSizeBytes)
        ) {
            setLocalError(
                `Ukuran setiap file maksimal ${formatBytes(maxFileSizeBytes)}.`,
            );
            return;
        }
        onFilesAdded(incoming);
        if (inputRef.current) inputRef.current.value = '';
    }

    function getPreviewUrl(item: UploadItem): string {
        if (item.previewUrl) return item.previewUrl;
        const existing = objectUrls.current.get(item.id);
        if (existing) return existing;
        const url = URL.createObjectURL(item.file);
        objectUrls.current.set(item.id, url);
        return url;
    }

    function removeFile(item: UploadItem): void {
        const objectUrl = objectUrls.current.get(item.id);
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrls.current.delete(item.id);
        }
        onRemove(item);
    }

    const message = error ?? localError;

    return (
        <div className="space-y-3">
            <input
                ref={inputRef}
                id={id}
                type="file"
                className="sr-only"
                accept={accept}
                multiple
                disabled={disabled}
                onChange={(event) =>
                    event.target.files && addFiles(event.target.files)
                }
                aria-describedby={message ? `${id}-error` : `${id}-hint`}
            />
            <div
                onDragEnter={(event) => {
                    event.preventDefault();
                    if (!disabled) setIsDragging(true);
                }}
                onDragOver={(event) => event.preventDefault()}
                onDragLeave={(event) => {
                    if (event.currentTarget === event.target)
                        setIsDragging(false);
                }}
                onDrop={(event) => {
                    event.preventDefault();
                    setIsDragging(false);
                    if (!disabled) addFiles(event.dataTransfer.files);
                }}
                className={cn(
                    'flex min-h-36 flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-5 text-center transition-colors',
                    isDragging && 'border-primary bg-primary/5',
                    disabled && 'opacity-60',
                )}
            >
                <Upload
                    aria-hidden="true"
                    className="size-5 text-muted-foreground"
                />
                <p className="text-sm font-medium">
                    Tarik file ke sini atau{' '}
                    <button
                        type="button"
                        className="text-primary underline underline-offset-4"
                        disabled={disabled}
                        onClick={() => inputRef.current?.click()}
                    >
                        pilih file
                    </button>
                </p>
                <p id={`${id}-hint`} className="text-xs text-muted-foreground">
                    {accept
                        ? `Format: ${accept}`
                        : 'Format file mengikuti kebutuhan formulir.'}
                    {maxFileSizeBytes !== undefined &&
                        ` · Maks. ${formatBytes(maxFileSizeBytes)}`}
                    {maxFiles !== undefined && ` · Maks. ${maxFiles} file`}
                </p>
            </div>
            {message && (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {message}
                </p>
            )}
            {files.length > 0 && (
                <ul className="space-y-2" aria-label="Daftar file terlampir">
                    {files.map((item) => {
                        const isImage = item.file.type.startsWith('image/');
                        const status = item.status ?? 'queued';
                        return (
                            <li
                                key={item.id}
                                className="flex min-w-0 items-center gap-3 rounded-lg border bg-card p-3"
                            >
                                {isImage ? (
                                    <img
                                        src={getPreviewUrl(item)}
                                        alt=""
                                        className="size-12 shrink-0 rounded-md object-cover"
                                    />
                                ) : (
                                    <div className="flex size-12 shrink-0 items-center justify-center rounded-md bg-muted">
                                        {item.file.type ===
                                        'application/pdf' ? (
                                            <FileText aria-hidden="true" />
                                        ) : (
                                            <FileImage aria-hidden="true" />
                                        )}
                                    </div>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {item.file.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatBytes(item.file.size)} ·{' '}
                                        {status === 'uploading'
                                            ? `Mengunggah ${item.progress ?? 0}%`
                                            : status === 'processing'
                                              ? 'Memproses'
                                              : status === 'error'
                                                ? (item.error ??
                                                  'Gagal diunggah')
                                                : status === 'done'
                                                  ? 'Selesai'
                                                  : 'Siap diunggah'}
                                    </p>
                                    {status === 'uploading' && (
                                        <Progress
                                            value={item.progress ?? 0}
                                            aria-label={`Progres unggah ${item.file.name}`}
                                            className="mt-2"
                                        />
                                    )}
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    {(isImage ||
                                        item.file.type ===
                                            'application/pdf') && (
                                        <Button
                                            type="button"
                                            size="icon-sm"
                                            variant="ghost"
                                            aria-label={`Pratinjau ${item.file.name}`}
                                            onClick={() => setPreview(item)}
                                        >
                                            <Eye aria-hidden="true" />
                                        </Button>
                                    )}
                                    {status === 'error' && onRetry && (
                                        <Button
                                            type="button"
                                            size="icon-sm"
                                            variant="ghost"
                                            aria-label={`Coba unggah ulang ${item.file.name}`}
                                            onClick={() => onRetry(item)}
                                        >
                                            <RotateCcw aria-hidden="true" />
                                        </Button>
                                    )}
                                    {status === 'uploading' && onCancel ? (
                                        <Button
                                            type="button"
                                            size="icon-sm"
                                            variant="ghost"
                                            aria-label={`Batalkan unggah ${item.file.name}`}
                                            onClick={() => onCancel(item)}
                                        >
                                            <X aria-hidden="true" />
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            size="icon-sm"
                                            variant="ghost"
                                            aria-label={`Hapus ${item.file.name}`}
                                            onClick={() => removeFile(item)}
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    )}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
            <Dialog
                open={preview !== null}
                onOpenChange={(open) => !open && setPreview(null)}
            >
                <DialogContent className="max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>{preview?.file.name}</DialogTitle>
                        <DialogDescription>
                            Pratinjau file lokal.
                        </DialogDescription>
                    </DialogHeader>
                    {preview &&
                        (preview.file.type === 'application/pdf' ? (
                            <iframe
                                title={`Pratinjau ${preview.file.name}`}
                                src={getPreviewUrl(preview)}
                                className="h-[70vh] w-full rounded-md border"
                            />
                        ) : (
                            <img
                                src={getPreviewUrl(preview)}
                                alt={preview.file.name}
                                className="max-h-[70vh] w-full rounded-md object-contain"
                            />
                        ))}
                </DialogContent>
            </Dialog>
        </div>
    );
}
