export type DocumentInfo = {
    _id: string;
    original_name: string;
    mime_type: string;
    size: number;
    status: string;
    r2_key?: string;
    created_at?: string | Date;
    updated_at?: string | Date;
};

export type ChatsList = {
    chat_id: string;
    title: string | null;
    document_count?: number;
    updated_at?: string | Date;
};

export type CitationDocument = {
    document_id: string;
    original_name: string;
    file_id?: string;
};

export type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
    at: Date | string;
    citations?: CitationDocument[];
    loading?: boolean;
    typing?: boolean;
};
