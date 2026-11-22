<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { PublicLegalContent } from '@/types/public-site';

defineProps<{ legal: PublicLegalContent; canRegister: boolean }>();
</script>

<template>
    <Head :title="legal.title"
        ><meta
            name="description"
            :content="
                legal.title +
                ' · Texto publicado en el sitio de referencia de Ascanio Publishing.'
            "
    /></Head>
    <PublicLayout :can-register="canRegister">
        <article class="public-legal" aria-labelledby="legal-title">
            <div class="public-legal-inner">
                <h1 id="legal-title">{{ legal.title }}</h1>
                <aside
                    v-if="legal.reviewRequired"
                    class="public-legal-note"
                    aria-label="Estado del texto de referencia"
                >
                    <p>
                        Texto publicado en ascaniopub.com, pendiente de revisión
                        editorial para esta nueva web.
                    </p>
                    <a :href="legal.sourceUrl">Ver texto en el sitio actual</a>
                </aside>
                <!-- Only versioned editorial HTML with a tested tag/link allowlist. -->
                <div class="public-legal-copy" v-html="legal.html" />
            </div>
        </article>
    </PublicLayout>
</template>
