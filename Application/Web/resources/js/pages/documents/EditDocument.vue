<script setup lang="ts">
import EditDocumentAssistantSidebar from '@/components/documents/edit/EditDocumentAssistantSidebar.vue';
import EditDocumentEditorPane from '@/components/documents/edit/EditDocumentEditorPane.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { csrfToken } from '@/lib/utils';
import { home as dashboard } from '@/routes/dashboard';
import {
    edit as editDocumentPage,
    edit_document as editDocumentStore,
    view as viewDocument,
} from '@/routes/documents';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
    reasoning?: string | null;
    edit_document_id?: string | null;
    at?: string | Date | null;
    loading?: boolean;
};

type ChatData = {
    id: string | null;
    title: string | null;
    messages: ChatMessage[];
};

const page = usePage();

const normalizeMessage = (raw: unknown): ChatMessage => {
    const message = (raw ?? {}) as Record<string, unknown>;

    return {
        role: message.role === 'assistant' ? 'assistant' : 'user',
        content: typeof message.content === 'string' ? message.content : '',
        reasoning:
            typeof message.reasoning === 'string' ? message.reasoning : null,
        edit_document_id:
            typeof message.edit_document_id === 'string'
                ? message.edit_document_id
                : null,
        at:
            typeof message.at === 'string' || message.at instanceof Date
                ? message.at
                : null,
        loading: Boolean(message.loading),
    };
};

const normalizeChatData = (raw: unknown): ChatData => {
    const chat = (raw ?? {}) as Record<string, unknown>;

    return {
        id: typeof chat.id === 'string' && chat.id.length > 0 ? chat.id : null,
        title: typeof chat.title === 'string' ? chat.title : null,
        messages: Array.isArray(chat.messages)
            ? chat.messages.map(normalizeMessage)
            : [],
    };
};

const latestEditedDocumentId = (messages: ChatMessage[]): string | null => {
    const lastEditedMessage = [...messages]
        .reverse()
        .find(
            (message) =>
                typeof message.edit_document_id === 'string' &&
                message.edit_document_id.length > 0,
        );

    return lastEditedMessage?.edit_document_id ?? null;
};

const extractEditPromptReasoning = (
    payload: Record<string, unknown>,
): string | null => {
    const message = payload.message;

    if (typeof message === 'string') {
        return message;
    }

    if (
        message &&
        typeof message === 'object' &&
        typeof (message as Record<string, unknown>).prompt === 'string'
    ) {
        return (message as Record<string, string>).prompt;
    }

    return null;
};

const extractFinalMessageContent = (
    payload: Record<string, unknown>,
): string | null => {
    if (typeof payload.content === 'string') {
        return payload.content;
    }

    if (typeof payload.message === 'string') {
        return payload.message;
    }

    return null;
};

const documentInfo = computed(
    () => (page.props.document ?? {}) as Record<string, unknown>,
);

const sourceDocumentId = computed(() => {
    const id = documentInfo.value._id ?? documentInfo.value.id;
    return typeof id === 'string' ? id : '';
});

const documentName = computed(() => {
    const originalName = documentInfo.value.original_name;
    return typeof originalName === 'string'
        ? originalName
        : 'untitled_document.md';
});

const documentMimeType = computed(() => {
    const mimeType = documentInfo.value.mime_type;
    return typeof mimeType === 'string' ? mimeType : undefined;
});

const initialChatData = computed<ChatData>(() =>
    normalizeChatData((page.props as Record<string, unknown>).chatData),
);

const messages = ref<ChatMessage[]>([]);
const prompt = ref('');
const sending = ref(false);
const chatId = ref<string | null>(null);
const previewDocumentId = ref('');

const isPreviewingEditedDocument = computed(
    () =>
        Boolean(previewDocumentId.value) &&
        previewDocumentId.value !== sourceDocumentId.value,
);

watch(
    initialChatData,
    (chatData) => {
        messages.value = chatData.messages;
        chatId.value = chatData.id;
        previewDocumentId.value =
            latestEditedDocumentId(chatData.messages) ?? sourceDocumentId.value;
    },
    { immediate: true },
);

watch(
    sourceDocumentId,
    (documentId) => {
        if (!previewDocumentId.value) {
            previewDocumentId.value = documentId;
        }
    },
    { immediate: true },
);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'My Documents',
        href: '#',
    },
    {
        title: documentName.value,
        href: sourceDocumentId.value
            ? viewDocument.url({ query: { id: sourceDocumentId.value } })
            : '#',
    },
    {
        title: 'Edit Document',
        href: '#',
    },
]);

const setPreviewDocument = (documentId: string) => {
    if (!documentId) {
        return;
    }

    previewDocumentId.value = documentId;
};

const resetPreviewDocument = () => {
    previewDocumentId.value = sourceDocumentId.value;
};

const openEditedDocument = (documentId: string) => {
    if (!documentId) {
        return;
    }

    router.visit(viewDocument.url({ query: { id: documentId } }));
};

