<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicHero from '@/components/PublicHero.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { PublicGuideContent } from '@/types/public-site';

defineProps<{ guide: PublicGuideContent; canRegister: boolean }>();
</script>

<template>
    <Head :title="guide.title"
        ><meta name="description" :content="guide.subtitle"
    /></Head>
    <PublicLayout :can-register="canRegister">
        <PublicHero
            :title="guide.title"
            :subtitle="guide.subtitle"
            :image="guide.hero"
            service
        />
        <section class="public-service-intro" aria-label="Guía de distribución">
            <p>{{ guide.paragraphs[0] }}</p>
        </section>
        <section class="public-guide" aria-label="Guía: tus primeros pasos">
            <details
                v-for="(section, index) in guide.sections"
                :id="section.id"
                :key="section.id"
                name="public-guide"
                :open="index === 0"
            >
                <summary>{{ section.title }}</summary>
                <!-- Trusted, versioned editorial HTML, sanitized offline and checked
                     by PublicSiteTest. Never render request or tenant HTML here. -->
                <div class="public-guide-content" v-html="section.html" />
            </details>
        </section>
    </PublicLayout>
</template>
