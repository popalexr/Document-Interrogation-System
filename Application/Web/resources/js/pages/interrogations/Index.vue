<script setup lang="ts">
import DeleteAllChatsDialog from '@/components/documents/interrogate/DeleteAllChatsDialog.vue';
import DeleteChatDialog from '@/components/documents/interrogate/DeleteChatDialog.vue';
import InterrogationComposer from '@/components/documents/interrogate/InterrogationComposer.vue';
import InterrogationDocumentPicker from '@/components/documents/interrogate/InterrogationDocumentPicker.vue';
import InterrogationEmptyState from '@/components/documents/interrogate/InterrogationEmptyState.vue';
import InterrogationMessages from '@/components/documents/interrogate/InterrogationMessages.vue';
import InterrogationSidebar from '@/components/documents/interrogate/InterrogationSidebar.vue';
import type {
    ChatMessage,
    ChatsList,
    DocumentInfo,
} from '@/components/documents/interrogate/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { csrfToken } from '@/lib/utils';
import { home as dashboard } from '@/routes/dashboard';
import type { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const page = usePage();
const pageProps = page.props as any;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Interrogate Documents', href: '/interrogations' },
];

const documents = computed<DocumentInfo[]>(
    () => (pageProps.documents ?? []) as DocumentInfo[],
);
const chatsList = ref<ChatsList[]>((pageProps.chats ?? []) as ChatsList[]);
const selectedDocumentIds = ref<string[]>(
    (pageProps.selected_documents_ids ?? []) as string[],
);

const messages = ref<ChatMessage[]>([]);
const messagesPanel = ref<InstanceType<typeof InterrogationMessages> | null>(
    null,
);
const chatId = ref(
    (pageProps.chat_id ?? pageProps.interrogation_id ?? null) as string | null,
);
const input = ref('');
const sending = ref(false);

const documentPickerOpen = ref(false);
const deleteDialogOpen = ref(false);
const deleteAllChatsDialogOpen = ref(false);
const deletingChat = ref<ChatsList | null>(null);
const isDeleting = ref(false);
const isDeletingAllChats = ref(false);

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

const canSend = computed(
    () =>
        input.value.trim().length > 0 &&
        selectedDocuments.value.length > 0 &&
        !sending.value,
);

function scrollToBottom(): void {
    messagesPanel.value?.scrollToBottom();
}

watch(deleteDialogOpen, (open) => {
    if (!open) {
        deletingChat.value = null;
    }
});

function clearSelectedDocuments(): void {
    selectedDocumentIds.value = [];
}

function removeDocument(documentId: string): void {
    selectedDocumentIds.value = selectedDocumentIds.value.filter(
        (selectedId) => selectedId !== documentId,
    );
}

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

function generateTitle(prompt: string, nextChatId: string): void {
    if (!prompt || !sending.value) return;

    fetch('/api/generate_title', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken() || '',
        },
        body: JSON.stringify({ query: prompt, chat_id: nextChatId }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (!data.title) return;

            chatsList.value = chatsList.value.map((chat) =>
                chat.chat_id === nextChatId
                    ? { ...chat, title: data.title }
                    : chat,
            );
        })
        .catch((error) => {
            console.error('Error generating title:', error);
        });
}

function deleteChat(nextChatId: string): void {
    deletingChat.value =
        chatsList.value.find((chat) => chat.chat_id === nextChatId) ?? null;
    deleteDialogOpen.value = true;
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
                deleteDialogOpen.value = false;
            },
        },
    );
}

