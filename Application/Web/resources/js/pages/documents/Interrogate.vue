<script setup lang="ts">
import DeleteAllChatsDialog from '@/components/documents/interrogate/DeleteAllChatsDialog.vue';
import DeleteChatDialog from '@/components/documents/interrogate/DeleteChatDialog.vue';
import InterrogateComposer from '@/components/documents/interrogate/InterrogateComposer.vue';
import InterrogateEmptyState from '@/components/documents/interrogate/InterrogateEmptyState.vue';
import InterrogateMessages from '@/components/documents/interrogate/InterrogateMessages.vue';
import InterrogateSidebar from '@/components/documents/interrogate/InterrogateSidebar.vue';
import type {
    ChatMessage,
    ChatsList,
    DocumentInfo,
} from '@/components/documents/interrogate/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { csrfToken } from '@/lib/utils';
import {
    deleteAll as deleteAllChatsRoute,
    deleteMethod as deleteChatRoute,
} from '@/routes/chats';
import { home as dashboard } from '@/routes/dashboard';
import { interrogate, view as viewDocument } from '@/routes/documents';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const page = usePage();
const pageProps = page.props as any;

let breadcrumbs = ref<BreadcrumbItem[]>([
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: pageProps.document.original_name,
        href: viewDocument.url({ query: { id: pageProps.document._id } }),
    },
    {
        title: 'Interrogate Document',
        href: interrogate.url({ query: { id: pageProps.document._id } }),
    },
]);

const chatsList = ref<ChatsList[]>(pageProps.chats || []);

let chatId = ref(page.props.chat_id as string | null);

const documentInfo = computed<DocumentInfo | null>(
    () => pageProps.document ?? null,
);

const messages = ref<ChatMessage[]>([]);
const input = ref('');
const sending = ref(false);
const messagesPanel = ref<InstanceType<typeof InterrogateMessages> | null>(
    null,
);

const deleteDialogOpen = ref(false);
const deletingChat = ref<ChatsList | null>(null);
const isDeleting = ref(false);
const deleteAllChatsDialogOpen = ref(false);
const isDeletingAllChats = ref(false);

const scrollToBottom = () => {
    messagesPanel.value?.scrollToBottom();
};

watch(
    () => chatId.value,
    (newChatId) => {
        if (newChatId) {
            updateBreadcrumbsWithChatTitle(newChatId);
        }
    },
);

watch(deleteDialogOpen, (open) => {
    if (!open) {
        deletingChat.value = null;
    }
});

const openNewChat = () => {
    router.visit(interrogate(), {
        method: 'get',
        data: { id: documentInfo.value?._id },
        preserveState: false,
        replace: true,
    });
};

const generateTitle = (prompt: string, chatId: string) => {
    if (!prompt || !sending.value) return;

    fetch(`/api/generate_title`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken() || '',
        },
        body: JSON.stringify({ query: prompt, chat_id: chatId }),
    })
        .then((res) => res.json())
        .then((data) => {
            if (data.title) {
                chatsList.value = chatsList.value.map((chat) => {
                    if (chat.chat_id === chatId) {
                        return { ...chat, title: data.title };
                    }
                    return chat;
                });
            }
        })
        .catch((err) => {
            console.error('Error generating title:', err);
        });
};

async function sendMessage() {
    const text = input.value.trim();
    if (!text || sending.value) return;
    const userMessage: ChatMessage = {
        role: 'user',
        content: text,
        at: new Date(),
    };
    messages.value.push(userMessage);
    // First assistant reply shows thinking text and owns the spinner until chunks arrive.
    messages.value.push({
        role: 'assistant',
        content: 'Thinking...',
        at: new Date(),
        loading: true,
    });
    // Use the reactive entry from the array so stream mutations repaint the UI.
    let assistantMessage: ChatMessage | null =
        messages.value[messages.value.length - 1] ?? null;
    let hasStarted = false;
    let queuedText = '';
    let typingTimer: ReturnType<typeof window.setTimeout> | null = null;
    let resolveTypingIdle: (() => void) | null = null;
    const typingSpeedMs = 6;
    input.value = '';
    sending.value = true;
    await nextTick();
    scrollToBottom();

    const stopTypingAnimation = () => {
        if (typingTimer !== null) {
            window.clearTimeout(typingTimer);
            typingTimer = null;
        }

        queuedText = '';

        if (assistantMessage) {
            assistantMessage.typing = false;
        }

        resolveTypingIdle?.();
        resolveTypingIdle = null;
    };

    const markTypingIdle = () => {
        if (!assistantMessage) return;

        assistantMessage.typing = false;
        typingTimer = null;
        resolveTypingIdle?.();
        resolveTypingIdle = null;
    };

    const typeNextCharacter = () => {
        if (!assistantMessage) {
            markTypingIdle();
            return;
        }

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
        if (!assistantMessage || !text) return;

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
        if (!assistantMessage) return;

        const visibleOrQueuedAnswer = assistantMessage.content + queuedText;

        if (!hasStarted) {
            assistantMessage.content = '';
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

    try {
        if (!assistantMessage) {
            throw new Error('Unable to create assistant message entry.');
        }
        const tokenEl = document.head.querySelector(
            'meta[name="csrf-token"]',
        ) as HTMLMetaElement | null;
        const token = tokenEl?.content ?? '';
        const did = documentInfo.value?._id;
        const res = await fetch(`/documents/interrogate`, {
            method: 'POST',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify({
                chat_id: chatId.value,
                document_id: did,
                query: text,
            }),
            credentials: 'same-origin',
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}) as any);
            throw new Error(data?.message || `Request failed (${res.status})`);
        }
        const reader = res.body?.getReader();
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
            } catch (err) {
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

                if (payload.newChat && payload.chatId) {
                    router.visit(interrogate(), {
                        method: 'get',
                        data: {
                            id: documentInfo.value?._id,
                            chat_id: payload.chatId,
                        },
                        preserveState: true,
                        replace: true,
                    });

                    chatId.value = payload.chatId;

                    chatsList.value.unshift({
                        chat_id: payload.chatId,
                        title: null,
                    });

                    generateTitle(text, payload.chatId);
                }
                scrollToBottom();
            } else if (payload?.type === 'error') {
                stopTypingAnimation();
                assistantMessage.content =
                    payload.message ?? 'Error performing interrogation.';
                assistantMessage.loading = false;
                assistantMessage.typing = false;
                assistantMessage.at = new Date();
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
    } catch (e: any) {
        stopTypingAnimation();
        if (assistantMessage) {
            assistantMessage.content =
                e?.message ?? 'Error performing interrogation.';
            assistantMessage.loading = false;
            assistantMessage.typing = false;
            assistantMessage.at = new Date();
        }
        scrollToBottom();
    } finally {
        if (assistantMessage) {
            assistantMessage.loading = false;
            assistantMessage.typing = false;
        }
        sending.value = false;
    }
}

