<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Dialog from '@/components/ui/dialog/Dialog.vue';
import DialogClose from '@/components/ui/dialog/DialogClose.vue';
import DialogContent from '@/components/ui/dialog/DialogContent.vue';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';
import DialogFooter from '@/components/ui/dialog/DialogFooter.vue';
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue';
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue';
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue';
import DropdownMenuContent from '@/components/ui/dropdown-menu/DropdownMenuContent.vue';
import DropdownMenuItem from '@/components/ui/dropdown-menu/DropdownMenuItem.vue';
import DropdownMenuTrigger from '@/components/ui/dropdown-menu/DropdownMenuTrigger.vue';
import Spinner from '@/components/ui/spinner/Spinner.vue';
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
import {
    ClipboardCheck,
    Ellipsis,
    FileText,
    SendHorizontal,
    Trash,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const page = usePage();

let breadcrumbs = ref<BreadcrumbItem[]>([
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: page.props.document.original_name,
        href: viewDocument.url({ query: { id: page.props.document._id } }),
    },
    {
        title: 'Interrogate Document',
        href: interrogate.url({ query: { id: page.props.document._id } }),
    },
]);

type DocumentInfo = {
    _id: string;
    original_name: string;
    mime_type: string;
    size: number;
    status: string;
    r2_key: string;
    created_at?: string | Date;
};

type ChatsList = {
    chat_id: string;
    title: string | null;
};
const chatsList = ref<ChatsList[]>(page.props.chats || []);

const textareaRef = ref<HTMLTextAreaElement | null>(null);
const lineHeightPx = ref(0);

let chatId = ref(page.props.chat_id as string | null);

const maxRows = 4;

const documentInfo = computed<DocumentInfo | null>(
    () => (page.props as any).document ?? null,
);

type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
    at: Date | string;
    loading?: boolean;
    typing?: boolean;
};
const messages = ref<ChatMessage[]>([]);
const input = ref('');
const sending = ref(false);
const chatContainer = ref<HTMLElement | null>(null);

const deleteDialogOpen = ref(false);
const deletingChat = ref<ChatsList | null>(null);
const isDeleting = ref(false);
const deleteAllChatsDialogOpen = ref(false);
const isDeletingAllChats = ref(false);

const scrollToBottom = () => {
    const el = chatContainer.value;
    if (!el) return;
    requestAnimationFrame(() => {
        el.scrollTop = el.scrollHeight;
    });
};

watch(
    () => messages.value.length,
    async () => {
        await nextTick();
        scrollToBottom();
    },
);

watch(
    () => chatId.value,
    (newChatId) => {
        if (newChatId) {
            updateBreadcrumbsWithChatTitle(newChatId);
        }
    },
);

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
    const typingSpeedMs = 18;
    input.value = '';
    sending.value = true;

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

const insertSuggestion = (suggestion: number) => {
    const suggestions = [
        "What's this document about?",
        'Summarize this document.',
    ];

    input.value = suggestions[suggestion];
    nextTick(() => {
        sendMessage();
    });
};

const autoGrow = () => {
    const el = textareaRef.value;
    if (!el) return;

    // Reset height to recalculate
    el.style.height = 'auto';

    const maxHeight = lineHeightPx.value * maxRows;
    const newHeight = Math.min(el.scrollHeight, maxHeight);

    el.style.height = newHeight + 'px';

    // Optional: allow scroll once we hit max rows
    el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
};

const handleTextareaKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Enter' || event.shiftKey) return;
    event.preventDefault();
    sendMessage();
};

const textAreaInitialSizing = () => {
    const el = textareaRef.value;
    if (!el) return;

    // Get the computed line-height (in px)
    const style = window.getComputedStyle(el);
    lineHeightPx.value = parseFloat(style.lineHeight) || 16; // fallback

    // Initial sizing
    autoGrow();
};

