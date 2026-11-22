export interface PublicImage {
    src: string;
    width: number;
    height: number;
    alt?: string;
}

export interface PublicHeroContent {
    title: string;
    subtitle: string;
    hero: PublicImage;
}

export interface PublicGalleryContent {
    title: string;
    kind: 'logos' | 'press' | 'packages' | 'covers';
    images: PublicImage[];
}

export interface PublicServiceContent extends PublicHeroContent {
    key: string;
    paragraphs: string[];
    tiles?: (PublicImage & { path: string | null })[];
    platforms?: PublicImage;
    galleries?: PublicGalleryContent[];
    images?: PublicImage[];
    partner?: PublicImage;
    plans?: {
        title: string;
        description: string;
        price: string;
        period: string;
        features: string[];
        href: string;
        footer: string;
    }[];
}

export interface PublicGuideContent extends PublicHeroContent {
    paragraphs: string[];
    sections: { id: string; title: string; html: string }[];
}

export interface PublicPreviewField {
    key: string;
    label: string;
    type: 'text' | 'email' | 'date' | 'textarea' | 'select';
    placeholder: string;
    options: string[];
    description: string;
}

export interface PublicProductContent {
    key: string;
    title: string;
    path: string;
    purchaseUrl: string;
    price: string | null;
    category: string;
    image: PublicImage;
    fields: PublicPreviewField[];
}

export interface PublicContactContent extends PublicHeroContent {
    heading: string;
    email: string;
    phone: string;
    address: string;
    formUrl: string;
    fields: PublicPreviewField[];
}

export interface PublicLegalContent {
    key: string;
    title: string;
    html: string;
    sourceUrl: string;
    reviewRequired: boolean;
}
