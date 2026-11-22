import {
    contact,
    distribution,
    guide,
    media,
    publishing,
    shop,
    social,
    visualIdentity,
    web,
} from '@/routes/public';
import { cookies, notice, privacy } from '@/routes/public/legal';
import { smart, sync, vevo } from '@/routes/public/products';

// The sixteen editorial pages resolve locally. Purchase/contact submissions
// stay on the existing site; production domain cutover is still a PUB2 gate.
export const currentSite = 'https://ascaniopub.com';

const localPages: Record<string, string> = {
    '/distribucion-digital/': distribution().url,
    '/publishing/': publishing().url,
    '/identidad-visual/': visualIdentity().url,
    '/desarrollo-web/': web().url,
    '/medios-de-comunicacion/': media().url,
    '/social-media/': social().url,
    '/tus-primeros-pasos/': guide().url,
    '/contacto/': contact().url,
    '/shop/': shop().url,
    '/product/distribucion-vevo/': vevo().url,
    '/product/sincronizacion/': sync().url,
    '/product/smart-url/': smart().url,
    '/privacy-policy/': privacy().url,
    '/aviso-legal/': notice().url,
    '/politica-de-cookies/': cookies().url,
};

export function publicHref(path: string): string {
    return localPages[path] ?? currentSite + path;
}

export const publicNavigation = [
    { label: 'Distribución musical', path: '/distribucion-digital/' },
    { label: 'Identidad visual', path: '/identidad-visual/' },
    { label: 'Social media', path: '/social-media/' },
    { label: 'Desarrollo web', path: '/desarrollo-web/' },
    { label: 'Primeros pasos', path: '/tus-primeros-pasos/' },
    { label: 'Contacto', path: '/contacto/' },
] as const;

export const publicServices = [
    {
        label: 'Primeros pasos',
        image: 'guide.webp',
        path: '/tus-primeros-pasos/',
    },
    {
        label: 'Distribución digital',
        image: 'distribution.webp',
        path: '/distribucion-digital/',
    },
    { label: 'Publishing', image: 'publishing.webp', path: '/publishing/' },
    {
        label: 'Identidad visual',
        image: 'visual-identity.webp',
        path: '/identidad-visual/',
    },
    { label: 'Desarrollo web', image: 'web.webp', path: '/desarrollo-web/' },
    {
        label: 'Medios de comunicación',
        image: 'media.webp',
        path: '/medios-de-comunicacion/',
    },
    { label: 'Social media', image: 'social.webp', path: '/social-media/' },
] as const;

export const publicLegalNavigation = [
    { label: 'Privacidad', path: '/privacy-policy/' },
    { label: 'Aviso legal', path: '/aviso-legal/' },
    { label: 'Cookies', path: '/politica-de-cookies/' },
] as const;