const updateBreadcrumbsWithChatTitle = (chatId: string | null) => {
    const chat = chatsList.value.find((c) => c.chat_id === chatId);
    if (chat && chat.title) {
        breadcrumbs.value = [
            ...breadcrumbs.value.slice(0, 4),
            {
                title: chat.title,
                href: '#',
            },
        ];
    } else {
        breadcrumbs.value = [...breadcrumbs.value.slice(0, 4)];
    }
};

const deleteChat = (chatId: string) => {
    deletingChat.value =
        chatsList.value.find((c) => c.chat_id === chatId) ?? null;
    deleteDialogOpen.value = true;
};

const handleDialogOpen = (open: boolean) => {
    deleteDialogOpen.value = open;

    if (!open) {
        deletingChat.value = null;
    }
};

const confirmDelete = async () => {
    if (!deletingChat.value) return;

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
                handleDialogOpen(false);
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
    messages.value = page.props.interrogations || [];
    nextTick(scrollToBottom);

    textAreaInitialSizing();

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
                <!-- 5.5/7 = 78.571429% -->
                <div class="flex h-full w-full flex-col">
                    <div class="h-4/5 w-full">
                        <template v-if="messages.length === 0">
                            <div
                                class="flex h-full items-center justify-center"
                            >
                                <div class="flex flex-col">
                                    <div
                                        class="mb-3 text-center text-5xl font-bold"
                                    >
                                        Interrogate this document
                                    </div>
                                    <div
                                        class="mb-10 text-center text-muted-foreground"
                                    >
                                        Get started by asking a question about
                                        this document. Not sure what to ask?
                                    </div>

                                    <div class="flex flex-wrap gap-6">
                                        <div
                                            class="flex max-w-xs min-w-[220px] flex-1 items-center justify-between rounded-2xl border border-border bg-card px-5 py-3 shadow-sm transition hover:bg-muted/40 hover:shadow-lg"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-200 text-amber-950"
                                                >
                                                    <span class="text-lg"
                                                        ><FileText />
                                                    </span>
                                                </div>
                                                <span
                                                    class="text-center font-medium text-card-foreground"
                                                    >What's this document
                                                    about?</span
                                                >
                                            </div>
                                            <button
                                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full border border-input text-muted-foreground hover:bg-muted hover:text-foreground"
                                                @click="insertSuggestion(0)"
                                            >
                                                +
                                            </button>
                                        </div>

                                        <div
                                            class="flex max-w-xs min-w-[220px] flex-1 items-center justify-between rounded-2xl border border-border bg-card px-5 py-3 shadow-sm transition hover:bg-muted/40 hover:shadow-lg"
                                        >
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-lime-200 text-lime-950"
                                                >
                                                    <span class="text-lg"
                                                        ><ClipboardCheck />
                                                    </span>
                                                </div>
                                                <span
                                                    class="text-center font-medium text-card-foreground"
                                                    >Summarize this
                                                    document.</span
                                                >
                                            </div>
                                            <button
                                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full border border-input text-muted-foreground hover:bg-muted hover:text-foreground"
                                                @click="insertSuggestion(1)"
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex h-full w-full justify-center pt-6">
                                <div
                                    ref="chatContainer"
                                    class="flex h-full w-3/4 flex-col gap-4 overflow-y-auto pr-2 pb-2"
                                >
                                    <div
                                        v-for="(m, i) in messages"
                                        :key="i"
                                        :class="
                                            m.role === 'user'
                                                ? 'self-end bg-primary text-primary-foreground'
                                                : 'self-start bg-muted text-foreground'
                                        "
                                        class="max-w-3/4 rounded-md px-4 py-3 text-sm whitespace-pre-wrap"
                                    >
                                        <span
                                            class="inline-flex items-end gap-2 leading-relaxed"
                                        >
                                            <span class="whitespace-pre-wrap">{{
                                                m.content
                                            }}</span>
                                            <Spinner
                                                v-if="
                                                    m.role === 'assistant' &&
                                                    m.loading
                                                "
                                                size="sm"
                                                class="inline-block shrink-0 align-middle"
                                            />
                                            <span
                                                v-else-if="
                                                    m.role === 'assistant' &&
                                                    m.typing
                                                "
                                                class="typing-cursor"
                                                aria-hidden="true"
                                            ></span>
                                        </span>
                                        <div
                                            class="mt-1 text-[10px] opacity-70"
                                            :class="{
                                                'flex justify-end':
                                                    m.role === 'user',
                                            }"
                                        >
                                            {{
                                                new Date(
                                                    m.at,
                                                ).toLocaleTimeString()
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="h-1/5">
                        <div
                            class="flex h-full w-full flex-col items-center justify-center"
                        >
                            <div class="mx-auto mt-6 w-3/4">
                                <div class="rounded-2xl border border-input">
                                    <div
                                        class="flex flex-col rounded-2xl bg-background text-sm shadow-sm"
                                    >
                                        <div
                                            class="flex items-center gap-3 px-4 pt-2 pb-1"
                                        >
                                            <textarea
                                                ref="textareaRef"
                                                v-model="input"
                                                rows="1"
                                                class="w-full resize-none border-none bg-transparent text-sm leading-snug text-foreground outline-none placeholder:text-muted-foreground"
                                                placeholder="Ask a question about this document..."
                                                maxlength="500"
                                                @input="autoGrow"
                                                @keydown="handleTextareaKeydown"
                                            ></textarea>
                                            <button
                                                class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center text-xs"
                                                :disabled="
                                                    sending || !input.trim()
                                                "
                                                @click="sendMessage"
                                            >
                                                <SendHorizontal />
                                            </button>
                                        </div>
                                        <div
                                            class="flex items-center justify-end px-4 py-2 text-xs text-muted-foreground"
                                        >
                                            {{ input.length }}/500
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 text-xs text-muted-foreground">
                                This application can make mistakes. Please
                                verify any information it provides.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-[21.428571%] p-2 lg:w-1/7">
                <!-- 1.5/7 = 21.428571% -->
                <div
                    class="flex items-center justify-between border-b border-border"
                >
                    <div class="text-md font-bold">Your chats</div>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                class="cursor-pointer rounded-full p-1 hover:bg-muted"
                            >
                                <Ellipsis />
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
                                class="cursor-pointer"
                                @click="deleteAllChats"
                            >
                                Clear all chats
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <div class="mt-4 flex flex-col gap-3">
                    <div
                        v-for="chat in chatsList"
                        :key="chat.chat_id"
                        class="flex cursor-pointer justify-between gap-2 rounded-lg border border-border bg-background p-3 hover:bg-muted/50"
                    >
                        <div
                            class="truncate"
                            @click="
                                router.visit(interrogate(), {
                                    method: 'get',
                                    data: {
                                        id: documentInfo?._id,
                                        chat_id: chat.chat_id,
                                    },
                                    preserveState: false,
                                    replace: true,
                                })
                            "
                        >
                            {{ chat.title ?? 'Untitled Chat' }}
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    class="cursor-pointer rounded p-1 hover:bg-muted"
                                >
                                    <Ellipsis size="16" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    class="cursor-pointer"
                                    @click="deleteChat(chat.chat_id)"
                                >
                                    <div class="flex w-full items-center gap-1">
                                        <Trash
                                            size="16"
                                            class="mr-2 text-red-500"
                                        />
                                        <p class="text-red-500">Delete</p>
                                    </div>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>
        </div>

        <Dialog :open="deleteDialogOpen" @update:open="handleDialogOpen">
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
                            @click="handleDialogOpen(false)"
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        :disabled="!deletingChat || isDeleting"
                        @click="confirmDelete"
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
                        Are you sure you want to delete all chats for this
                        document? This action cannot be undone.
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
    </AppLayout>
</template>

<style scoped>
.typing-cursor {
    display: inline-block;
    width: 2px;
    height: 1rem;
    margin-left: 2px;
    background: currentColor;
    animation: typing-cursor-blink 0.8s infinite;
    vertical-align: -0.125em;
}

@keyframes typing-cursor-blink {
    0%,
    50% {
        opacity: 1;
    }

    51%,
    100% {
        opacity: 0;
    }
}
</style>
