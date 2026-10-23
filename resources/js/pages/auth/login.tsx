import { Form, Link } from '@inertiajs/react';
import { ArrowRight, Lock, Mail, ShieldCheck } from 'lucide-react';
import AuthShowcase, {
    authInputClassName,
    PortalLogo,
} from '@/components/auth/auth-showcase';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { register } from '@/routes';

type Props = {
    status?: string;
    canRegister?: boolean;
};

export default function Login({ status, canRegister = false }: Props) {
    return (
        <AuthShowcase title="Masuk">
            <div className="w-full max-w-[350px]">
                <PortalLogo />
                <h2 className="mt-8 text-[26px] leading-tight font-bold tracking-[-0.02em] sm:text-[28px]">
                    Masuk ke akun Anda
                </h2>
                <p className="mt-3 max-w-[330px] text-sm leading-6 text-[#5e6779]">
                    Akses sistem untuk monitoring, proposal, dan AI scoring
                    dalam satu portal terpadu.
                </p>

                {status && (
                    <div className="mt-5 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {status}
                    </div>
                )}

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    className="mt-6 flex flex-col"
                >
                    {({ processing, errors }) => (
                        <>
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
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="email"
                                        placeholder="nama@perusahaan.co.id"
                                        className={authInputClassName}
                                    />
                                </div>
                                <InputError message={errors.email} />
                            </div>

                            <div className="mt-[18px] grid gap-2">
                                <Label
                                    htmlFor="password"
                                    className="font-semibold"
                                >
                                    Password
                                </Label>
                                <div className="relative">
                                    <Lock
                                        className="pointer-events-none absolute top-1/2 left-4 z-10 size-4 -translate-y-1/2 text-[#838592]"
                                        strokeWidth={1.8}
                                    />
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="current-password"
                                        placeholder="Masukkan password"
                                        className={`${authInputClassName} pr-11`}
                                    />
                                </div>
                                <InputError message={errors.password} />
                            </div>

                            <div className="mt-4 flex items-center gap-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    className="size-4 rounded-[4px] border-[#d5d8e0]"
                                />
                                <Label
                                    htmlFor="remember"
                                    className="font-normal"
                                >
                                    Ingat saya
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="relative mt-6 h-12 w-full rounded-[10px] text-base font-semibold shadow-[0_6px_14px_rgba(11,92,240,0.18)] transition-[transform,box-shadow] hover:shadow-[0_8px_18px_rgba(11,92,240,0.24)] active:translate-y-px"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Masuk
                                <ArrowRight className="absolute right-6 size-[18px]" />
                            </Button>
                        </>
                    )}
                </Form>

                {canRegister && (
                    <p className="mt-5 text-center text-sm text-[#5e6779]">
                        Belum punya akun?{' '}
                        <Link
                            href={register()}
                            tabIndex={5}
                            className="font-semibold text-primary hover:underline"
                        >
                            Daftar sebagai SIMer
                        </Link>
                    </p>
                )}

                <div className="mt-6 flex items-center gap-4 rounded-xl bg-[#f4f7ff] px-5 py-3.5">
                    <ShieldCheck
                        className="size-7 shrink-0 text-[#1c63c0]"
                        strokeWidth={1.6}
                    />
                    <p className="text-[13px] leading-5 text-[#646a7a]">
                        Akses aman untuk monitoring, proposal, dan AI scoring
                    </p>
                </div>
            </div>
        </AuthShowcase>
    );
}
