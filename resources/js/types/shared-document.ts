export type SharedDocument = {
    id: string;
    workspace: string;
    timezone: string;
    number: number;
    filename: string;
    mime_type: string;
    size_bytes: number;
    sha256: string;
    scan_status: string;
    created_at: string;
    expires_at: string;
    projection: string;
    can_download: boolean;
};
