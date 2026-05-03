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

export type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
    at: Date | string;
    loading?: boolean;
    typing?: boolean;
};
