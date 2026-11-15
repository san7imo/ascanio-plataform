<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import WorkspaceNav from '@/components/WorkspaceNav.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { edit, index, show, store, update } from '@/routes/parties';
import type {
    PartyActivity,
    PartyDetail,
    PartyIdentifier,
} from '@/types/party';
import type { Workspace } from '@/types/workspace';

const props = defineProps<{
    workspace: Workspace;
    party: PartyDetail | null;
    activities: PartyActivity[];
}>();
const initial = () => ({
    kind: props.party?.kind ?? ('person' as 'person' | 'organization'),
    display_name: props.party?.display_name ?? '',
    legal_name: props.party?.private?.legal_name ?? '',
    email: props.party?.private?.email ?? '',
    phone: props.party?.private?.phone ?? '',
    notes: props.party?.private?.notes ?? '',
    source_note: props.party?.private?.source_note ?? '',
    business_type_codes:
        props.party?.private?.activities.map((activity) => activity.code) ??
        ([] as string[]),
    identifiers:
        props.party?.private?.identifiers.map((identifier) => ({
            ...identifier,
            issuer: identifier.issuer ?? '',
        })) ?? ([] as (Omit<PartyIdentifier, 'issuer'> & { issuer: string })[]),
    version: props.party?.version ?? null,
});
const form = useForm(initial());
watch(
    () => `${props.workspace.id}:${props.party?.id}`,
    () => {
        form.defaults(initial());
        form.reset();
        form.clearErrors();
    },
);
const error = (field: string) => (form.errors as Record<string, string>)[field];
function submit() {
    if (props.party) {
        form.patch(update([props.workspace.id, props.party.id]).url);
    } else {
        form.post(store(props.workspace.id).url);
    }
}
function toggleActivity(code: string, checked: boolean) {
    form.business_type_codes = checked
        ? [...form.business_type_codes, code]
        : form.business_type_codes.filter((value) => value !== code);
}
function reload() {
    if (props.party) {
        router.visit(edit([props.workspace.id, props.party.id]).url, {
            preserveState: false,
        });
    }
}
</script>

