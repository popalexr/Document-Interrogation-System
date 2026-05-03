export type UploadStatus =
    | 'uploading'
    | 'uploaded'
    | 'failed'
    | 'quarantine'
    | string;

export type UploadItem = {
    _id: string;
    original_name: string;
    mime_type: string;
    size: number;
    status: UploadStatus;
    r2_key: string;
    created_at?: string | Date;
    updated_at?: string | Date;
};

export type StatusFilter =
    | 'all'
    | 'indexed'
    | 'processing'
    | 'failed'
    | 'not_indexed';

export type SortKey = 'newest' | 'oldest' | 'name';
