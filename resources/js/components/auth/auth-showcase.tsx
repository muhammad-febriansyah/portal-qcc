import { Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

/** The two SIMGROUP QCC brand colors, taken from the logo. */
export const brandBlue = '#0191C2';
export const brandTan = '#D49E77';

export const authInputClassName =
    'h-14 rounded-xl border-[#dfe5ec] bg-white pl-12 text-base shadow-none placeholder:text-[#7c8694] focus-visible:border-[#0191C2] focus-visible:ring-[#0191C2]/20 md:text-base';

export function PortalLogo({ className = '' }: { className?: string }) {
    const { branding } = usePage().props;

    return (
        <img
            src="/assets/logo-qcc.png"
            alt={`${branding.name} · SIMGROUP Quality Control Circle`}
            width={960}
            height={806}
            className={`h-auto w-full object-contain select-none ${className}`}
        />
    );
}

const qccSteps = [
    'Menentukan Tema',
    'Menetapkan Target',
    'Menemukan Sebab-Sebab yang Mungkin',
    'Menentukan Sebab-Sebab yang Dominan',
    'Membuat Rencana Perbaikan',
    'Implementasi Rencana Perbaikan',
    'Evaluasi Hasil Perbaikan',
    'Standarisasi & Rencana Pencegahan',
];

/** Ring radius and badge size as a share of the ring's width. */
const ringRadius = 41;
const badgeSize = 18;

function pointOnRing(angle: number): { x: number; y: number } {
    const radians = (angle * Math.PI) / 180;

    return {
        x: 50 + ringRadius * Math.cos(radians),
        y: 50 + ringRadius * Math.sin(radians),
    };
}

/** Step n sits clockwise from the top, 45 degrees apart. */
function stepAngle(index: number): number {
    return -90 + index * (360 / qccSteps.length);
}

/** Arrows from each step badge to the next, drawn along the ring. */
function CycleArrows() {
    const gap = 17;

    return (
        <svg
            viewBox="0 0 100 100"
            aria-hidden
            className="pointer-events-none absolute inset-0 size-full overflow-visible"
        >
            <defs>
                <marker
                    id="qcc-cycle-arrow"
                    viewBox="0 0 10 10"
                    refX="7"
                    refY="5"
                    markerWidth="4"
                    markerHeight="4"
                    orient="auto-start-reverse"
                >
                    <path
                        d="M1 1 L9 5 L1 9"
                        fill="none"
                        stroke={brandBlue}
                        strokeWidth="1.6"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </marker>
            </defs>
            {qccSteps.map((_, index) => {
                const from = pointOnRing(stepAngle(index) + gap);
                const to = pointOnRing(stepAngle(index + 1) - gap);

                return (
                    <path
                        key={index}
                        d={`M ${from.x} ${from.y} A ${ringRadius} ${ringRadius} 0 0 1 ${to.x} ${to.y}`}
                        fill="none"
                        stroke={brandBlue}
                        strokeOpacity="0.5"
                        strokeWidth="0.45"
                        strokeLinecap="round"
                        markerEnd="url(#qcc-cycle-arrow)"
                    />
                );
            })}
        </svg>
    );
}

/** A small grid of dots, the texture used in the campaign artwork. */
function DotGrid({ className }: { className: string }) {
    return (
        <span
            aria-hidden
            className={`pointer-events-none absolute size-20 bg-[radial-gradient(#0191C2_1.2px,transparent_1.2px)] [background-size:12px_12px] opacity-30 ${className}`}
        />
    );
}

/** The 8 QCC steps in a cycle around the logo and tagline, shown on large screens. */
function QccStepRing() {
    return (
        <div className="relative aspect-square w-full max-w-[620px]">
            <DotGrid className="top-[2%] left-[2%]" />
            <DotGrid className="right-[0%] bottom-[4%]" />
            <CycleArrows />
            <ol aria-label="8 Step QCC" className="contents">
                {qccSteps.map((step, index) => {
                    const point = pointOnRing(stepAngle(index));

                    return (
                        <li
                            key={step}
                            className="absolute -translate-x-1/2 -translate-y-1/2 drop-shadow-[0_8px_14px_rgba(1,145,194,0.18)]"
                            style={{
                                left: `${point.x}%`,
                                top: `${point.y}%`,
                                width: `${badgeSize}%`,
                            }}
                        >
                            <img
                                src={`/assets/qcc-steps/step-${index + 1}.webp`}
                                alt={`Langkah ${index + 1}: ${step}`}
                                width={256}
                                height={256}
                                loading="lazy"
                                className="h-auto w-full select-none"
                            />
                        </li>
                    );
                })}
            </ol>
            <div className="absolute inset-[25%] grid content-center justify-items-center gap-3">
                <PortalLogo className="max-w-[78%]" />
                <Tagline />
            </div>
        </div>
    );
}

/** "Let's Create a Legacy" with the tan brush banner from the campaign artwork. */
function Tagline() {
    return (
        <div className="grid justify-items-center gap-2 text-center">
            <p className="text-xl leading-none font-extrabold tracking-[-0.02em] whitespace-nowrap text-[#0b3a57] italic xl:text-2xl">
                Let’s Create a Legacy
            </p>
            <p
                className="-rotate-2 px-4 py-1.5 text-[10px] font-bold whitespace-nowrap text-white italic xl:text-xs"
                style={{
                    backgroundColor: brandTan,
                    clipPath:
                        'polygon(2% 18%, 6% 6%, 30% 0, 70% 3%, 97% 0, 100% 30%, 98% 62%, 100% 92%, 72% 100%, 40% 96%, 8% 100%, 0 78%, 3% 50%)',
                }}
            >
                Kita Bisa! Pasti Bisa! Bisa Bisa Yes!
            </p>
        </div>
    );
}

type Props = {
    title: string;
    children: ReactNode;
};

/**
 * Shell shared by the sign in, password and verification pages: the SIMGROUP QCC
 * campaign panel on the left, the form card passed as children on the right.
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

            <main className="relative isolate flex min-h-dvh items-center justify-center overflow-hidden bg-gradient-to-br from-[#eef8fc] via-[#e3f2f9] to-[#d3ebf6] px-4 py-8 text-[#0f2b3d] [--primary:#0191C2] [--ring:#0191C2] sm:px-8 lg:px-12">
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-24 right-[8%] -z-10 h-[140%] w-24 rotate-[28deg] bg-white/40 blur-sm"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-24 right-[16%] -z-10 h-[140%] w-10 rotate-[28deg] bg-white/30 blur-sm"
                />
                <div
                    aria-hidden
                    className="pointer-events-none absolute -bottom-40 -left-40 -z-10 size-[34rem] rounded-full bg-white/50 blur-2xl"
                />

                <section className="relative grid w-full max-w-[1240px] items-center gap-8 lg:min-h-[640px] lg:grid-cols-[1.05fr_0.95fr] lg:gap-12">
                    <div className="relative isolate grid justify-items-center gap-8 lg:content-center">
                        <PortalLogo className="max-w-[240px] sm:max-w-[300px] lg:hidden" />
                        <div className="hidden w-full justify-items-center lg:grid">
                            <QccStepRing />
                        </div>
                    </div>

                    <div className="flex justify-center lg:justify-end">
                        <div className="w-full max-w-[520px] rounded-[28px] bg-white/95 px-6 py-9 shadow-[0_12px_32px_-24px_rgba(24,35,55,0.25)] ring-1 ring-border/60 sm:px-12 sm:py-12">
                            {children}
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}
