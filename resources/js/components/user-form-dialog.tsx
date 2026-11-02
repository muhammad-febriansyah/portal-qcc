import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
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
    role: string | null;
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
    const [role, setRole] = useState<string>(user?.role ?? '');

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
                        error={errors.sim_id}
                    >
                        <Input
                            name="sim_id"
                            defaultValue={user?.sim_id ?? ''}
                            placeholder="Contoh: SIM-12345"
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
                        id="user-role"
                        label="Role"
                        required
                        error={errors.role}
                    >
                        <Select
                            name="role"
                            value={role}
                            onValueChange={(value) => setRole(value ?? '')}
                            items={roleOptions.map((name) => ({
                                value: name,
                                label: name,
                            }))}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Pilih role" />
                            </SelectTrigger>
                            <SelectContent>
                                {roleOptions.map((name) => (
                                    <SelectItem key={name} value={name}>
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
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
