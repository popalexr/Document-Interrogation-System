<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/AppLayout.vue';
import { csrfToken } from '@/lib/utils';
import { home as dashboard } from '@/routes/dashboard';
import type { BreadcrumbItem } from '@/types';
import { Icon as DocumentIcon } from '@iconify/vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    FileText,
    MessageSquareText,
    MoreHorizontal,
    Plus,
    Search,
    SendHorizontal,
    Trash,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

type UploadStatus = 'uploading' | 'uploaded' | 'failed' | 'quarantine' | string;

type DocumentItem = {
    _id: string;
    original_name: string;
    mime_type: string;
    size: number;
    status: UploadStatus;
    created_at?: string | Date;
    updated_at?: string | Date;
};

type ChatItem = {
    chat_id: string;
    title: string;
    document_count: number;
    updated_at?: string | Date;
};

type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
    at: Date | string;
    loading?: boolean;
};

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Interrogate Documents', href: '/interrogations' },
];

const documents = computed<DocumentItem[]>(
    () => ((page.props as any).documents ?? []) as DocumentItem[],
);
const chatsList = ref<ChatItem[]>(
    ((page.props as any).chats ?? []) as ChatItem[],
);

const selectedDocumentIds = ref<string[]>(
    ((page.props as any).selected_documents_ids ?? []) as string[],
);
const documentPickerOpen = ref(false);
const documentSearch = ref('');
const input = ref('');
const messages = ref<ChatMessage[]>([]);
const chatContainer = ref<HTMLElement | null>(null);
const chatId = ref(
    ((page.props as any).chat_id ??
        (page.props as any).interrogation_id ??
        null) as string | null,
);
const sending = ref(false);
const deleteDialogOpen = ref(false);
const deleteAllChatsDialogOpen = ref(false);
const deletingChat = ref<ChatItem | null>(null);
const isDeleting = ref(false);
const isDeletingAllChats = ref(false);
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const lineHeightPx = ref(0);
const maxRows = 4;

const allDocumentsSelected = computed(
    () =>
        documents.value.length > 0 &&
        selectedDocumentIds.value.length === documents.value.length,
);

const selectedDocuments = computed(() =>
    documents.value.filter((document) =>
        selectedDocumentIds.value.includes(document._id),
    ),
);

const filteredDocuments = computed(() => {
    const query = documentSearch.value.trim().toLowerCase();

    return documents.value.filter((document) => {
        if (!query) return true;

        return (
            document.original_name.toLowerCase().includes(query) ||
            fileExt(document).toLowerCase().includes(query) ||
            document.mime_type.toLowerCase().includes(query)
        );
    });
});

const canSend = computed(
    () =>
        input.value.trim().length > 0 &&
        selectedDocuments.value.length > 0 &&
        !sending.value,
);

function fileExt(document: DocumentItem | null | undefined): string {
    const name = document?.original_name;
    if (!name) return '';

    const index = name.lastIndexOf('.');
    if (index === -1) return '';

    return name.slice(index + 1).toUpperCase();
}

