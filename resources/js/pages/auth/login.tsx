import { Form, Link } from '@inertiajs/react';
import { Lock, UserRound } from 'lucide-react';
import AuthShowcase, {
    authInputClassName,
} from '@/components/auth/auth-showcase';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request as forgotPassword } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword?: boolean;
};

export default function Login({ status, canResetPassword = false }: Props) {
    return (
        <AuthShowcase title="Masuk">
            <div className="text-center">
                <h1 className="text-[40px] leading-none font-extrabold tracking-[-0.03em] text-[#0b3a57] sm:text-[48px]">
                    Sign In
                </h1>
                <p className="mt-3 text-lg font-medium text-[#3d5566] sm:text-xl">
                    Portal QCC SIMGROUP
                </p>
            </div>

            {status && (
                <div className="mt-6 rounded-xl border border-success/20 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="mt-8 flex flex-col"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email" className="sr-only">
                                Username / Email Kantor
                            </Label>
                            <div className="relative">
                                <UserRound
                                    className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-[#6b7785]"
                                    strokeWidth={1.8}
                                />
                                <Input
                                    id="email"
                                    type="text"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="username"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    placeholder="Username / Email Kantor"
                                    className={authInputClassName}
                                />
                            </div>
                            <InputError message={errors.email} />
                        </div>

                        <div className="mt-4 grid gap-2">
                            <Label htmlFor="password" className="sr-only">
                                Password
                            </Label>
                            <div className="relative">
                                <Lock
                                    className="pointer-events-none absolute top-1/2 left-4 z-10 size-5 -translate-y-1/2 text-[#6b7785]"
                                    strokeWidth={1.8}
                                />
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                    className={`${authInputClassName} pr-12`}
                                />
                            </div>
                            <InputError message={errors.password} />
                        </div>

                        <div className="mt-5 flex items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                    className="size-5 rounded-[5px] border-[#c9d2dc]"
                                />
                                <Label
                                    htmlFor="remember"
                                    className="text-base font-normal"
                                >
                                    Ingat saya
                                </Label>
                            </div>
                            {canResetPassword && (
                                <Link
                                    href={forgotPassword()}
                                    tabIndex={4}
                                    className="text-base font-semibold text-[#0179A3] hover:underline"
                                >
                                    Lupa password?
                                </Link>
                            )}
                        </div>

                        <Button
                            type="submit"
                            className="mt-8 h-14 w-full rounded-xl bg-[#0191C2] text-[19px] font-bold text-white shadow-[0_10px_24px_-10px_rgba(1,145,194,0.7)] transition-[transform,box-shadow,background-color] hover:bg-[#017ba5] active:translate-y-px"
                            tabIndex={5}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Sign In
                        </Button>

                        <p className="mt-5 text-center text-sm text-[#6b7785]">
                            Masuk dengan email kantor atau SIM ID Anda.
                        </p>
                    </>
                )}
            </Form>
        </AuthShowcase>
    );
}
