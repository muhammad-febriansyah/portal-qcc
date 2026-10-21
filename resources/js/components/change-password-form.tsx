import { Form } from '@inertiajs/react';
import { useRef } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { FormField } from '@/components/form-field';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    passwordRules: string;
};

export function ChangePasswordForm({ passwordRules }: Props) {
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <Card className="h-fit gap-6 py-6">
            <CardHeader>
                <CardTitle className="text-base font-semibold">
                    Ubah Password
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    {...SecurityController.update.form()}
                    options={{ preserveScroll: true }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        } else if (errors.password) {
                            passwordInput.current?.focus();
                        }
                    }}
                    className="grid gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <FormField
                                id="current_password"
                                label="Password Saat Ini"
                                required
                                error={errors.current_password}
                            >
                                <PasswordInput
                                    ref={currentPasswordInput}
                                    name="current_password"
                                    autoComplete="current-password"
                                    placeholder="Masukkan password saat ini"
                                />
                            </FormField>

                            <div className="grid gap-6 sm:grid-cols-2">
                                <FormField
                                    id="password"
                                    label="Password Baru"
                                    required
                                    error={errors.password}
                                >
                                    <PasswordInput
                                        ref={passwordInput}
                                        name="password"
                                        autoComplete="new-password"
                                        placeholder="Masukkan password baru"
                                        passwordrules={passwordRules}
                                    />
                                </FormField>

                                <FormField
                                    id="password_confirmation"
                                    label="Konfirmasi Password Baru"
                                    required
                                    error={errors.password_confirmation}
                                >
                                    <PasswordInput
                                        name="password_confirmation"
                                        autoComplete="new-password"
                                        placeholder="Ulangi password baru"
                                        passwordrules={passwordRules}
                                    />
                                </FormField>
                            </div>

                            <div>
                                <Button
                                    type="submit"
                                    className="h-10 px-4 text-sm"
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    {processing && <Spinner />}
                                    Ubah Password
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
