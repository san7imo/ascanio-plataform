<script setup lang="ts">
import type { PartyPrivate } from '@/types/party';

defineProps<{ details: PartyPrivate }>();
const schemes = { ipi: 'IPI/CAE', isni: 'ISNI', other: 'Otro' };
</script>

<template>
    <div class="grid min-w-0 gap-5 break-words">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-muted-foreground">Nombre legal declarado</dt>
                <dd>{{ details.legal_name || 'Sin indicar' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Correo de contacto</dt>
                <dd>{{ details.email || 'Sin indicar' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Teléfono</dt>
                <dd>{{ details.phone || 'Sin indicar' }}</dd>
            </div>
        </dl>
        <div>
            <h3 class="text-sm font-medium">Actividades declaradas</h3>
            <p class="text-sm">
                {{
                    details.activities
                        .map((activity) => activity.name)
                        .join(' · ') || 'Sin indicar'
                }}
            </p>
        </div>
        <div>
            <h3 class="text-sm font-medium">Identificadores declarados</h3>
            <ul
                v-if="details.identifiers.length"
                class="mt-2 grid gap-2 text-sm"
            >
                <li
                    v-for="identifier in details.identifiers"
                    :key="`${identifier.scheme}:${identifier.value}`"
                >
                    <span class="font-medium"
                        >{{ schemes[identifier.scheme] }}:</span
                    >
                    {{ identifier.value
                    }}<span v-if="identifier.issuer">
                        · Emisor: {{ identifier.issuer }}</span
                    >
                </li>
            </ul>
            <p v-else class="text-sm">Sin indicar</p>
        </div>
        <div>
            <h3 class="text-sm font-medium">Notas privadas</h3>
            <p class="text-sm whitespace-pre-wrap">
                {{ details.notes || 'Sin notas' }}
            </p>
        </div>
        <div>
            <h3 class="text-sm font-medium">Procedencia declarada</h3>
            <p class="text-sm whitespace-pre-wrap">
                {{
                    details.source_note ||
                    'Captura manual, sin detalles adicionales'
                }}
            </p>
        </div>
    </div>
</template>
