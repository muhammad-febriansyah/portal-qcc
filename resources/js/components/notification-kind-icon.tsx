import {
    Bell,
    CalendarDays,
    FileText,
    ListChecks,
    MessagesSquare,
    Sparkles,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

const icons: Record<string, LucideIcon> = {
    bell: Bell,
    'file-text': FileText,
    'list-checks': ListChecks,
    'calendar-days': CalendarDays,
    'messages-square': MessagesSquare,
    'triangle-alert': TriangleAlert,
    sparkles: Sparkles,
};

type Props = {
    icon: string;
    className?: string;
};

export function NotificationKindIcon({ icon, className }: Props) {
    const Icon = icons[icon] ?? Bell;

    return <Icon className={className} aria-hidden="true" />;
}