const insertSuggestion = (suggestion: string) => {
    input.value = suggestion;
    nextTick(() => {
        sendMessage();
    });
};

const updateBreadcrumbsWithChatTitle = (chatId: string | null) => {
    const chat = chatsList.value.find((c) => c.chat_id === chatId);
    if (chat && chat.title) {
        breadcrumbs.value = [
            ...breadcrumbs.value.slice(0, 3),
            {
                title: chat.title,
                href: '#',
            },
        ];
    } else {
        breadcrumbs.value = [...breadcrumbs.value.slice(0, 3)];
    }
};

const selectChat = (selectedChatId: string) => {
    router.visit(interrogate(), {
        method: 'get',
        data: {
            id: documentInfo.value?._id,
            chat_id: selectedChatId,
        },
        preserveState: false,
        replace: true,
    });
};

const deleteChat = (chatId: string) => {
    deletingChat.value =
        chatsList.value.find((c) => c.chat_id === chatId) ?? null;
    deleteDialogOpen.value = true;
};

const confirmDelete = async () => {
    if (!deletingChat.value) return;

    isDeleting.value = true;

    router.post(
        deleteChatRoute.url({
            query: { chat_id: deletingChat.value.chat_id },
        }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                chatsList.value = chatsList.value.filter(
                    (c) => c.chat_id !== deletingChat.value?.chat_id,
                );
                if (chatId.value === deletingChat.value?.chat_id) {
                    openNewChat();
                }
            },
            onFinish: () => {
                isDeleting.value = false;
                deleteDialogOpen.value = false;
            },
        },
    );
};

const deleteAllChats = () => {
    deleteAllChatsDialogOpen.value = true;
};

const handleDeleteAllChatsDialogOpen = (open: boolean) => {
    deleteAllChatsDialogOpen.value = open;
};

const confirmDeleteAllChats = async () => {
    if (chatsList.value.length === 0) return;

    isDeletingAllChats.value = true;

    router.post(
        deleteAllChatsRoute.url(),
        {
            document_id: documentInfo.value?._id,
        },
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
};

onMounted(() => {
    messages.value = pageProps.interrogations || [];
    nextTick(scrollToBottom);

    updateBreadcrumbsWithChatTitle(chatId.value);
});
</script>

<template>
    <Head title="Interrogate Document" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="layout-content-height flex w-full bg-background text-foreground"
        >
            <div
                class="w-[78.571429%] border-r border-border px-2 lg:w-6/7 lg:px-10"
            >
                <div class="flex h-full w-full flex-col">
                    <div class="h-4/5 w-full">
                        <InterrogateEmptyState
                            v-if="messages.length === 0"
                            @select-suggestion="insertSuggestion"
                        />
                        <InterrogateMessages
                            v-else
                            ref="messagesPanel"
                            :messages="messages"
                        />
                    </div>
                    <div class="h-1/5">
                        <InterrogateComposer
                            v-model="input"
                            :sending="sending"
                            @send="sendMessage"
                        />
                    </div>
                </div>
            </div>
            <InterrogateSidebar
                :chats="chatsList"
                @new-chat="openNewChat"
                @clear-chats="deleteAllChats"
                @select-chat="selectChat"
                @delete-chat="deleteChat"
            />
        </div>

        <DeleteChatDialog
            v-model:open="deleteDialogOpen"
            :chat="deletingChat"
            :is-deleting="isDeleting"
            @confirm="confirmDelete"
        />

        <DeleteAllChatsDialog
            v-model:open="deleteAllChatsDialogOpen"
            :is-deleting="isDeletingAllChats"
            @confirm="confirmDeleteAllChats"
        />
    </AppLayout>
</template>
