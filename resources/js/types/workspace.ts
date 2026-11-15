export type Workspace = {
    id: string;
    name: string;
    locale: string;
    timezone: string;
    can_update: boolean;
    can_view_members: boolean;
    can_revoke_members: boolean;
    can_manage_roles: boolean;
    can_assign_roles: boolean;
    can_transfer_ownership: boolean;
};

export type Paginated<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