async function sendMessage() {
    const text = prompt.value.trim();
    if (!text || sending.value || !sourceDocumentId.value) {
        return;
    }

    const userMessage: ChatMessage = {
        role: 'user',
        content: text,
        at: new Date(),
    };

    const assistantMessage: ChatMessage = {
        role: 'assistant',
        content: '',
        reasoning: null,
        edit_document_id: null,
        at: new Date(),
        loading: true,
    };

    messages.value.push(userMessage, assistantMessage);
    prompt.value = '';
    sending.value = true;

    try {
        const response = await fetch(editDocumentStore.url(), {
            method: 'POST',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                ...(csrfToken() ? { 'X-CSRF-TOKEN': csrfToken() ?? '' } : {}),
            },
            body: JSON.stringify({
                chat_id: chatId.value,
                document_id: sourceDocumentId.value,
                query: text,
            }),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            const data = await response
                .json()
                .catch(() => ({}) as Record<string, unknown>);
            throw new Error(
                typeof data.message === 'string'
                    ? data.message
                    : `Request failed (${response.status})`,
            );
        }

        const reader = response.body?.getReader();
        if (!reader) {
            throw new Error('No response body.');
        }

        const decoder = new TextDecoder();
        let buffer = '';

        const handleEvent = (rawEvent: string) => {
            const line = rawEvent.trim();
            if (!line.startsWith('data:')) {
                return;
            }

            const jsonPayload = line.replace(/^data:\s*/, '');

            let payload: Record<string, unknown> | null = null;

            try {
                payload = JSON.parse(jsonPayload) as Record<string, unknown>;
            } catch {
                return;
            }

            if (!payload) {
                return;
            }

            if (payload.type === 'edit_prompt') {
                assistantMessage.reasoning =
                    extractEditPromptReasoning(payload);
                assistantMessage.at = new Date();
                return;
            }

            if (payload.type === 'execution_result') {
                if (
                    payload.status === 'ok' &&
                    typeof payload.document_id === 'string'
                ) {
                    assistantMessage.edit_document_id = payload.document_id;
                    assistantMessage.content =
                        assistantMessage.content.trim() ||
                        'Edited document generated successfully. Preview is ready.';
                    assistantMessage.at = new Date();
                    setPreviewDocument(payload.document_id);
                    return;
                }

                assistantMessage.content =
                    typeof payload.message === 'string'
                        ? payload.message
                        : 'Error performing edit.';
                assistantMessage.at = new Date();
                assistantMessage.loading = false;
                return;
            }

            if (payload.type === 'final_message') {
                assistantMessage.content =
                    extractFinalMessageContent(payload) ??
                    assistantMessage.content;
                assistantMessage.at = new Date();
                return;
            }

            if (payload.type === 'done') {
                assistantMessage.loading = false;
                assistantMessage.at = new Date();

                if (
                    !assistantMessage.content.trim() &&
                    assistantMessage.edit_document_id
                ) {
                    assistantMessage.content =
                        'Edited document generated successfully. Preview is ready.';
                }

                if (payload.newChat && typeof payload.chatId === 'string') {
                    chatId.value = payload.chatId;

                    router.visit(editDocumentPage(), {
                        method: 'get',
                        data: {
                            id: sourceDocumentId.value,
                            chat_id: payload.chatId,
                        },
                        preserveState: true,
                        replace: true,
                    });
                }

                if (
                    typeof payload.editDocumentId === 'string' &&
                    payload.editDocumentId.length > 0
                ) {
                    assistantMessage.edit_document_id = payload.editDocumentId;
                    setPreviewDocument(payload.editDocumentId);
                }

                return;
            }

            if (payload.type === 'error') {
                assistantMessage.content =
                    typeof payload.message === 'string'
                        ? payload.message
                        : 'Error performing edit.';
                assistantMessage.at = new Date();
                assistantMessage.loading = false;
            }
        };

        const processBuffer = (flush = false) => {
            let working = buffer;
            const events = working.split(/\r?\n\r?\n/);
            working = events.pop() ?? '';

            for (const rawEvent of events) {
                handleEvent(rawEvent);
            }

            if (flush && working.trim()) {
                handleEvent(working);
                working = '';
            }

            buffer = working;
        };

        while (true) {
            const { value, done } = await reader.read();
            if (done) {
                break;
            }

            buffer += decoder.decode(value, { stream: true });
            processBuffer();
        }

        buffer += decoder.decode();
        processBuffer(true);

        if (
            !assistantMessage.content.trim() &&
            !assistantMessage.edit_document_id
        ) {
            assistantMessage.content = 'No edit result returned.';
        }

        assistantMessage.loading = false;
        assistantMessage.at = new Date();
    } catch (error: unknown) {
        assistantMessage.content =
            error instanceof Error ? error.message : 'Error performing edit.';
        assistantMessage.loading = false;
        assistantMessage.at = new Date();
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <Head title="Edit Document" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100vh-4rem)] flex-col bg-muted/20">
            <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
                <EditDocumentEditorPane
                    :file-id="previewDocumentId || sourceDocumentId"
                    :file-name="documentName"
                    :mime-type="documentMimeType"
                    :is-edited-file="isPreviewingEditedDocument"
                />
                <EditDocumentAssistantSidebar
                    :messages="messages"
                    :prompt="prompt"
                    :sending="sending"
                    :preview-document-id="previewDocumentId || sourceDocumentId"
                    :original-document-id="sourceDocumentId"
                    :document-name="documentName"
                    @update:prompt="prompt = $event"
                    @submit="sendMessage"
                    @preview="setPreviewDocument"
                    @reset-preview="resetPreviewDocument"
                    @open-document="openEditedDocument"
                />
            </div>
        </div>
    </AppLayout>
</template>
