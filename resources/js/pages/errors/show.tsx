import { Head, Link } from '@inertiajs/react';
import { ArrowRight, RotateCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const errorPages: Record<
    number,
    {
        title: string;
        description: string;
        image: string;
        imageDescription: string;
        primaryAction: 'dashboard' | 'login' | 'reload';
        primaryLabel: string;
    }
> = {
    403: {
        title: 'Akses belum tersedia',
        description:
            'Akun Anda belum memiliki izin untuk membuka halaman ini. Hubungi Owner QCC jika akses ini diperlukan.',
        image: '/assets/error-403.png',
        imageDescription: 'Ilustrasi akses ditolak',
        primaryAction: 'dashboard',
        primaryLabel: 'Kembali ke Dashboard',
    },
    404: {
        title: 'Halaman tidak ditemukan',
        description:
            'Alamat ini mungkin sudah berubah atau tidak tersedia. Kembali ke Dashboard untuk melanjutkan.',
        image: '/assets/error-404.png',
        imageDescription: 'Ilustrasi halaman tidak ditemukan',
        primaryAction: 'dashboard',
        primaryLabel: 'Kembali ke Dashboard',
    },
    419: {
        title: 'Sesi Anda berakhir',
        description:
            'Sesi login sudah kedaluwarsa. Masuk kembali agar pekerjaan Anda tetap terlindungi.',
        image: '/assets/error-419.png',
        imageDescription: 'Ilustrasi sesi kedaluwarsa',
        primaryAction: 'login',
        primaryLabel: 'Masuk kembali',
    },
    429: {
        title: 'Terlalu banyak permintaan',
        description:
            'Portal menerima permintaan beruntun. Tunggu sebentar sebelum mencoba lagi.',
        image: '/assets/error-429.png',
        imageDescription: 'Ilustrasi batas permintaan tercapai',
        primaryAction: 'reload',
        primaryLabel: 'Coba lagi',
    },
    500: {
        title: 'Ada gangguan di server',
        description:
            'Permintaan belum dapat diproses. Coba muat ulang beberapa saat lagi.',
        image: '/assets/error-500.png',
        imageDescription: 'Ilustrasi gangguan server',
        primaryAction: 'reload',
        primaryLabel: 'Coba lagi',
    },
    503: {
        title: 'Portal sedang dirawat',
        description:
            'Layanan sementara tidak tersedia karena pemeliharaan. Kami akan segera kembali.',
        image: '/assets/error-503.png',
        imageDescription: 'Ilustrasi portal dalam pemeliharaan',
        primaryAction: 'reload',
        primaryLabel: 'Muat ulang halaman',
    },
};

export default function ErrorPage({ status }: { status: number }) {
    const page = errorPages[status] ?? errorPages[500];

    return (
        <>
            <Head title={`${status} · ${page.title}`}>
                <link rel="icon" href="/assets/logo-simgroup-mark.png" />
            </Head>
            <main className="relative flex min-h-screen flex-col overflow-hidden bg-white px-5 sm:px-8">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -top-56 left-1/2 size-[38rem] -translate-x-1/2 rounded-full bg-[#2948ff] opacity-[0.08] blur-[130px]"
                />

                <header className="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-between py-7 sm:py-9">
                    <a
                        href="/"
                        aria-label="Portal QCC SIMGROUP"
                        className="inline-flex items-center"
                    >
                        <img
                            src="/assets/logo-simgroup.png"
                            alt="SIMGROUP"
                            width={165}
                            height={43}
                            className="h-9 w-auto object-contain"
                        />
                    </a>
                    <span className="text-xs font-semibold tracking-[0.16em] text-[#8792a8] uppercase sm:text-sm">
                        Portal QCC
                    </span>
                </header>

                <section className="relative z-10 mx-auto flex w-full max-w-3xl flex-1 flex-col items-center justify-center py-8 text-center sm:py-10">
                    <img
                        src={page.image}
                        alt={`${status} · ${page.imageDescription}`}
                        className="mb-3 aspect-square w-full max-w-[290px] object-contain sm:max-w-[340px]"
                    />

                    <p className="mb-3 text-xs font-semibold tracking-[0.18em] text-[#2547f9] uppercase">
                        Portal QCC · {status}
                    </p>
                    <h1 className="text-3xl leading-tight font-semibold tracking-tight text-[#182337] sm:text-4xl">
                        {page.title}
                    </h1>
                    <p className="mt-3 max-w-xl text-base leading-7 text-[#697386] sm:text-lg">
                        {page.description}
                    </p>

                    <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                        {page.primaryAction === 'dashboard' && (
                            <Button
                                nativeButton={false}
                                render={<Link href={dashboard()} />}
                                size="lg"
                                className="h-11 rounded-xl px-5"
                            >
                                {page.primaryLabel}
                                <ArrowRight aria-hidden="true" />
                            </Button>
                        )}
                        {page.primaryAction === 'login' && (
                            <Button
                                nativeButton={false}
                                render={<Link href={login()} />}
                                size="lg"
                                className="h-11 rounded-xl px-5"
                            >
                                {page.primaryLabel}
                                <ArrowRight aria-hidden="true" />
                            </Button>
                        )}
                        {page.primaryAction === 'reload' && (
                            <Button
                                type="button"
                                onClick={() => window.location.reload()}
                                size="lg"
                                className="h-11 rounded-xl px-5"
                            >
                                <RotateCw aria-hidden="true" />
                                {page.primaryLabel}
                            </Button>
                        )}
                        {page.primaryAction !== 'reload' && (
                            <Button
                                type="button"
                                variant="outline"
                                size="lg"
                                className="h-11 rounded-xl px-5"
                                onClick={() => window.location.reload()}
                            >
                                <RotateCw aria-hidden="true" />
                                Muat ulang
                            </Button>
                        )}
                        {page.primaryAction === 'reload' && (
                            <Button
                                nativeButton={false}
                                render={<Link href={dashboard()} />}
                                variant="outline"
                                size="lg"
                                className="h-11 rounded-xl px-5"
                            >
                                Kembali ke Dashboard
                            </Button>
                        )}
                    </div>
                </section>

                <footer className="relative z-10 mx-auto flex w-full max-w-7xl flex-col gap-2 border-t border-[#e4e9f3] py-5 text-xs font-medium text-[#8993a6] sm:flex-row sm:items-center sm:justify-between sm:py-6">
                    <span>
                        © {new Date().getFullYear()} SIMGROUP · Portal QCC
                    </span>
                    <span>Continuous Quality Control</span>
                </footer>
            </main>
        </>
    );
}
