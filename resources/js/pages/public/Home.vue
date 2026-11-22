<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { currentSite, publicServices } from '@/lib/public-site';

defineProps<{ canRegister: boolean }>();
</script>

<template>
    <Head title="Ascanio Publishing">
        <meta
            name="description"
            content="Distribución musical, publishing, identidad visual y servicios para artistas independientes y sellos discográficos. Ascanio Publishing."
        />
    </Head>
    <PublicLayout :can-register="canRegister">
        <section class="public-hero" aria-labelledby="hero-title">
            <img
                class="public-hero-art"
                src="/assets/ascanio/hero.jpg"
                alt=""
                width="1920"
                height="767"
                fetchpriority="high"
            />
            <div class="public-hero-content">
                <h1 id="hero-title">Distribución Musical</h1>
                <p>
                    Nos especializamos en distribuir y viralizar tu música en
                    cualquier parte del mundo
                </p>
                <ul class="public-platforms" aria-label="Plataformas musicales">
                    <li>
                        <img
                            src="/assets/ascanio/spotify.png"
                            alt="Spotify"
                            width="120"
                            height="36"
                        />
                    </li>
                    <li>
                        <img
                            src="/assets/ascanio/apple-music.png"
                            alt="Apple Music"
                            width="120"
                            height="30"
                        />
                    </li>
                    <li>
                        <img
                            src="/assets/ascanio/youtube.png"
                            alt="YouTube"
                            width="126"
                            height="29"
                        />
                    </li>
                </ul>
            </div>
        </section>
        <section class="public-intro" aria-labelledby="intro-title">
            <h2 id="intro-title">
                Tu música en las calles <span>del Mundo</span>
            </h2>
            <p>
                En Ascanio Publishing te brindamos servicios como artista
                independiente o sello discográfico, desde la distribución,
                monetización, registros hasta el asesoramiento de tu Branding,
                para el crecimiento de tu carrera musical.
            </p>
        </section>
        <section class="public-services" aria-label="Nuestros servicios">
            <a
                v-for="service in publicServices"
                :key="service.path"
                :href="currentSite + service.path"
                class="public-service"
            >
                <img
                    :src="'/assets/ascanio/' + service.image"
                    :alt="service.label"
                    width="1024"
                    height="1024"
                    loading="lazy"
                    decoding="async"
                />
            </a>
            <div class="public-service">
                <img
                    src="/assets/ascanio/merchandising.webp"
                    alt="Merchandising"
                    width="1024"
                    height="1024"
                    loading="lazy"
                    decoding="async"
                />
            </div>
        </section>
        <section class="public-advice" aria-labelledby="advice-title">
            <div class="public-advice-content">
                <h2 id="advice-title">
                    ¿Necesitas asesoramiento legal en el mundo de la música?
                </h2>
                <p>
                    Descubre que necesitas para iniciar en este mundo como un
                    artista completo
                </p>
                <a
                    :href="currentSite + '/contacto/'"
                    class="public-contact-button"
                    ><Mail :size="16" aria-hidden="true" /> Contáctanos</a
                >
            </div>
        </section>
    </PublicLayout>
</template>
