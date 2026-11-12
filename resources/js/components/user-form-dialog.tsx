import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { store, update } from '@/routes/admin/users';

export type OrganizationOption = {
    id: number;
    name: string;
    is_active: boolean;
};

export type UserFormValues = {
    id: number;
    name: string;
    sim_id: string | null;
    email: string;
    organization_id: number | null;
    roles: string[];
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: UserFormValues | null;
    roleOptions: string[];
    organizationOptions: OrganizationOption[];
};

function UserForm({
    user,
    roleOptions,
    organizationOptions,
    onClose,
}: Omit<Props, 'open' | 'onOpenChange'> & { onClose: () => void }) {
    const isEditing = user !== null;
    const [organizationId, setOrganizationId] = useState<string>(
        user?.organization_id ? String(user.organization_id) : '',
    );
    const [roles, setRoles] = useState<string[]>(user?.roles ?? []);

    function toggleRole(name: string, checked: boolean): void {
        setRoles((current) =>
            checked
                ? roleOptions.filter(
                      (option) => option === name || current.includes(option),
                  )
                : current.filter((role) => role !== name),
        );
    }

    const selectableOrganizations = organizationOptions.filter(
        (organization) =>
            organization.is_active || organization.id === user?.organization_id,
    );

    return (
        <Form
            {...(isEditing ? update.form(user.id) : store.form())}
            options={{ preserveScroll: true }}
            onSuccess={onClose}
            className="grid gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <FormField
                        id="user-name"
                        label="Nama Lengkap"
                        required
                        error={errors.name}
                    >
                        <Input
                            name="name"
                            defaultValue={user?.name}
                            placeholder="Contoh: Budi Santoso"
                            maxLength={255}
                            autoComplete="off"
                        />
                    </FormField>

                    <FormField
                        id="user-sim-id"
                        label="SIM ID"
                        required
                        hint={
                            !isEditing
                                ? 'Isi angkanya saja. Password awal otomatis "SIM" + nomor SIM ID.'
                                : undefined
                        }
                        error={errors.sim_id}
                    >
                        <Input
                            name="sim_id"
                            defaultValue={user?.sim_id ?? ''}
                            placeholder="Contoh: 12345"
                            maxLength={32}
                            autoComplete="off"
                            className="uppercase placeholder:normal-case"
                        />
                    </FormField>

                    <FormField
                        id="user-email"
                        label="Email Kantor"
                        required
                        error={errors.email}
                    >
                        <Input
                            name="email"
                            type="email"
                            defaultValue={user?.email}
                            placeholder="nama@perusahaan.co.id"
                            maxLength={255}
                            autoComplete="off"
                        />
                    </FormField>

                    <FormField
                        id="user-organization"
                        label="Organisasi"
                        required
                        error={errors.organization_id}
                    >
                        <Select
                            name="organization_id"
                            value={organizationId}
                            onValueChange={(value) =>
                                setOrganizationId(value ?? '')
                            }
                            items={selectableOrganizations.map(
                                (organization) => ({
                                    value: String(organization.id),
                                    label: organization.name,
                                }),
                            )}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Pilih organisasi" />
                            </SelectTrigger>
                            <SelectContent>
                                {selectableOrganizations.map((organization) => (
                                    <SelectItem
                                        key={organization.id}
                                        value={String(organization.id)}
                                    >
                                        {organization.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField
                        id="user-roles"
                        label="Role"
                        required
                        hint="Boleh lebih dari satu. Menu dan akses pengguna menjadi gabungan semua role yang dipilih."
                        error={
                            errors.roles ??
                            Object.entries(errors).find(([key]) =>
                                key.startsWith('roles.'),
                            )?.[1]
                        }
                    >
                        <div
                            role="group"
                            aria-label="Role"
                            className="grid gap-2 rounded-lg border p-3 sm:grid-cols-2"
                        >
                            {roleOptions.map((name) => {
                                const inputId = `user-role-${name.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}`;

                                return (
                                    <label
                                        key={name}
                                        htmlFor={inputId}
                                        className="flex cursor-pointer items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            id={inputId}
                                            checked={roles.includes(name)}
                                            onCheckedChange={(checked) =>
                                                toggleRole(name, checked === true)
                                            }
                                        />
                                        {name}
                                    </label>
                                );
                            })}
                            {roles.map((name) => (
                                <input
                                    key={name}
                                    type="hidden"
                                    name="roles[]"
                                    value={name}
                                />
                            ))}
                        </div>
                    </FormField>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 px-4 text-sm"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            className="h-10 px-4 text-sm"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

export function UserFormDialog({
    open,
    onOpenChange,
    user,
    roleOptions,
    organizationOptions,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-6 sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {user ? 'Ubah Anggota' : 'Tambah Anggota'}
                    </DialogTitle>
                </DialogHeader>
                {open && (
                    <UserForm
                        key={user?.id ?? 'new'}
                        user={user}
                        roleOptions={roleOptions}
                        organizationOptions={organizationOptions}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
