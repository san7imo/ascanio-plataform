<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicFieldPreview from '@/components/PublicFieldPreview.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { publicHref } from '@/lib/public-site';
import type { PublicProductContent } from '@/types/public-site';

defineProps<{ product: PublicProductContent; canRegister: boolean }>();
</script>

<template>
    <Head :title="product.title"
        ><meta
            name="description"
            :content="
                product.title +
                ' · Ascanio Publishing. Configuración y compra en la tienda actual.'
            "
    /></Head>
    <PublicLayout :can-register="canRegister">
        <article class="public-product" aria-labelledby="product-title">
            <img
                class="public-product-image"
                :src="product.image.src"
                :alt="product.image.alt"
                :width="product.image.width"
                :height="product.image.height"
                fetchpriority="high"
            />
            <h1 id="product-title">{{ product.title }}</h1>
            <p v-if="product.price" class="public-product-price">
                {{ product.price }}
            </p>
            <p id="purchase-channel-note" class="public-channel-note">
                La configuración y la compra se realizan en la tienda actual.
                Confirma allí las opciones, el precio y las condiciones
                vigentes.
            </p>
            <PublicFieldPreview
                :fields="product.fields"
                :scope="product.key"
                description-id="purchase-channel-note"
            />
            <a :href="product.purchaseUrl" class="public-purchase-link"
                >Elegir y comprar en la tienda actual</a
            >
            <a :href="publicHref('/shop/')" class="public-back-to-shop"
                >Ver todos los productos</a
            >
        </article>
    </PublicLayout>
</template>
