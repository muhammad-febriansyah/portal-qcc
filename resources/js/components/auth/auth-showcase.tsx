import { Head, usePage } from '@inertiajs/react';
import { Settings } from 'lucide-react';
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

/** Rising bars, a gear and a growth arrow, kept faint behind the brand panel. */
function GrowthIllustration() {
    const bars = [38, 52, 30, 44, 62, 74, 88, 104];

    return (
        <div
            aria-hidden
            className="pointer-events-none relative -mt-4 hidden h-[170px] w-full max-w-[560px] lg:block"
        >
            <Settings
                className="absolute bottom-4 left-0 size-28 text-[#0191C2]/15"
                strokeWidth={1.4}
            />
            <div className="absolute right-[4%] bottom-0 left-[24%] flex h-full items-end gap-3">
                {bars.map((height, index) => (
                    <span
                        key={index}
                        className="flex-1 rounded-t-md bg-gradient-to-t from-[#0191C2]/5 to-[#0191C2]/20"
                        style={{ height: `${height}%` }}
                    />
                ))}
            </div>
            <svg
                viewBox="0 0 400 160"
                className="absolute right-[2%] bottom-6 w-[64%]"
                fill="none"
            >
                <path
                    d="M10 150 C 140 140, 250 100, 370 22"
                    stroke="#0191C2"
                    strokeOpacity="0.45"
                    strokeWidth="5"
                    strokeLinecap="round"
                />
                <path
                    d="M338 22 L376 16 L362 52"
                    stroke="#0191C2"
                    strokeOpacity="0.55"
                    strokeWidth="5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        </div>
    );
}

/** "Let's Create a Legacy" with the tan brush banner from the campaign artwork. */
function Tagline() {
    return (
        <div className="grid justify-items-center gap-4 text-center">
            <svg
                viewBox="0 0 360 14"
                aria-hidden
                className="w-[70%] max-w-[300px]"
            >
                <path
                    d="M4 10 C 120 0, 240 0, 356 8"
                    stroke={brandTan}
                    strokeWidth="5"
                    strokeLinecap="round"
                    fill="none"
                />
            </svg>
            <p className="text-[34px] leading-none font-extrabold tracking-[-0.02em] text-[#0b3a57] italic xl:text-[44px]">
                Let’s Create a Legacy
            </p>
            <p
                className="mt-1 -rotate-2 px-10 py-3 text-lg font-bold text-white italic xl:text-[22px]"
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
                        <PortalLogo className="max-w-[240px] sm:max-w-[300px] lg:max-w-[400px]" />
                        <div className="hidden lg:block">
                            <Tagline />
                        </div>
                        <GrowthIllustration />
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
