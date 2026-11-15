<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkspaceNav from '@/components/WorkspaceNav.vue';
import WorkspacePagination from '@/components/WorkspacePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { index, members as membersRoute } from '@/routes/workspaces';
import { revoke } from '@/routes/workspaces/members';
import type { Paginated, Workspace } from '@/types/workspace';

type Member = {
    id: string;
    name: string;
    status: string;
    is_owner: boolean;
    accepted_at: string;
};
const props = defineProps<{
    workspace: Workspace;
    members: Paginated<Member>;
}>();
const form = useForm<{ password: string; membership?: string }>({
    password: '',
});
const search = ref(
    new URL(usePage().url, 'https://ascanio.invalid').searchParams.get(
        'search',
    ) ?? '',
);
const statuses: Record<string, string> = {
    active: 'Activa',
    suspended: 'Suspendida',
    revoked: 'Revocada',
};
const breadcrumbs = [{ title: 'Mis espacios', href: index() }];
function revokeMember(member: Member) {
    if (
        window.confirm(
            `¿Revocar la membresía de ${member.name} en este espacio?`,
        )
    ) {
        form.delete(
            revoke({ workspace: props.workspace.id, membership: member.id })
                .url,
            { onSuccess: () => form.reset('password') },
        );
    }
}
</script>

<template>
    <Head :title="`Membresías · ${workspace.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="flex max-w-3xl flex-col gap-6 p-6">
            <WorkspaceNav :workspace="workspace" />
            <h1 class="text-2xl font-semibold">
                Membresías de {{ workspace.name }}
            </h1>
            <form
                class="flex items-end gap-3"
                @submit.prevent="
                    router.get(membersRoute(workspace.id).url, { search })
                "
            >
                <div class="grid flex-1 gap-2">
                    <Label for="member-search">Buscar por nombre</Label
                    ><Input
                        id="member-search"
                        v-model="search"
                        maxlength="120"
                    />
                </div>
                <Button variant="outline">Buscar</Button>
            </form>
            <p class="text-sm">
                {{ members.total }} membresías · Horario de
                {{ workspace.timezone }}
            </p>
            <div
                v-if="workspace.can_revoke_members"
                class="grid max-w-sm gap-2"
            >
                <Label for="revoke-password"
                    >Tu contraseña para revocar una membresía</Label
                ><PasswordInput
                    id="revoke-password"
                    v-model="form.password"
                    autocomplete="current-password"
                /><InputError :message="form.errors.password" /><InputError
                    :message="form.errors.membership"
                />
            </div>
            <ul class="grid gap-3">
                <li
                    v-for="member in members.data"
                    :key="member.id"
                    class="flex items-center justify-between gap-4 rounded-lg border p-4"
                >
                    <div>
                        <p class="font-medium">{{ member.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ member.is_owner ? 'Propietario · ' : ''
                            }}{{ statuses[member.status] }} ·
                            {{ member.accepted_at }}
                        </p>
                    </div>
                    <Button
                        v-if="
                            workspace.can_revoke_members &&
                            member.status !== 'revoked'
                        "
                        variant="destructive"
                        :disabled="form.processing || !form.password"
                        @click="revokeMember(member)"
                        >Revocar</Button
                    >
                </li>
            </ul>
            <WorkspacePagination v-bind="members" />
        </section>
    </AppLayout>
</template>
