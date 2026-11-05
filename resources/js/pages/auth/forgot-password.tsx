// Components
import { Form, Link } from '@inertiajs/react';
import { ArrowRight, Mail } from 'lucide-react';
import AuthShowcase, {
    authInputClassName,
} from '@/components/auth/auth-showcase';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <AuthShowcase title="Lupa password">
            <div className="w-full">
                <h2 className="mt-8 text-[26px] leading-tight font-bold tracking-[-0.02em] sm:text-[28px]">
                    Lupa password?
                </h2>
                <p className="mt-3 max-w-[330px] text-sm leading-6 text-[#5e6779]">
                    Masukkan email akun Anda. Kami akan mengirim tautan untuk
                    mengatur ulang password.
                </p>

                {status && (
                    <div className="mt-5 rounded-xl border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                        {status}
                    </div>
                )}

                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <div className="mt-6 flex flex-col">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="email"
                                    className="font-semibold"
                                >
                                    Email
                                </Label>
                                <div className="relative">
                                    <Mail
                                        className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[#838592]"
                                        strokeWidth={1.8}
                                    />
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoComplete="email"
                                        autoFocus
                                        placeholder="nama@perusahaan.co.id"
                                        className={authInputClassName}
                                    />
                                </div>

                                <InputError message={errors.email} />
                            </div>

                            <Button
                                type="submit"
                                className="relative mt-6 h-12 w-full rounded-[10px] text-base font-semibold shadow-[0_6px_14px_rgba(11,92,240,0.18)] transition-[transform,box-shadow] hover:shadow-[0_8px_18px_rgba(11,92,240,0.24)] active:translate-y-px"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                Kirim tautan reset password
                                <ArrowRight className="absolute right-6 size-[18px]" />
                            </Button>
                        </div>
                    )}
                </Form>

                <div className="mt-5 text-center text-sm text-[#5e6779]">
                    Ingat password?{' '}
                    <Link
                        href={login()}
                        className="font-semibold text-primary hover:underline"
                    >
                        Kembali ke halaman masuk
                    </Link>
                </div>
            </div>
        </AuthShowcase>
    );
}

ForgotPassword.layout = {
    title: 'Lupa password',
    description: 'Masukkan email untuk menerima tautan reset password',
};