<template>
    <Head
        :title="party ? `Editar ${party.display_name}` : 'Crear contraparte'"
    />
    <AppLayout
        :breadcrumbs="[{ title: 'Contrapartes', href: index(workspace.id) }]"
    >
        <section class="grid max-w-3xl gap-6 p-4 sm:p-6">
            <WorkspaceNav :workspace="workspace" />
            <h1 class="text-2xl font-semibold">
                {{ party ? 'Editar ficha' : 'Crear ficha de contraparte' }}
            </h1>
            <p class="text-sm text-muted-foreground">
                Captura manual y privada en {{ workspace.name }}. No crea una
                cuenta ni vincula una identidad global. Los datos permanecen
                provisionales y cada guardado conserva una revisión.
            </p>
            <div
                v-if="form.errors.version"
                role="alert"
                class="grid gap-3 rounded-lg border p-4"
            >
                <InputError :message="form.errors.version" />
                <p class="text-sm">
                    Copia los cambios que quieras conservar antes de recargar.
                </p>
                <Button variant="secondary" class="w-fit" @click="reload"
                    >Recargar ficha</Button
                >
            </div>
            <form class="grid gap-6" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="kind">Tipo declarado</Label
                        ><select
                            id="kind"
                            v-model="form.kind"
                            class="rounded-md border bg-background p-2"
                        >
                            <option value="person">Persona</option>
                            <option value="organization">
                                Organización
                            </option></select
                        ><InputError :message="form.errors.kind" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="display_name"
                            >Nombre visible en el espacio</Label
                        ><Input
                            id="display_name"
                            v-model="form.display_name"
                            required
                            maxlength="160"
                        /><InputError :message="form.errors.display_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="legal_name"
                            >Nombre legal declarado (opcional)</Label
                        ><Input
                            id="legal_name"
                            v-model="form.legal_name"
                            maxlength="160"
                        /><InputError :message="form.errors.legal_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Correo de contacto (opcional)</Label
                        ><Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            maxlength="254"
                        /><InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="phone">Teléfono (opcional)</Label
                        ><Input
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            maxlength="40"
                        /><InputError :message="form.errors.phone" />
                    </div>
                </div>
                <fieldset class="grid gap-3">
                    <legend class="mb-3 font-medium">
                        Actividades declaradas (opcional)
                    </legend>
                    <p class="text-sm text-muted-foreground">
                        Selecciona varias si corresponde. No conceden permisos
                        ni sustituyen créditos en una obra.
                    </p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="activity in activities"
                            :key="activity.code"
                            class="flex items-center gap-2 text-sm"
                            ><input
                                type="checkbox"
                                :checked="
                                    form.business_type_codes.includes(
                                        activity.code,
                                    )
                                "
                                @change="
                                    toggleActivity(
                                        activity.code,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />{{ activity.name }}</label
                        >
                    </div>
                    <InputError
                        :message="form.errors.business_type_codes"
                    /><InputError
                        v-for="(_, i) in form.business_type_codes"
                        :key="i"
                        :message="error(`business_type_codes.${i}`)"
                    />
                </fieldset>
                <fieldset class="grid gap-3">
                    <legend class="mb-3 font-medium">
                        Identificadores declarados (opcional)
                    </legend>
                    <p class="text-sm text-muted-foreground">
                        Se conservan como texto, incluidos ceros iniciales. No
                        se consultan registros externos ni se buscan
                        coincidencias con otras cuentas o espacios.
                    </p>
                    <div
                        v-for="(identifier, i) in form.identifiers"
                        :key="i"
                        class="grid gap-3 rounded-lg border p-4"
                    >
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label :for="`scheme-${i}`">Esquema</Label
                                ><select
                                    :id="`scheme-${i}`"
                                    v-model="identifier.scheme"
                                    class="rounded-md border bg-background p-2"
                                >
                                    <option value="ipi">IPI/CAE</option>
                                    <option value="isni">ISNI</option>
                                    <option value="other">Otro</option></select
                                ><InputError
                                    :message="error(`identifiers.${i}.scheme`)"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`identifier-${i}`"
                                    >Valor declarado</Label
                                ><Input
                                    :id="`identifier-${i}`"
                                    v-model="identifier.value"
                                    required
                                    maxlength="120"
                                /><InputError
                                    :message="error(`identifiers.${i}.value`)"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`issuer-${i}`"
                                    >Emisor declarado (opcional)</Label
                                ><Input
                                    :id="`issuer-${i}`"
                                    v-model="identifier.issuer"
                                    maxlength="160"
                                /><InputError
                                    :message="error(`identifiers.${i}.issuer`)"
                                />
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="secondary"
                            class="w-fit"
                            @click="form.identifiers.splice(i, 1)"
                            >Quitar identificador {{ i + 1 }}</Button
                        >
                    </div>
                    <InputError :message="form.errors.identifiers" /><Button
                        type="button"
                        variant="outline"
                        class="w-fit"
                        :disabled="form.identifiers.length >= 20"
                        @click="
                            form.identifiers.push({
                                scheme: 'ipi',
                                value: '',
                                issuer: '',
                            })
                        "
                        >Añadir identificador</Button
                    >
                </fieldset>
                <div class="grid gap-2">
                    <Label for="source_note"
                        >Procedencia declarada (opcional)</Label
                    ><textarea
                        id="source_note"
                        v-model="form.source_note"
                        rows="3"
                        maxlength="500"
                        class="rounded-md border bg-background p-3"
                        placeholder="De dónde provienen estos datos, sin adjuntar documentos"
                    /><InputError :message="form.errors.source_note" />
                </div>
                <div class="grid gap-2">
                    <Label for="notes">Notas privadas (opcional)</Label
                    ><textarea
                        id="notes"
                        v-model="form.notes"
                        rows="4"
                        maxlength="2000"
                        class="rounded-md border bg-background p-3"
                    /><InputError :message="form.errors.notes" />
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <Button :disabled="form.processing">{{
                        party ? 'Guardar nueva revisión' : 'Crear ficha'
                    }}</Button
                    ><Link
                        :href="
                            party
                                ? show([workspace.id, party.id])
                                : index(workspace.id)
                        "
                        class="text-sm underline"
                        >Cancelar</Link
                    >
                </div>
            </form>
        </section>
    </AppLayout>
</template>
