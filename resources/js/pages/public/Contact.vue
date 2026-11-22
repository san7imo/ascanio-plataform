<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Mail, MapPin, Phone } from 'lucide-vue-next';
import PublicFieldPreview from '@/components/PublicFieldPreview.vue';
import PublicHero from '@/components/PublicHero.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { PublicContactContent } from '@/types/public-site';

defineProps<{ contact: PublicContactContent; canRegister: boolean }>();
</script>

<template>
    <Head title="Contacto"
        ><meta name="description" :content="contact.subtitle"
    /></Head>
    <PublicLayout :can-register="canRegister">
        <PublicHero
            :title="contact.title"
            :subtitle="contact.subtitle"
            :image="contact.hero"
            service
        />
        <section class="public-contact" aria-labelledby="contact-title">
            <h2 id="contact-title">{{ contact.heading }}</h2>
            <div class="public-contact-columns">
                <div class="public-contact-channels">
                    <h3>¡Contáctanos!</h3>
                    <a :href="'mailto:' + contact.email"
                        ><Mail :size="48" aria-hidden="true" /><span>{{
                            contact.email
                        }}</span></a
                    >
                    <a :href="'tel:' + contact.phone.replaceAll(' ', '')"
                        ><Phone :size="48" aria-hidden="true" /><span>{{
                            contact.phone
                        }}</span></a
                    >
                    <p>
                        <MapPin :size="48" aria-hidden="true" /><span>{{
                            contact.address
                        }}</span>
                    </p>
                </div>
                <div class="public-contact-form-preview">
                    <p id="contact-channel-note" class="public-channel-note">
                        Para enviarnos un mensaje, abre el formulario del sitio
                        actual. Los campos se completan allí.
                    </p>
                    <PublicFieldPreview
                        :fields="contact.fields"
                        scope="contact"
                        description-id="contact-channel-note"
                    />
                    <a
                        :href="contact.formUrl"
                        class="public-contact-button public-contact-form-link"
                        >Abrir formulario actual</a
                    >
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
