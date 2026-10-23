import { Form, Link, router, usePage } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import AuthShowcase, { PortalLogo } from '@/components/auth/auth-showcase';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

type Props = {
    status?: string;
};

export default function VerifyEmail({ status }: Props) {
    const { auth } = usePage().props;

    return (
        <AuthShowcase title="Verifikasi email">
            <div className="w-full max-w-[350px]">
                <PortalLogo />

                <span className="mt-8 flex size-12 items-center justify-center rounded-xl bg-[#eef5fe] text-primary">
                    <MailCheck className="size-6" strokeWidth={1.8} />
                </span>

                <h2 className="mt-5 text-[26px] leading-tight font-bold tracking-[-0.02em] sm:text-[28px]">
                    Cek email Anda
                </h2>
                <p className="mt-3 text-sm leading-6 text-[#5e6779]">
                    Kami mengirim tautan verifikasi ke{' '}
                    <span className="font-semibold text-[#0b1530]">
                        {auth.user.email}
                    </span>
                    . Buka email itu lalu klik tombol Verifikasi email untuk
                    mengaktifkan akun.
                </p>

                {status === 'verification-link-sent' && (
                    <div
                        role="status"
                        className="mt-5 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                    >
                        Email verifikasi baru sudah dikirim. Periksa juga folder
                        spam.
                    </div>
                )}

                <Form {...send.form()} className="mt-6">
                    {({ processing }) => (
                        <Button
                            type="submit"
                            className="h-12 w-full rounded-[10px] text-base font-semibold shadow-[0_6px_14px_rgba(11,92,240,0.18)]"
                            disabled={processing}
                            data-test="resend-verification-button"
                        >
                            {processing && <Spinner />}
                            Kirim ulang email verifikasi
                        </Button>
                    )}
                </Form>

                <p className="mt-5 text-center text-sm text-[#5e6779]">
                    Salah alamat email atau ingin pakai akun lain?{' '}
                    <Link
                        href={logout()}
                        as="button"
                        onClick={() => router.flushAll()}
                        className="font-semibold text-primary hover:underline"
                    >
                        Keluar
                    </Link>
                </p>
            </div>
        </AuthShowcase>
    );
}
