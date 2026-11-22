<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicGallery from '@/components/PublicGallery.vue';
import PublicHero from '@/components/PublicHero.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { publicHref } from '@/lib/public-site';
import type { PublicServiceContent } from '@/types/public-site';

defineProps<{ service: PublicServiceContent; canRegister: boolean }>();
</script>

<template>
    <Head :title="service.title"
        ><meta name="description" :content="service.subtitle"
    /></Head>
    <PublicLayout :can-register="canRegister">
        <PublicHero
            :title="service.title"
            :subtitle="service.subtitle"
            :image="service.hero"
            service
        />
        <section
            class="public-service-intro"
            :aria-label="'Acerca de ' + service.title"
        >
            <h2 v-if="service.key === 'web'">Sitios web a la medida</h2>
            <p>{{ service.paragraphs[0] }}</p>
            <a
                v-if="service.partner"
                class="public-partner"
                href="https://kromahost.com"
                ><img
                    :src="service.partner.src"
                    :alt="service.partner.alt"
                    :width="service.partner.width"
                    :height="service.partner.height"
                    loading="lazy"
            /></a>
        </section>

        <template v-if="service.key === 'distribution'">
            <section
                class="public-services public-distribution-tiles"
                aria-label="Opciones de distribución"
            >
                <template v-for="tile in service.tiles" :key="tile.src">
                    <a
                        v-if="tile.path"
                        :href="publicHref(tile.path)"
                        class="public-service"
                        ><img
                            :src="tile.src"
                            :alt="tile.alt"
                            :width="tile.width"
                            :height="tile.height"
                            loading="lazy"
                    /></a>
                    <figure v-else class="public-service">
                        <img
                            :src="tile.src"
                            :alt="tile.alt"
                            :width="tile.width"
                            :height="tile.height"
                            loading="lazy"
                        />
                        <figcaption class="public-tile-note">
                            <a :href="publicHref('/contacto/')"
                                >Consultar este servicio</a
                            >
                        </figcaption>
                    </figure>
                </template>
            </section>
            <img
                v-if="service.platforms"
                class="public-distribution-platforms"
                :src="service.platforms.src"
                :alt="service.platforms.alt"
                :width="service.platforms.width"
                :height="service.platforms.height"
                loading="lazy"
            />
        </template>

        <template v-if="service.key === 'visual-identity'">
            <section
                v-for="gallery in service.galleries"
                :key="gallery.kind"
                class="public-portfolio-section"
                :aria-label="gallery.title"
            >
                <h2>{{ gallery.title }}</h2>
                <div
                    :class="{
                        'public-portfolio-split': gallery.kind === 'press',
                    }"
                >
                    <p v-if="gallery.kind === 'press'">
                        {{ service.paragraphs[1] }}
                    </p>
                    <PublicGallery :gallery="gallery" />
                </div>
            </section>
        </template>

        <template v-if="service.key === 'social'">
            <section
                class="public-social-examples"
                aria-label="Propuestas de social media"
            >
                <img
                    v-for="image in service.images?.slice(0, 2)"
                    :key="image.src"
                    :src="image.src"
                    :alt="image.alt"
                    :width="image.width"
                    :height="image.height"
                    loading="lazy"
                />
            </section>
            <section
                v-if="service.images?.[2]"
                class="public-social-contact"
                aria-label="Estrategias de redes sociales"
            >
                <img
                    :src="service.images[2].src"
                    :alt="service.images[2].alt"
                    :width="service.images[2].width"
                    :height="service.images[2].height"
                    loading="lazy"
                />
                <div>
                    <p>{{ service.paragraphs[1] }}</p>
                    <a
                        :href="publicHref('/contacto/')"
                        class="public-secondary-button"
                        >Comunícate</a
                    >
                </div>
            </section>
        </template>

        <template v-if="service.key === 'web'">
            <section class="public-domain" aria-labelledby="domain-title">
                <h2 id="domain-title">Busca tu nombre de dominio</h2>
                <a class="public-secondary-button" href="https://kromahost.com"
                    >Consultar dominios en Kroma Hosting</a
                >
            </section>
            <section
                class="public-hosting-plans"
                aria-label="Planes del proveedor Kroma Hosting"
            >
                <article
                    v-for="plan in service.plans"
                    :key="plan.title"
                    class="public-hosting-plan"
                >
                    <header>
                        <h2>{{ plan.title }}</h2>
                        <p>{{ plan.description }}</p>
                    </header>
                    <p class="public-hosting-price">
                        <span>$</span> {{ plan.price }}
                        <small>{{ plan.period }}</small>
                    </p>
                    <ul>
                        <li v-for="feature in plan.features" :key="feature">
                            {{ feature }}
                        </li>
                    </ul>
                    <a :href="plan.href" class="public-secondary-button"
                        >Continuar en Kroma Hosting</a
                    >
                    <p class="public-hosting-note">{{ plan.footer }}</p>
                </article>
            </section>
            <p class="public-hosting-conditions">
                Consulta precios y condiciones vigentes en Kroma Hosting.
            </p>
            <p class="public-hosting-more">
                <a class="public-secondary-button" href="https://kromahost.com"
                    >Ver más planes en Kroma Hosting</a
                >
            </p>
        </template>
    </PublicLayout>
</template>
