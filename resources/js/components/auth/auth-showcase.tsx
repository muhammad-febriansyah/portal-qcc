import { Head, usePage } from '@inertiajs/react';
import { FileText, Star, Zap } from 'lucide-react';
import type { ReactNode } from 'react';

export const authInputClassName =
    'h-11 rounded-[10px] border-[#e4e6ec] bg-white pl-11 text-sm shadow-none placeholder:text-[#898997] md:text-sm';

const highlights = [
    {
        icon: FileText,
        filled: false,
        title: 'Monitoring Terpadu',
        description: ['Pantau data dan aktivitas', 'dalam satu dashboard.'],
    },
    {
        icon: Zap,
        filled: true,
        title: 'Proposal Lebih Mudah',
        description: ['Ajukan dan kelola proposal', 'secara digital.'],
    },
    {
        icon: Star,
        filled: true,
        title: 'AI Scoring Cerdas',
        description: ['Dapatkan penilaian yang objektif', 'dan transparan.'],
    },
];

export function PortalLogo({ className = '' }: { className?: string }) {
    const { branding } = usePage().props;

    if (!branding.logoUrl) {
        return (
            <p
                className={`text-xl font-bold tracking-tight text-primary ${className}`}
            >
                {branding.name}
            </p>
        );
    }

    return (
        <img
            src={branding.logoUrl}
            alt={branding.name}
            width={154}
            height={40}
            className={`h-10 w-auto object-contain mix-blend-multiply ${className}`}
        />
    );
}

type Props = {
    title: string;
    children: ReactNode;
};

/**
 * Two panel shell shared by the sign in and sign up pages: brand showcase on
 * the left, the form passed as children on the right.
 */
export default function AuthShowcase({ title, children }: Props) {
    const { branding } = usePage().props;

    return (
        <>
            <Head title={title}>
                {branding.faviconUrl && (
                    <link rel="icon" href={branding.faviconUrl} />
                )}
                <meta name="description" content={branding.description} />
                <meta name="keywords" content={branding.keywords} />
            </Head>

            <main className="relative isolate flex min-h-dvh items-center justify-center overflow-hidden bg-[#dae9fd] px-4 py-6 text-[#0b1530] [--primary:#0b5cf0] [--ring:#0b5cf0] sm:px-8 sm:py-8 lg:px-12">
                <div className="pointer-events-none absolute -top-[26rem] left-[28%] -z-10 size-[48rem] rounded-full bg-[#e9f2fe]" />
                <div className="pointer-events-none absolute -top-32 -left-48 -z-10 size-[28rem] rounded-full bg-[#e3eefd]" />
                <div className="pointer-events-none absolute -right-60 -bottom-[18rem] -z-10 size-[44rem] rounded-full bg-[#e6f0fe]" />
                <div className="pointer-events-none absolute -bottom-[24rem] left-[22%] -z-10 size-[40rem] rounded-full bg-[#d1e3fd]" />

                <section className="relative grid w-full max-w-[1160px] overflow-hidden rounded-[20px] border border-white/70 bg-white shadow-[0_4px_20px_rgba(40,90,170,0.06)] lg:min-h-[620px] lg:grid-cols-[60fr_40fr]">
                    <div className="relative isolate overflow-hidden bg-[#f6fafd] px-7 pt-8 pb-9 sm:px-10 sm:pt-10 lg:px-14 lg:pt-14 lg:pb-14">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute -top-40 right-[-12rem] -z-10 size-[36rem] rounded-full bg-[#eef5fe]"
                        />
                        <div
                            aria-hidden
                            className="pointer-events-none absolute right-[-15rem] bottom-[-22rem] -z-10 size-[44rem] rounded-full bg-[#e4effe]"
                        />

                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-0 -z-10 hidden lg:block"
                        >
                            <div className="absolute top-[30px] right-[-50px] h-[240px] w-[160px] bg-[#c9dcf8]/70" />
                            <div className="absolute top-[120px] right-[85px] h-[240px] w-[11px] bg-[#b7cff3]/80" />
                            <img
                                src="/assets/gedung_simgroup_HD.webp"
                                alt=""
                                width={1830}
                                height={1653}
                                className="absolute top-[80px] right-[-100px] w-[340px] opacity-[0.28] mix-blend-multiply saturate-[0.7] select-none"
                            />
                        </div>

                        <div
                            aria-hidden
                            className="pointer-events-none absolute top-[14px] right-[-12px] hidden w-[74px] flex-col items-center lg:flex"
                        >
                            <span className="h-[76px] w-[2px] bg-[#3f77df]" />
                            <span className="h-9 w-full rounded-t-full bg-[#3f77df]" />
                            <span className="h-1.5 w-8 rounded-b-full bg-white/80" />
                        </div>

                        <div className="relative z-10 max-w-[420px]">
                            <span className="inline-flex rounded-lg bg-[#dfecfe] px-3 py-1.5 text-[13px] leading-5 font-medium text-[#1a4fc4]">
                                Satu Portal, Banyak Kemudahan
                            </span>
                            <h1 className="mt-5 text-[28px] leading-[1.25] font-bold tracking-[-0.02em] sm:text-[31px] sm:leading-[39px]">
                                Monitoring, Proposal,
                                <br className="hidden sm:block" /> dan AI
                                Scoring
                                <span className="block text-primary">
                                    dalam Satu Sistem
                                </span>
                            </h1>
                            <p className="mt-4 max-w-[365px] text-sm leading-[22px] text-[#6d727e]">
                                Kelola data, pantau perkembangan, dan dapatkan
                                penilaian dengan dukungan AI secara terintegrasi
                                dan efisien.
                            </p>
                        </div>

                        <div className="relative z-10 mt-9 grid max-w-[260px] gap-6">
                            {highlights.map(
                                ({
                                    icon: Icon,
                                    filled,
                                    title: itemTitle,
                                    description,
                                }) => (
                                    <div
                                        key={itemTitle}
                                        className="flex items-start gap-3.5"
                                    >
                                        <span className="flex size-11 shrink-0 items-center justify-center rounded-[10px] bg-white text-primary shadow-[0_4px_12px_rgba(30,80,170,0.08)]">
                                            <Icon
                                                className="size-5"
                                                strokeWidth={1.8}
                                                fill={
                                                    filled
                                                        ? 'currentColor'
                                                        : 'none'
                                                }
                                            />
                                        </span>
                                        <div className="pt-0.5">
                                            <p className="text-sm leading-5 font-semibold">
                                                {itemTitle}
                                            </p>
                                            <p className="mt-1 text-xs leading-[18px] text-[#72787f]">
                                                {description[0]}
                                                <br className="hidden sm:block" />{' '}
                                                {description[1]}
                                            </p>
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>

                        <img
                            src="/assets/Ilustrasi Kantor Digital Minimalis.png"
                            alt="Karyawan memantau dashboard digital"
                            width={1448}
                            height={1086}
                            fetchPriority="high"
                            className="pointer-events-none absolute right-[-34%] bottom-[-26px] hidden w-[86%] max-w-none select-none lg:block"
                        />
                    </div>

                    <div className="flex items-center justify-center px-7 py-10 sm:px-12 sm:py-12 lg:px-10 xl:px-14">
                        {children}
                    </div>
                </section>
            </main>
        </>
    );
}
