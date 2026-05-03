export type DeletedDocument = {
    _id: string;
    original_name: string;
    mime_type: string;
    size: number;
    status?: string;
    r2_key?: string;
    created_at?: string | Date;
    updated_at?: string | Date;
    deleted_at?: string | Date;
};

export type TrashSortKey = 'newest_deleted' | 'oldest_deleted' | 'name';