function deleteAllChats(): void {
    deleteAllChatsDialogOpen.value = true;
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
                deleteAllChatsDialogOpen.value = false;
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
    let queuedText = '';
    let typingTimer: ReturnType<typeof window.setTimeout> | null = null;
    let resolveTypingIdle: (() => void) | null = null;
    const typingSpeedMs = 18;

    input.value = '';
    sending.value = true;

    const stopTypingAnimation = () => {
        if (typingTimer !== null) {
            window.clearTimeout(typingTimer);
            typingTimer = null;
        }

        queuedText = '';
        assistantMessage.typing = false;
        resolveTypingIdle?.();
        resolveTypingIdle = null;
    };

    const markTypingIdle = () => {
        assistantMessage.typing = false;
        typingTimer = null;
        resolveTypingIdle?.();
        resolveTypingIdle = null;
    };

    const typeNextCharacter = () => {
        if (!queuedText.length) {
            markTypingIdle();
            return;
        }

        assistantMessage.content += queuedText.charAt(0);
        queuedText = queuedText.slice(1);
        assistantMessage.at = new Date();
        assistantMessage.typing = true;
        scrollToBottom();

        typingTimer = window.setTimeout(typeNextCharacter, typingSpeedMs);
    };

    const enqueueAnimatedText = (text: string) => {
        if (!text) return;

        queuedText += text;
        assistantMessage.typing = true;

        if (typingTimer === null) {
            typeNextCharacter();
        }
    };

    const waitForTypingIdle = () => {
        if (!typingTimer && !queuedText.length) {
            return Promise.resolve();
        }

        return new Promise<void>((resolve) => {
            resolveTypingIdle = resolve;
        });
    };

    const reconcileFinalAnswer = (answer: string) => {
        const visibleOrQueuedAnswer = assistantMessage.content + queuedText;

        if (!hasStarted) {
            assistantMessage.content = '';
            assistantMessage.loading = false;
            hasStarted = true;
            enqueueAnimatedText(answer);
            return;
        }

        if (answer.startsWith(visibleOrQueuedAnswer)) {
            enqueueAnimatedText(answer.slice(visibleOrQueuedAnswer.length));
            return;
        }

        if (visibleOrQueuedAnswer.trim() === answer.trim()) return;

        stopTypingAnimation();
        assistantMessage.content = answer;
    };

    await nextTick();
    scrollToBottom();

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
                    assistantMessage.loading = false;
                    hasStarted = true;
                }

                enqueueAnimatedText(payload.delta);
                assistantMessage.at = new Date();
                scrollToBottom();
            } else if (payload?.type === 'done') {
                if (typeof payload.answer === 'string') {
                    reconcileFinalAnswer(payload.answer);
                }

                assistantMessage.at = new Date();

                if (payload.chatId) {
                    chatId.value = payload.chatId;
                    window.history.replaceState(
                        {},
                        '',
                        `/interrogations?id=${payload.chatId}`,
                    );

                    if (payload.newChat) {
                        chatsList.value.unshift({
                            chat_id: payload.chatId,
                            title: 'Untitled chat',
                            document_count: selectedDocumentIds.value.length,
                            updated_at: new Date(),
                        });

                        generateTitle(question, payload.chatId);
                    }
                }

                scrollToBottom();
            } else if (payload?.type === 'error') {
                stopTypingAnimation();
                assistantMessage.content =
                    payload.message ?? 'Error performing interrogation.';
                assistantMessage.loading = false;
                assistantMessage.typing = false;
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
        await waitForTypingIdle();

        if (!assistantMessage.content.trim()) {
            assistantMessage.content = 'No answer returned.';
            assistantMessage.at = new Date();
        }
        assistantMessage.loading = false;
        assistantMessage.typing = false;
    } catch (error: any) {
        stopTypingAnimation();
        assistantMessage.content =
            error?.message ?? 'Error performing interrogation.';
        assistantMessage.loading = false;
        assistantMessage.typing = false;
        assistantMessage.at = new Date();
        scrollToBottom();
    } finally {
        assistantMessage.loading = false;
        assistantMessage.typing = false;
        sending.value = false;
    }
}

onMounted(() => {
    messages.value = ((pageProps.interrogations ?? []) as ChatMessage[]).map(
        (message) => ({
            ...message,
            at: message.at ? new Date(message.at) : new Date(),
        }),
    );

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
                <InterrogationEmptyState v-if="messages.length === 0" />
                <InterrogationMessages
                    v-else
                    ref="messagesPanel"
                    :messages="messages"
                />

                <InterrogationComposer
                    v-model="input"
                    :all-documents-selected="allDocumentsSelected"
                    :can-send="canSend"
                    :selected-documents="selectedDocuments"
                    @clear-documents="clearSelectedDocuments"
                    @open-document-picker="documentPickerOpen = true"
                    @remove-document="removeDocument"
                    @send="sendMessage"
                />
            </section>

            <InterrogationSidebar
                :active-chat-id="chatId"
                :chats="chatsList"
                @clear-chats="deleteAllChats"
                @delete-chat="deleteChat"
                @new-chat="openNewChat"
                @select-chat="openChat"
            />

            <InterrogationDocumentPicker
                v-model:open="documentPickerOpen"
                v-model:selected-document-ids="selectedDocumentIds"
                :documents="documents"
            />

            <DeleteChatDialog
                v-model:open="deleteDialogOpen"
                :chat="deletingChat"
                :is-deleting="isDeleting"
                @confirm="confirmDeleteChat"
            />

            <DeleteAllChatsDialog
                v-model:open="deleteAllChatsDialogOpen"
                description="Are you sure you want to delete all AI Interrogation chats? This action cannot be undone."
                :is-deleting="isDeletingAllChats"
                @confirm="confirmDeleteAllChats"
            />
        </main>
    </AppLayout>
</template>
