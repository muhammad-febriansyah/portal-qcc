import { Ellipsis, type LucideIcon } from 'lucide-react';
import { useState } from 'react';
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
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export type RowAction = {
    label: string;
    icon?: LucideIcon;
    onSelect: () => void;
    destructive?: boolean;
    requiresConfirmation?: boolean;
    disabled?: boolean;
    hidden?: boolean;
    confirmTitle?: string;
    confirmDescription?: string;
    confirmLabel?: string;
};

export function DataTableRowActions({
    actions,
    label = 'Buka aksi baris',
}: {
    actions: RowAction[];
    label?: string;
}) {
    const [pendingAction, setPendingAction] = useState<RowAction | null>(null);
    const visibleActions = actions.filter((action) => !action.hidden);

    if (visibleActions.length === 0) return null;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger
                    render={
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label={label}
                        />
                    }
                >
                    <Ellipsis aria-hidden="true" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-44">
                    {visibleActions.map((action, index) => {
                        const Icon = action.icon;
                        return (
                            <div key={`${action.label}-${index}`}>
                                {action.destructive && index > 0 && (
                                    <DropdownMenuSeparator />
                                )}
                                <DropdownMenuItem
                                    disabled={action.disabled}
                                    variant={
                                        action.destructive
                                            ? 'destructive'
                                            : 'default'
                                    }
                                    onClick={() =>
                                        action.destructive ||
                                        action.requiresConfirmation
                                            ? setPendingAction(action)
                                            : action.onSelect()
                                    }
                                >
                                    {Icon && <Icon aria-hidden="true" />}
                                    {action.label}
                                </DropdownMenuItem>
                            </div>
                        );
                    })}
                </DropdownMenuContent>
            </DropdownMenu>
            <AlertDialog
                open={pendingAction !== null}
                onOpenChange={(open) => !open && setPendingAction(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {pendingAction?.confirmTitle ??
                                'Konfirmasi tindakan'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {pendingAction?.confirmDescription ??
                                'Tindakan ini tidak dapat dibatalkan.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel
                            onClick={() => setPendingAction(null)}
                        >
                            Batal
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant={
                                pendingAction?.destructive
                                    ? 'destructive'
                                    : 'default'
                            }
                            onClick={() => {
                                pendingAction?.onSelect();
                                setPendingAction(null);
                            }}
                        >
                            {pendingAction?.confirmLabel ?? 'Lanjutkan'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
