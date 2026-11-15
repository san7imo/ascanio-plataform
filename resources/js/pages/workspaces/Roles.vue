<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import SensitiveConfirmation from '@/components/SensitiveConfirmation.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkspaceNav from '@/components/WorkspaceNav.vue';
import WorkspacePagination from '@/components/WorkspacePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index, roles as rolesRoute } from '@/routes/workspaces';
import { destroy, store, update } from '@/routes/workspaces/roles';
import type { Paginated, Workspace } from '@/types/workspace';

type Permission = { code: string; delegable: boolean };
type Role = {
    id: string;
    name: string;
    protected: boolean;
    permissions: (Permission & { label: string })[];
    can_edit: boolean;
};
const props = defineProps<{
    workspace: Workspace;
    roles: Paginated<Role>;
    capabilities: { code: string; label: string }[];
    mfaReady: boolean;
}>();
const selected = ref<Role | null>(null);
const form = useForm({
    name: '',
    permissions: [] as Permission[],
    password: '',
    code: '',
});
const search = ref(
    new URL(usePage().url, 'https://ascanio.invalid').searchParams.get(
        'search',
    ) ?? '',
);
const breadcrumbs = [{ title: 'Mis espacios', href: index() }];
function edit(role: Role | null) {
    selected.value = role;
    form.reset();
    form.clearErrors();
    form.name = role?.name ?? '';
    form.permissions =
        role?.permissions.map((p) => ({
            code: p.code,
            delegable: p.delegable,
        })) ?? [];
}
function toggle(code: string, enabled: boolean) {
    form.permissions = form.permissions.filter((p) => p.code !== code);

    if (enabled) {
        form.permissions.push({ code, delegable: false });
    }
}
function save() {
    const options = {
        onSuccess: () => edit(null),
        onFinish: () => form.reset('password', 'code'),
    };

    if (selected.value) {
        form.patch(
            update({ workspace: props.workspace.id, role: selected.value.id })
                .url,
            options,
        );
    } else {
        form.post(store(props.workspace.id).url, options);
    }
}
function remove() {
    if (
        !selected.value ||
        !window.confirm('¿Eliminar este rol y retirar todas sus asignaciones?')
    ) {
        return;
    }

    form.delete(
        destroy({ workspace: props.workspace.id, role: selected.value.id }).url,
        {
            onSuccess: () => edit(null),
            onFinish: () => form.reset('password', 'code'),
        },
    );
}
</script>

<template>
    <Head :title="'Roles · ' + workspace.name" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="flex max-w-4xl flex-col gap-6 p-6">
            <WorkspaceNav :workspace="workspace" />
            <h1 class="text-2xl font-semibold">
                Roles de {{ workspace.name }}
            </h1>
            <form
                class="flex items-end gap-3"
                @submit.prevent="
                    router.get(rolesRoute(workspace.id).url, { search })
                "
            >
                <div class="grid flex-1 gap-2">
                    <Label for="role-search">Buscar por nombre</Label
                    ><Input id="role-search" v-model="search" maxlength="120" />
                </div>
                <Button variant="outline">Buscar</Button>
            </form>
            <p class="text-sm">{{ roles.total }} roles en este espacio</p>
            <ul class="grid gap-3">
                <li
                    v-for="role in roles.data"
                    :key="role.id"
                    class="rounded-lg border p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-medium">
                            {{ role.name }}
                            <span
                                v-if="role.protected"
                                class="text-sm text-muted-foreground"
                                >· Gobierno protegido</span
                            >
                        </h2>
                        <Button
                            v-if="role.can_edit"
                            variant="outline"
                            @click="edit(role)"
                            >Editar</Button
                        >
                    </div>
                    <ul class="mt-2 text-sm text-muted-foreground">
                        <li
                            v-for="permission in role.permissions"
                            :key="permission.code"
                        >
                            {{ permission.label
                            }}{{
                                permission.delegable ? ' · Puede delegarse' : ''
                            }}
                        </li>
                    </ul>
                    <p
                        v-if="!role.can_edit && !role.protected"
                        class="mt-2 text-sm"
                    >
                        No tienes autoridad delegable para modificar este rol.
                    </p>
                </li>
            </ul>
            <WorkspacePagination v-bind="roles" />
            <form
                class="grid gap-4 rounded-lg border p-5"
                @submit.prevent="save"
            >
                <h2 class="text-lg font-semibold">
                    {{ selected ? 'Editar rol' : 'Crear rol' }}
                </h2>
                <Label for="role-name">Nombre</Label
                ><Input
                    id="role-name"
                    v-model="form.name"
                    maxlength="120"
                    required
                />
                <fieldset class="grid gap-3">
                    <legend class="mb-2 font-medium">
                        Capacidades que puedes delegar
                    </legend>
                    <div
                        v-for="capability in capabilities"
                        :key="capability.code"
                        class="grid gap-2"
                    >
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                :checked="
                                    form.permissions.some(
                                        (p) => p.code === capability.code,
                                    )
                                "
                                @change="
                                    toggle(
                                        capability.code,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            {{ capability.label }}
                        </label>
                        <label
                            v-if="
                                form.permissions.find(
                                    (p) => p.code === capability.code,
                                )
                            "
                            class="ml-6 flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                :checked="
                                    form.permissions.find(
                                        (p) => p.code === capability.code,
                                    )?.delegable
                                "
                                @change="
                                    form.permissions.find(
                                        (p) => p.code === capability.code,
                                    )!.delegable = (
                                        $event.target as HTMLInputElement
                                    ).checked
                                "
                            />
                            Permitir que quien reciba el rol delegue esta
                            capacidad
                        </label>
                    </div>
                </fieldset>
                <SensitiveConfirmation
                    id="role-proof"
                    v-model:password="form.password"
                    v-model:code="form.code"
                    :mfa-ready="mfaReady"
                    :errors="form.errors"
                />
                <ul
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="text-sm text-red-600"
                >
                    <li v-for="(error, key) in form.errors" :key="key">
                        {{ error }}
                    </li>
                </ul>
                <div class="flex flex-wrap gap-3">
                    <Button :disabled="form.processing || !mfaReady">{{
                        selected ? 'Guardar cambios' : 'Crear rol'
                    }}</Button>
                    <Button
                        v-if="selected"
                        type="button"
                        variant="destructive"
                        :disabled="form.processing || !mfaReady"
                        @click="remove"
                        >Eliminar rol</Button
                    >
                    <Button
                        v-if="selected"
                        type="button"
                        variant="outline"
                        @click="edit(null)"
                        >Cancelar edición</Button
                    >
                </div>
            </form>
        </section>
    </AppLayout>
</template>
