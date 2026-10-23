import { Form, Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ComponentProps } from 'react';
import { ArrowRight, BadgeCheck, Lock, Mail, User } from 'lucide-react';
import AuthShowcase, {
    authInputClassName,
    PortalLogo,
} from '@/components/auth/auth-showcase';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

type IconFieldProps = Omit<ComponentProps<'input'>, 'type'> & {
    icon: LucideIcon;
    type?: 'text' | 'email' | 'password';
    passwordrules?: string;
};

/**
 * An input with a leading icon. FormField passes id, required and aria props
 * to its child, so they are forwarded to the real input and not to the wrapper.
 */
function IconField({
    icon: Icon,
    type = 'text',
    className = '',
    ...props
}: IconFieldProps) {
    return (
        <div className="relative">
            <Icon
                className="pointer-events-none absolute top-1/2 left-4 z-10 size-4 -translate-y-1/2 text-[#838592]"
                strokeWidth={1.8}
            />
            {type === 'password' ? (
                <PasswordInput
                    className={`${authInputClassName} pr-11 ${className}`}
                    {...props}
                />
            ) : (
                <Input
                    type={type}
                    className={`${authInputClassName} ${className}`}
                    {...props}
                />
            )}
        </div>
    );
}

export default function Register({ passwordRules }: Props) {
    return (
        <AuthShowcase title="Daftar">
            <div className="w-full max-w-[350px]">
                <PortalLogo />
                <h2 className="mt-8 text-[26px] leading-tight font-bold tracking-[-0.02em] sm:text-[28px]">
                    Daftar sebagai SIMer
                </h2>
                <p className="mt-3 max-w-[330px] text-sm leading-6 text-[#5e6779]">
                    Buat akun dulu. Profil dan kompetensi bisa dilengkapi
                    setelah masuk.
                </p>

                <Form
                    {...store.form()}
                    resetOnSuccess={['password', 'password_confirmation']}
                    disableWhileProcessing
                    className="mt-6 grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField
                                id="sim_id"
                                label="SIM ID"
                                required
                                error={errors.sim_id}
                            >
                                <IconField
                                    icon={BadgeCheck}
                                    name="sim_id"
                                    required
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="Contoh: SIM12345"
                                    className="uppercase placeholder:normal-case"
                                />
                            </FormField>

                            <FormField
                                id="name"
                                label="Nama lengkap"
                                required
                                error={errors.name}
                            >
                                <IconField
                                    icon={User}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Nama sesuai identitas kerja"
                                />
                            </FormField>

                            <FormField
                                id="email"
                                label="Email"
                                required
                                error={errors.email}
                            >
                                <IconField
                                    icon={Mail}
                                    type="email"
                                    name="email"
                                    required
                                    autoComplete="email"
                                    placeholder="nama@perusahaan.co.id"
                                />
                            </FormField>

                            <FormField
                                id="password"
                                label="Password"
                                required
                                error={errors.password}
                            >
                                <IconField
                                    icon={Lock}
                                    type="password"
                                    name="password"
                                    required
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                    placeholder="Buat password"
                                />
                            </FormField>

                            <FormField
                                id="password_confirmation"
                                label="Ulangi password"
                                required
                                error={errors.password_confirmation}
                            >
                                <IconField
                                    icon={Lock}
                                    type="password"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    placeholder="Ulangi password"
                                />
                            </FormField>

                            <Button
                                type="submit"
                                className="relative mt-2 h-12 w-full rounded-[10px] text-base font-semibold shadow-[0_6px_14px_rgba(11,92,240,0.18)] transition-[transform,box-shadow] hover:shadow-[0_8px_18px_rgba(11,92,240,0.24)] active:translate-y-px"
                                disabled={processing}
                                data-test="register-button"
                            >
                                {processing && <Spinner />}
                                Daftar
                                <ArrowRight className="absolute right-6 size-[18px]" />
                            </Button>
                        </>
                    )}
                </Form>

                <p className="mt-5 text-center text-sm text-[#5e6779]">
                    Sudah punya akun?{' '}
                    <Link
                        href={login()}
                        className="font-semibold text-primary hover:underline"
                    >
                        Masuk
                    </Link>
                </p>
            </div>
        </AuthShowcase>
    );
}
