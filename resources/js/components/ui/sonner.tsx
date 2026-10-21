import { useFlashToast } from '@/hooks/use-flash-toast';
import { CircleCheck, CircleX, Info, TriangleAlert } from 'lucide-react';
import { Toaster as Sonner, type ToasterProps } from 'sonner';

function Toaster({ ...props }: ToasterProps) {
    useFlashToast();

    return (
        <Sonner
            theme="light"
            richColors
            className="toaster group"
            position="bottom-right"
            icons={{
                success: <CircleCheck aria-hidden="true" className="size-4" />,
                warning: <TriangleAlert aria-hidden="true" className="size-4" />,
                error: <CircleX aria-hidden="true" className="size-4" />,
                info: <Info aria-hidden="true" className="size-4" />,
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