function formatSize(bytes: number | undefined): string {
    if (!bytes && bytes !== 0) return '';

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function formatRelative(value: string | Date | undefined): string {
    if (!value) return '';

    const date = typeof value === 'string' ? new Date(value) : value;
    const diff = Date.now() - date.getTime();
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days === 1) return 'Yesterday';
    if (days < 7) return `${days}d ago`;

    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function formatMessageTime(value: string | Date): string {
    const date = typeof value === 'string' ? new Date(value) : value;

    return date.toLocaleTimeString();
}

function documentIcon(document: DocumentItem): string {
    switch (fileExt(document)) {
        case 'DOC':
        case 'DOCX':
            return 'vscode-icons:file-type-word';
        case 'PDF':
            return 'vscode-icons:file-type-pdf2';
        case 'PPT':
        case 'PPTX':
            return 'vscode-icons:file-type-powerpoint';
        case 'XLS':
        case 'XLSX':
        case 'CSV':
            return 'vscode-icons:file-type-excel';
        case 'MD':
        case 'TXT':
        case 'TEX':
            return 'vscode-icons:file-type-text';
        case 'JSON':
            return 'vscode-icons:file-type-json';
        default:
            return 'vscode-icons:default-file';
    }
}

function isDocumentSelected(documentId: string): boolean {
    return selectedDocumentIds.value.includes(documentId);
}

function toggleDocument(documentId: string): void {
    if (isDocumentSelected(documentId)) {
        selectedDocumentIds.value = selectedDocumentIds.value.filter(
            (selectedId) => selectedId !== documentId,
        );
        return;
    }

    selectedDocumentIds.value = [...selectedDocumentIds.value, documentId];
}

function toggleAllDocuments(): void {
    selectedDocumentIds.value = allDocumentsSelected.value
        ? []
        : documents.value.map((document) => document._id);
}

function clearSelectedDocuments(): void {
    selectedDocumentIds.value = [];
}

function removeDocument(documentId: string): void {
    selectedDocumentIds.value = selectedDocumentIds.value.filter(
        (selectedId) => selectedId !== documentId,
    );
}

function autoGrow(): void {
    const el = textareaRef.value;
    if (!el) return;

    el.style.height = 'auto';

    const maxHeight = lineHeightPx.value * maxRows;
    const nextHeight = Math.min(el.scrollHeight, maxHeight);

    el.style.height = `${nextHeight}px`;
    el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
}

function initializeTextareaSizing(): void {
    const el = textareaRef.value;
    if (!el) return;

    const style = window.getComputedStyle(el);
    lineHeightPx.value = parseFloat(style.lineHeight) || 20;
    autoGrow();
}

function scrollToBottom(): void {
    const el = chatContainer.value;
    if (!el) return;

    requestAnimationFrame(() => {
        el.scrollTop = el.scrollHeight;
    });
}

watch(
    () => messages.value.length,
    async () => {
        await nextTick();
        scrollToBottom();
    },
);

function openChat(nextChatId: string): void {
    router.visit('/interrogations', {
        method: 'get',
        data: { id: nextChatId },
        preserveState: false,
        replace: true,
    });
}

function openNewChat(): void {
    router.visit('/interrogations', {
        method: 'get',
        preserveState: false,
        replace: true,
    });
}

function deleteChat(nextChatId: string): void {
    deletingChat.value =
        chatsList.value.find((chat) => chat.chat_id === nextChatId) ?? null;
    deleteDialogOpen.value = true;
}

function handleDeleteDialogOpen(open: boolean): void {
    deleteDialogOpen.value = open;

    if (!open) {
        deletingChat.value = null;
    }
}

function confirmDeleteChat(): void {
    if (!deletingChat.value) return;

    isDeleting.value = true;
    const deletingChatId = deletingChat.value.chat_id;

    router.post(
        '/interrogations/delete',
        { chat_id: deletingChatId },
        {
            preserveScroll: true,
            onSuccess: () => {
                chatsList.value = chatsList.value.filter(
                    (chat) => chat.chat_id !== deletingChatId,
                );

                if (chatId.value === deletingChatId) {
                    openNewChat();
                }
            },
            onFinish: () => {
                isDeleting.value = false;
                handleDeleteDialogOpen(false);
            },
        },
    );
}

function deleteAllChats(): void {
    deleteAllChatsDialogOpen.value = true;
}

function handleDeleteAllChatsDialogOpen(open: boolean): void {
    deleteAllChatsDialogOpen.value = open;
}

function confirmDeleteAllChats(): void {
    if (chatsList.value.length === 0) return;

    isDeletingAllChats.value = true;

    router.post(
        '/interrogations/deleteAll',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                chatsList.value = [];
                openNewChat();
            },
            onFinish: () => {
                isDeletingAllChats.value = false;
                handleDeleteAllChatsDialogOpen(false);
            },
        },
    );
}

