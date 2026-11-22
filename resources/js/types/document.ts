export type DocumentSummary = {
    id: string;
    title: string;
    version_count: number;
};
export type DocumentDetail = DocumentSummary & { description: string | null };
export type DocumentVersion = {
    id: string;
    number: number;
    filename: string;
    mime_type: string;
    size_bytes: number;
    sha256: string;
    scan_status:
        'quarantined' | 'clean' | 'infected' | 'scan_failed' | 'blocked';
    created_at: string;
    can_download: boolean;
    can_retry: boolean;
};
