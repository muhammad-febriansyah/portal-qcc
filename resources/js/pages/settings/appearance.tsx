import { Head } from '@inertiajs/react';
import { Sun } from 'lucide-react';

export default function Appearance() {
    return (
        <>
            <Head title="Appearance settings" />

            <div className="space-y-6">
                <div className="flex items-center gap-2 rounded-lg border bg-card p-4 text-sm text-card-foreground">
                    <Sun className="size-4 text-primary" aria-hidden="true" />
                    <span>Light theme is active for all users.</span>
                </div>
            </div>
        </>
    );
}