async function sendMessage(): Promise<void> {
    const question = input.value.trim();
    if (!question || !canSend.value) return;

    messages.value.push({
        role: 'user',
        content: question,
        at: new Date(),
    });

    messages.value.push({
        role: 'assistant',
        content: 'Thinking...',
        at: new Date(),
        loading: true,
    });

    const assistantMessage = messages.value[messages.value.length - 1];
    let hasStarted = false;

    input.value = '';
    sending.value = true;

    nextTick(() => {
        autoGrow();
        scrollToBottom();
    });

    try {
        const response = await fetch('/interrogations', {
            method: 'POST',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                ...(csrfToken() ? { 'X-CSRF-TOKEN': csrfToken() ?? '' } : {}),
            },
            body: JSON.stringify({
                chat_id: chatId.value,
                documents_ids: selectedDocumentIds.value,
                query: question,
            }),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(
                (data as any)?.message || `Request failed (${response.status})`,
            );
        }

        const reader = response.body?.getReader();
        if (!reader) {
            throw new Error('No response body.');
        }

        const decoder = new TextDecoder();
        let buffer = '';

        const handleEvent = (raw: string) => {
            const line = raw.trim();
            if (!line.startsWith('data:')) return;

            const jsonPayload = line.replace(/^data:\s*/, '');
            let payload: any = null;

            try {
                payload = JSON.parse(jsonPayload);
            } catch {
                return;
            }

            if (
                payload?.type === 'chunk' &&
                typeof payload.delta === 'string'
            ) {
                if (!hasStarted) {
                    assistantMessage.content = '';
                    hasStarted = true;
                }

                assistantMessage.content += payload.delta;
                assistantMessage.at = new Date();
                scrollToBottom();
            } else if (payload?.type === 'done') {
                if (typeof payload.answer === 'string') {
                    assistantMessage.content = payload.answer;
                }

                assistantMessage.loading = false;
                assistantMessage.at = new Date();

                if (payload.chatId) {
                    chatId.value = payload.chatId;
                    window.history.replaceState(
                        {},
                        '',
                        `/interrogations?id=${payload.chatId}`,
                    );
                }

                scrollToBottom();
            } else if (payload?.type === 'error') {
                assistantMessage.content =
                    payload.message ?? 'Error performing interrogation.';
                assistantMessage.loading = false;
                assistantMessage.at = new Date();
                scrollToBottom();
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
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            processBuffer();
        }

        buffer += decoder.decode();
        processBuffer(true);

        if (!assistantMessage.content.trim()) {
            assistantMessage.content = 'No answer returned.';
            assistantMessage.at = new Date();
        }
    } catch (error: any) {
        assistantMessage.content =
            error?.message ?? 'Error performing interrogation.';
        assistantMessage.loading = false;
        assistantMessage.at = new Date();
        scrollToBottom();
    } finally {
        assistantMessage.loading = false;
        sending.value = false;
    }
}

onMounted(() => {
    messages.value = (
        ((page.props as any).interrogations ?? []) as ChatMessage[]
    ).map((message) => ({
        ...message,
        at: message.at ? new Date(message.at) : new Date(),
    }));

    initializeTextareaSizing();
    nextTick(scrollToBottom);
});
</script>

<template>
    <Head title="Interrogate Documents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main
            class="layout-content-height flex min-h-0 overflow-hidden bg-background"
        >
            <section
                class="flex h-full min-h-0 min-w-0 flex-1 flex-col overflow-hidden border-r border-border px-8 pt-3 pb-6"
            >
                <div
                    ref="chatContainer"
                    class="h-0 min-h-0 flex-1 overflow-y-auto overscroll-contain px-4"
                >
                    <div
                        v-if="messages.length === 0"
                        class="flex h-full items-center justify-center"
                    >
                        <div
                            class="w-full max-w-4xl rounded-lg border border-dashed bg-muted/20 p-10 text-center"
                        >
                            <MessageSquareText
                                class="mx-auto mb-4 size-10 text-muted-foreground"
                            />
                            <h1 class="text-2xl font-semibold">
                                Interrogate documents
                            </h1>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Select one or more documents, then ask a
                                question across the chosen context.
                            </p>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mx-auto flex max-w-4xl flex-col gap-4 py-6"
                    >
                        <div
                            v-for="(message, index) in messages"
                            :key="index"
                            class="max-w-[78%] rounded-lg px-4 py-3 text-sm shadow-xs"
                            :class="
                                message.role === 'user'
                                    ? 'self-end bg-primary text-primary-foreground'
                                    : 'self-start bg-muted text-foreground'
                            "
                        >
                            <p class="leading-relaxed whitespace-pre-wrap">
                                {{ message.content }}
                            </p>
                            <div class="mt-2 text-[11px] opacity-70">
                                {{ formatMessageTime(message.at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mx-auto w-full max-w-5xl shrink-0">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <Button
                            v-if="selectedDocuments.length === 0"
                            variant="outline"
                            class="h-10 gap-2 rounded-md bg-background"
                            @click="documentPickerOpen = true"
                        >
                            <FileText class="size-4" />
                            Add documents to interrogate
                            <ChevronDown class="size-4" />
                        </Button>

                        <Badge
                            v-if="allDocumentsSelected"
                            variant="secondary"
                            class="h-8 max-w-56 gap-1 rounded-md px-2"
                        >
                            <span class="truncate">
                                All documents included
                            </span>
                            <button
                                type="button"
                                class="inline-flex size-5 items-center justify-center rounded hover:bg-background"
                                aria-label="Clear selected documents"
                                @click="clearSelectedDocuments"
                            >
                                <X class="size-3" />
                            </button>
                        </Badge>

                        <Badge
                            v-else
                            v-for="document in selectedDocuments"
                            :key="document._id"
                            variant="secondary"
                            class="h-8 max-w-56 gap-1 rounded-md px-2"
                        >
                            <span class="truncate">
                                {{ document.original_name }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex size-5 items-center justify-center rounded hover:bg-background"
                                :aria-label="`Remove ${document.original_name}`"
                                @click="removeDocument(document._id)"
                            >
                                <X class="size-3" />
                            </button>
                        </Badge>
                    </div>

                    <div
                        class="rounded-lg border border-slate-300 bg-background"
                    >
                        <div class="flex items-center gap-3 px-4 pt-3 pb-2">
                            <button
                                type="button"
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border hover:bg-muted"
                                aria-label="Add documents"
                                @click="documentPickerOpen = true"
                            >
                                <Plus class="size-4" />
                            </button>
                            <textarea
                                ref="textareaRef"
                                v-model="input"
                                rows="1"
                                maxlength="500"
                                class="max-h-32 min-h-5 flex-1 resize-none bg-transparent text-sm leading-5 outline-none placeholder:text-muted-foreground"
                                placeholder="Ask a question across the selected documents..."
                                @input="autoGrow"
                                @keydown.enter.exact.prevent="sendMessage"
                            ></textarea>
                            <button
                                type="button"
                                class="inline-flex size-9 shrink-0 items-center justify-center rounded-md text-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!canSend"
                                aria-label="Send message"
                                @click="sendMessage"
                            >
                                <SendHorizontal class="size-5" />
                            </button>
                        </div>

                        <div
                            class="flex items-center justify-end px-4 pb-3 text-xs text-muted-foreground"
                        >
                            <span>{{ input.length }}/500</span>
                        </div>
                    </div>

                    <p class="mt-4 text-center text-xs text-muted-foreground">
                        This application can make mistakes. Please verify any
                        information it provides.
                    </p>
                </div>
            </section>

            <aside class="hidden w-80 shrink-0 flex-col px-5 py-6 lg:flex">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Your chats</h2>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-md hover:bg-muted"
                                aria-label="Chat options"
                            >
                                <MoreHorizontal class="size-5" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                class="cursor-pointer"
                                @click="openNewChat"
                            >
                                New chat
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="cursor-pointer text-destructive"
                                :disabled="chatsList.length === 0"
                                @click="deleteAllChats"
                            >
                                Clear all chats
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="mt-5 flex flex-col gap-3">
                    <div
                        v-for="chat in chatsList"
                        :key="chat.chat_id"
                        class="rounded-lg border bg-background p-4 shadow-xs transition hover:bg-muted/40"
                        :class="{
                            'border-primary/40 bg-muted/50':
                                chat.chat_id === chatId,
                        }"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <button
                                type="button"
                                class="min-w-0 flex-1 text-left"
                                @click="openChat(chat.chat_id)"
                            >
                                <h3 class="truncate text-sm font-medium">
                                    {{ chat.title }}
                                </h3>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    {{ chat.document_count }}
                                    {{
                                        chat.document_count === 1
                                            ? 'document'
                                            : 'documents'
                                    }}
                                    - {{ formatRelative(chat.updated_at) }}
                                </p>
                            </button>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <button
                                        type="button"
                                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-md hover:bg-muted"
                                        aria-label="Chat actions"
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        class="cursor-pointer text-destructive"
                                        @click="deleteChat(chat.chat_id)"
                                    >
                                        <Trash class="mr-2 size-4" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <div
                        v-if="chatsList.length === 0"
                        class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
                    >
                        No chats yet.
                    </div>
                </div>
            </aside>

            <Dialog v-model:open="documentPickerOpen">
                <DialogContent class="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Add documents for context</DialogTitle>
                        <DialogDescription>
                            Choose the documents the interrogation should use as
                            context.
                        </DialogDescription>
                    </DialogHeader>

                    <label
                        class="flex h-10 items-center gap-2 rounded-md border bg-background px-3 text-sm"
                    >
                        <Search class="size-4 text-muted-foreground" />
                        <input
                            v-model="documentSearch"
                            type="search"
                            class="min-w-0 flex-1 bg-transparent outline-none placeholder:text-muted-foreground"
                            placeholder="Search documents..."
                        />
                    </label>

                    <div class="max-h-96 overflow-y-auto rounded-lg border">
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 border-b bg-muted/30 px-4 py-3 text-left hover:bg-muted/60"
                            @click="toggleAllDocuments"
                        >
                            <input
                                type="checkbox"
                                class="size-4 rounded border-input"
                                :checked="allDocumentsSelected"
                                @click.stop="toggleAllDocuments"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">
                                    Select all documents
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Include every available document as context.
                                </p>
                            </div>
                        </button>

                        <button
                            v-for="document in filteredDocuments"
                            :key="document._id"
                            type="button"
                            class="flex w-full items-center gap-3 border-b px-4 py-3 text-left last:border-b-0 hover:bg-muted/50"
                            @click="toggleDocument(document._id)"
                        >
                            <input
                                type="checkbox"
                                class="size-4 rounded border-input"
                                :checked="isDocumentSelected(document._id)"
                                @click.stop="toggleDocument(document._id)"
                            />
                            <DocumentIcon
                                :icon="documentIcon(document)"
                                class="size-8 shrink-0"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ document.original_name }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ fileExt(document) || 'FILE' }} -
                                    {{ formatSize(document.size) }} -
                                    {{ document.status }}
                                </p>
                            </div>
                        </button>

                        <div
                            v-if="filteredDocuments.length === 0"
                            class="p-8 text-center text-sm text-muted-foreground"
                        >
                            No documents found.
                        </div>
                    </div>

                    <DialogFooter class="items-center gap-3">
                        <p class="mr-auto text-sm text-muted-foreground">
                            {{ selectedDocuments.length }} selected
                        </p>
                        <Button @click="documentPickerOpen = false">
                            Done
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="deleteDialogOpen"
                @update:open="handleDeleteDialogOpen"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader class="space-y-2">
                        <DialogTitle>Delete chat</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete
                            <span class="font-medium text-foreground">
                                {{ deletingChat?.title ?? 'this chat' }}
                            </span>
                            ? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                variant="secondary"
                                @click="handleDeleteDialogOpen(false)"
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            :disabled="!deletingChat || isDeleting"
                            @click="confirmDeleteChat"
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="deleteAllChatsDialogOpen"
                @update:open="handleDeleteAllChatsDialogOpen"
            >
                <DialogContent class="sm:max-w-md">
                    <DialogHeader class="space-y-2">
                        <DialogTitle>Delete all chats</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete all AI Interrogation
                            chats? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                variant="secondary"
                                @click="handleDeleteAllChatsDialogOpen(false)"
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            :disabled="isDeletingAllChats"
                            @click="confirmDeleteAllChats"
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </main>
    </AppLayout>
</template>
