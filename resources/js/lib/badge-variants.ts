import { badgeVariants } from '@/components/ui/badge';
import type { VariantProps } from 'class-variance-authority';

export type BadgeVariant = Exclude<
    VariantProps<typeof badgeVariants>['variant'],
    undefined
>;

export function roleBadgeVariant(role: string): BadgeVariant {
    const variants: Record<string, BadgeVariant> = {
        'Super Admin': 'violet',
        'Owner QCC': 'default',
        Mentor: 'info',
        'Team Leader': 'warning',
        'SIMer / Member': 'success',
    };

    return variants[role] ?? 'secondary';
}

export function moduleBadgeVariant(moduleLabel: string): BadgeVariant {
    const variants: Record<string, BadgeVariant> = {
        Autentikasi: 'teal',
        Anggota: 'info',
        Batch: 'success',
        Organisasi: 'violet',
        Kompetensi: 'warning',
        'Kompetensi Saya': 'teal',
        'Penilaian AI': 'default',
        'Pengaturan Sistem': 'violet',
    };

    return variants[moduleLabel] ?? 'secondary';
}

export function permissionBadgeVariant(permission: string): BadgeVariant {
    const prefix = permission.split('.')[0];
    const variants: Record<string, BadgeVariant> = {
        users: 'info',
        roles: 'violet',
        organizations: 'teal',
        competencies: 'warning',
        batches: 'success',
        rubrics: 'violet',
        projects: 'default',
        scoring: 'info',
        'system-settings': 'warning',
        audit: 'secondary',
    };

    return variants[prefix] ?? 'secondary';
}

export function competencyGroupBadgeVariant(groupCode: string): BadgeVariant {
    const variants: Record<string, BadgeVariant> = {
        A: 'info',
        B: 'success',
        C: 'warning',
        D: 'violet',
        E: 'teal',
        F: 'default',
    };

    return variants[groupCode.toUpperCase()] ?? 'secondary';
}

export function scoringStatusBadgeVariant(status: string): BadgeVariant {
    if (status === 'approved') {
        return 'success';
    }

    if (
        status === 'failed' ||
        status === 'insufficient_evidence' ||
        status === 'revision_required'
    ) {
        return 'destructive';
    }

    if (status === 'pending_review') {
        return 'warning';
    }

    if (
        status === 'evidence_check' ||
        status === 'ai_scoring' ||
        status === 'ready_for_scoring'
    ) {
        return 'info';
    }

    return 'secondary';
}

export function scoringTypeBadgeVariant(scoringType: string): BadgeVariant {
    const variants: Record<string, BadgeVariant> = {
        proposal: 'info',
        final_presentation: 'violet',
        convention: 'teal',
    };

    return variants[scoringType] ?? 'secondary';
}
