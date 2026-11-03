import { FileSpreadsheet } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function ExcelExportButton({ href }: { href: string }) {
    return (
        <Button
            variant="outline"
            className="h-10 border-primary/20 bg-primary/5 px-4 text-sm text-primary hover:bg-primary/10"
            render={<a href={href} />}
            nativeButton={false}
        >
            <FileSpreadsheet data-icon="inline-start" aria-hidden="true" />
            Ekspor Excel
        </Button>
    );
}
