import { Form, Head } from '@inertiajs/react';
import { ArrowRight, Lock, Mail, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

type Props = {
    status?: string;
};

function PortalLogo() {
    return (
        <img
            src="/assets/logo-simgroup.png"
            alt="SIMGROUP"
            width={150}
            height={39}
            className="h-[39px] w-auto"
        />
    );
}

export default function Login({ status }: Props) {
    return (
        <>
            <Head title="Masuk" />

            <div className="flex min-h-dvh">
                <main
                    className="flex flex-1 justify-center bg-[#F7F8FD] px-4 py-8 sm:px-8"
                    style={{
                        backgroundImage: "url('/assets/pattern-bg.svg')",
                        backgroundSize: '304px 304px',
                    }}
                >
                    <div className="relative z-10 my-auto w-full max-w-[440px] rounded-3xl border border-slate-100 bg-white p-7 shadow-sm sm:p-9">
                        <PortalLogo />

                        <div className="mt-7">
                            <h1 className="text-2xl font-bold text-slate-900 sm:text-[1.75rem]">
                                Masuk ke akun Anda
                            </h1>
                            <p className="mt-2 text-sm leading-relaxed text-slate-500">
                                Akses sistem untuk monitoring, proposal, dan AI
                                scoring dalam satu portal terpadu.
                            </p>
                        </div>

                        {status && (
                            <div className="mt-6 rounded-lg bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700">
                                {status}
                            </div>
                        )}

                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            className="mt-6 flex flex-col gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <div className="relative">
                                            <Mail className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                required
                                                autoFocus
                                                tabIndex={1}
                                                autoComplete="email"
                                                placeholder="nama@perusahaan.co.id"
                                                className="h-11 rounded-xl bg-white pl-10"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            Password
                                        </Label>
                                        <div className="relative">
                                            <Lock className="pointer-events-none absolute top-1/2 left-3.5 z-10 size-4 -translate-y-1/2 text-slate-400" />
                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                required
                                                tabIndex={2}
                                                autoComplete="current-password"
                                                placeholder="Masukkan password"
                                                className="h-11 rounded-xl bg-white pl-10"
                                            />
                                        </div>
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2.5">
                                            <Checkbox
                                                id="remember"
                                                name="remember"
                                                tabIndex={3}
                                            />
                                            <Label
                                                htmlFor="remember"
                                                className="font-normal text-slate-600"
                                            >
                                                Ingat saya
                                            </Label>
                                        </div>
                                    </div>

                                    <Button
                                        type="submit"
                                        className="relative mt-1 h-11 w-full rounded-xl text-[15px] font-semibold shadow-lg shadow-primary/25"
                                        tabIndex={4}
                                        disabled={processing}
                                        data-test="login-button"
                                    >
                                        {processing && <Spinner />}
                                        Masuk
                                        <ArrowRight className="absolute right-5 size-5" />
                                    </Button>
                                </>
                            )}
                        </Form>

                        <p className="mt-6 flex items-center justify-center gap-2 text-center text-[11px] text-slate-500">
                            <ShieldCheck className="size-4 shrink-0" />
                            Akses aman untuk monitoring, proposal, dan AI
                            scoring
                        </p>
                    </div>
                </main>
            </div>
        </>
    );
}
