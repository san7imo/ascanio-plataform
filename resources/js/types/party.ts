export type PartyIdentifier = {
    scheme: 'ipi' | 'isni' | 'other';
    value: string;
    issuer: string | null;
};

export type PartyActivity = { code: string; name: string };

export type PartyPrivate = {
    legal_name: string | null;
    email: string | null;
    phone: string | null;
    notes: string | null;
    source_note: string | null;
    identifiers: PartyIdentifier[];
    activities: PartyActivity[];
};

export type PartySummary = {
    id: string;
    kind: 'person' | 'organization';
    display_name: string;
    version: number;
    source: string;
    verification_status: string;
    updated_at: string;
};

export type PartyDetail = PartySummary & {
    can_view_private: boolean;
    can_update: boolean;
    private?: PartyPrivate;
};

export type PartyRevision = {
    id: string;
    version: number;
    recorded_at: string;
    snapshot: PartyPrivate & {
        display_name: string;
        kind: 'person' | 'organization';
    };
};
