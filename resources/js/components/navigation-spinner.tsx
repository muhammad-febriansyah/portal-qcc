import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Spinner } from '@/components/ui/spinner';

export function NavigationSpinner() {
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        let activeVisitId: string | undefined;
        let showSpinnerTimeout: ReturnType<typeof setTimeout> | undefined;

        const stopSpinner = () => {
            if (showSpinnerTimeout) {
                clearTimeout(showSpinnerTimeout);
                showSpinnerTimeout = undefined;
            }

            setIsLoading(false);
        };

        const removeStartListener = router.on('start', (event) => {
            activeVisitId = event.detail.visit.id;
            stopSpinner();

            showSpinnerTimeout = setTimeout(() => {
                setIsLoading(true);
            }, 150);
        });

        const removeFinishListener = router.on('finish', (event) => {
            if (event.detail.visit.id !== activeVisitId) {
                return;
            }

            activeVisitId = undefined;
            stopSpinner();
        });

        return () => {
            removeStartListener();
            removeFinishListener();
            stopSpinner();
        };
    }, []);

    if (!isLoading) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed inset-x-0 top-3 z-50 flex justify-center">
            <div className="flex items-center gap-2 rounded-full border bg-background px-3 py-2 text-sm text-foreground shadow-sm">
                <Spinner aria-hidden="true" />
                <span role="status">Memuat halaman</span>
            </div>
        </div>
    );
}
