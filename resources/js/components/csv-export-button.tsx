import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function CsvExportButton({ href }: { href: string }) {
    return (
        <Button
            variant="outline"
            className="h-10 px-4 text-sm"
            render={<a href={href} />}
            nativeButton={false}
        >
            <Download data-icon="inline-start" aria-hidden="true" />
            Ekspor CSV
        </Button>
    );
}
