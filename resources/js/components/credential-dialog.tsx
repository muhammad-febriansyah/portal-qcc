import { Check, Copy, TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useClipboard } from '@/hooks/use-clipboard';

export type Credential = {
    name: string;
    email: string;
    password: string;
    reason: 'created' | 'reset';
};

type Props = {
    credential: Credential | null;
    onClose: () => void;
};

export function CredentialDialog({ credential, onClose }: Props) {
    const [copiedText, copy] = useClipboard();
    const isCopied = copiedText !== null && copiedText === credential?.password;

    return (
        <Dialog
            open={credential !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {credential?.reason === 'reset'
                            ? 'Password Baru'
                            : 'Akun Berhasil Dibuat'}
                    </DialogTitle>
                    <DialogDescription>
                        Berikan kredensial ini kepada {credential?.name} melalui
                        jalur yang aman.
                    </DialogDescription>
                </DialogHeader>

                {credential && (
                    <div className="grid gap-3">
                        <div className="grid gap-1 rounded-lg bg-muted px-3 py-2.5">
                            <span className="text-xs text-muted-foreground">
                                Email
                            </span>
                            <span className="text-sm font-medium break-all">
                                {credential.email}
                            </span>
                        </div>
                        <div className="flex items-center justify-between gap-3 rounded-lg bg-muted px-3 py-2.5">
                            <div className="grid min-w-0 gap-1">
                                <span className="text-xs text-muted-foreground">
                                    Password sementara
                                </span>
                                <code className="text-sm font-semibold tracking-wide break-all">
                                    {credential.password}
                                </code>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => void copy(credential.password)}
                            >
                                {isCopied ? (
                                    <Check aria-hidden="true" />
                                ) : (
                                    <Copy aria-hidden="true" />
                                )}
                                {isCopied ? 'Tersalin' : 'Salin'}
                            </Button>
                        </div>
                        <p className="flex items-start gap-2 text-xs text-[#c96b0d]">
                            <TriangleAlert
                                className="mt-0.5 size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            Password hanya tampil sekali. Setelah dialog ini
                            ditutup, password tidak dapat dilihat lagi dan harus
                            direset bila hilang.
                        </p>
                    </div>
                )}

                <DialogFooter>
                    <Button type="button" onClick={onClose}>
                        Selesai
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
