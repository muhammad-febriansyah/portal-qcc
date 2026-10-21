import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile() {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <div className="space-y-6">
                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <FormField id="name" label="Name" required error={errors.name}>
                                <Input
                                    defaultValue={auth.user.name}
                                    name="name"
                                    autoComplete="name"
                                    placeholder="Full name"
                                    className="block w-full"
                                />
                            </FormField>

                            <FormField
                                id="email"
                                label="Email address"
                                required
                                error={errors.email}
                                hint="Use a valid email address."
                            >
                                <Input
                                    type="email"
                                    className="block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    autoComplete="username"
                                    placeholder="Email address"
                                />
                            </FormField>

                            <div className="flex items-center gap-4">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}

