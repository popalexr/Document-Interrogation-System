<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import {
    Bot,
    Ellipsis,
    Paperclip,
    SendHorizontal,
    Trash,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

type EditorTab = 'chat' | 'history';

type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
    reasoning?: string | null;
    edit_document_id?: string | null;
    at?: string | Date | null;
    loading?: boolean;
};

type ChatHistoryEntry = {
    chat_id: string;
    title: string | null;
    created_at?: string | Date | null;
    updated_at?: string | Date | null;
};

const props = defineProps<{
    messages: ChatMessage[];
    chats: ChatHistoryEntry[];
    activeChatId: string | null;
    prompt: string;
    sending: boolean;
    previewDocumentId: string;
    originalDocumentId: string;
    documentName: string;
}>();

const emit = defineEmits<{
    (e: 'update:prompt', value: string): void;
    (e: 'submit'): void;
    (e: 'new-chat'): void;
    (e: 'select-chat', chatId: string): void;
    (e: 'preview', documentId: string): void;
    (e: 'reset-preview'): void;
    (e: 'open-document', documentId: string): void;
    (e: 'delete-chat', chatId: string): void;
}>();

const activeTab = ref<EditorTab>('chat');
const chatContainer = ref<HTMLElement | null>(null);
const textareaRef = ref<HTMLTextAreaElement | null>(null);
const lineHeightPx = ref(0);
const deleteDialogOpen = ref(false);
const deletingChat = ref<ChatHistoryEntry | null>(null);

const maxRows = 4;

const canSend = computed(
    () => props.prompt.trim().length > 0 && !props.sending,
);

const assistantContent = (message: ChatMessage) => {
    if (message.content.trim()) {
        return message.content;
    }

    if (message.loading) {
        return 'Generating edited document...';
    }

    if (message.edit_document_id) {
        return 'Edited document generated successfully. Preview is ready.';
    }

    return 'Waiting for edit result...';
};

const isPreviewingMessage = (message: ChatMessage) =>
    Boolean(message.edit_document_id) &&
    message.edit_document_id === props.previewDocumentId;

const showMessageActions = (message: ChatMessage) =>
    message.role === 'assistant' &&
    typeof message.edit_document_id === 'string' &&
    message.edit_document_id.length > 0;

const formatTimestamp = (value?: string | Date | null) => {
    if (!value) {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleString();
};

const historyEntries = computed<ChatHistoryEntry[]>(() => props.chats);

const isActiveHistoryEntry = (entry: ChatHistoryEntry) =>
    entry.chat_id === props.activeChatId;

const openDeleteDialog = (entry: ChatHistoryEntry) => {
    deletingChat.value = entry;
    deleteDialogOpen.value = true;
};

const handleDeleteDialogOpen = (open: boolean) => {
    deleteDialogOpen.value = open;

    if (!open) {
        deletingChat.value = null;
    }
};

const confirmDelete = () => {
    if (!deletingChat.value) {
        return;
    }

    emit('delete-chat', deletingChat.value.chat_id);
    handleDeleteDialogOpen(false);
};

const scrollToBottom = () => {
    const container = chatContainer.value;
    if (!container) {
        return;
    }

    requestAnimationFrame(() => {
        container.scrollTop = container.scrollHeight;
    });
};

watch(
    () => props.messages,
    async () => {
        await nextTick();
        scrollToBottom();
    },
    { deep: true },
);

const updatePrompt = (value: string | number) => {
    emit('update:prompt', String(value));
};

const autoGrow = () => {
    const el = textareaRef.value;
    if (!el) {
        return;
    }

    el.style.height = 'auto';

    const maxHeight = lineHeightPx.value * maxRows;
    const nextHeight = Math.min(el.scrollHeight, maxHeight);

    el.style.height = `${nextHeight}px`;
    el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
};

const send = () => {
    if (!canSend.value) {
        return;
    }

    emit('submit');
};

const handlePromptKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Enter' || event.shiftKey) {
        return;
    }

    event.preventDefault();
    send();
};

const applyPromptChip = (chip: string) => {
    emit('update:prompt', chip);
};

onMounted(() => {
    const el = textareaRef.value;
    if (!el) {
        return;
    }

    const styles = window.getComputedStyle(el);
    lineHeightPx.value = parseFloat(styles.lineHeight) || 20;
    autoGrow();
});

watch(
    () => props.prompt,
    async () => {
        await nextTick();
        autoGrow();
    },
);
</script>

<template>
    <aside
        class="flex min-h-[26rem] w-full min-w-0 flex-col border-t border-border/80 bg-muted/20 lg:min-h-0 lg:w-[35rem] lg:max-w-[44vw] lg:border-t-0 lg:border-l"
    >
        <div class="border-b border-border/80 bg-background px-4 sm:px-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-7">
                    <button
                        type="button"
                        class="border-b-[3px] px-1 py-4"
                        :class="
                            activeTab === 'chat'
                                ? 'border-primary font-semibold text-foreground'
                                : 'border-transparent text-muted-foreground'
                        "
                        @click="activeTab = 'chat'"
                    >
                        Chat
                    </button>
                    <button
                        type="button"
                        class="border-b-[3px] px-1 py-4"
                        :class="
                            activeTab === 'history'
                                ? 'border-primary font-semibold text-foreground'
                                : 'border-transparent text-muted-foreground'
                        "
                        @click="activeTab = 'history'"
                    >
                        History
                    </button>
                </div>

                <Button size="sm" class="shrink-0" @click="emit('new-chat')">
                    New chat
                </Button>
            </div>
        </div>

        <template v-if="activeTab === 'chat'">
            <div
                ref="chatContainer"
                class="min-h-0 flex-1 space-y-4 overflow-y-auto px-4 py-4 sm:px-5"
            >
                <div
                    v-for="(message, index) in messages"
                    :key="`${message.role}-${index}`"
                >
                    <div
                        v-if="message.role === 'user'"
                        class="flex items-start justify-end gap-3"
                    >
                        <div
                            class="max-w-[82%] rounded-2xl bg-blue-50 px-4 py-3 leading-8 text-foreground whitespace-pre-line"
                        >
                            {{ message.content }}
                        </div>
                    </div>

                    <div
                        v-if="message.role === 'assistant' && message.reasoning"
                        class="flex items-start gap-3 py-3 hidden"
                    >
                        <Avatar
                            class="size-10 border border-primary/20 bg-primary/10"
                        >
                            <AvatarFallback class="bg-transparent text-primary">
                                <Bot class="size-5" />
                            </AvatarFallback>
                        </Avatar>

                        <div
                            class="w-full overflow-hidden rounded-xl border border-border bg-background"
                        >
                            <div
                                class="border-b border-border/70 bg-muted/70 px-4 py-2"
                            >
                                <p class="font-medium text-foreground">
                                    Reasoning
                                </p>
                            </div>

                            <div
                                class="space-y-3 px-4 py-4 leading-[1.65] text-foreground whitespace-pre-line"
                            >
                                {{ message.reasoning }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="message.role === 'assistant'"
                        class="flex items-start gap-3 py-3"
                    >
                        <Avatar
                            class="size-10 border border-primary/20 bg-primary/10"
                        >
                            <AvatarFallback class="bg-transparent text-primary">
                                <Bot class="size-5" />
                            </AvatarFallback>
                        </Avatar>

                        <div
                            class="w-full overflow-hidden rounded-xl border border-border bg-background"
                        >
                            <div
                                class="border-b border-border/70 bg-muted/70 px-4 py-2"
                            >
                                <p class="font-medium text-foreground">
                                    AI Edited
                                </p>
                            </div>

                            <div
                                class="space-y-3 px-4 py-4 leading-[1.65] text-foreground whitespace-pre-line"
                            >
                                {{ assistantContent(message) }}
                            </div>

                            <div
                                v-if="showMessageActions(message)"
                                class="flex flex-wrap gap-2 border-t border-border/70 px-4 py-3"
                            >
                                <Button
                                    size="sm"
                                    class="shadow-none"
                                    @click="
                                        emit(
                                            'open-document',
                                            message.edit_document_id || '',
                                        )
                                    "
                                >
                                    Save Change
                                </Button>
                                <Button
                                    size="sm"
                                    :variant="
                                        isPreviewingMessage(message)
                                            ? 'default'
                                            : 'outline'
                                    "
                                    @click="
                                        emit(
                                            'preview',
                                            message.edit_document_id || '',
                                        )
                                    "
                                >
                                    Preview Change
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="
                                        previewDocumentId === originalDocumentId
                                    "
                                    @click="emit('reset-preview')"
                                >
                                    Discard
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-border/80 bg-background p-4">
                <div
                    class="flex items-center gap-2 rounded-lg border border-input bg-background px-2"
                >
                    <Button variant="ghost" size="icon" class="size-8" disabled>
                        <Paperclip class="size-4 text-muted-foreground" />
                    </Button>
                    <textarea
                        ref="textareaRef"
                        :value="prompt"
                        rows="1"
                        placeholder="Send a message..."
                        class="max-h-24 min-h-11 w-full resize-none border-0 bg-transparent px-0 py-3 text-base leading-snug text-foreground shadow-none outline-none placeholder:text-muted-foreground"
                        @input="
                            updatePrompt(
                                ($event.target as HTMLTextAreaElement).value,
                            )
                        "
                        @keydown="handlePromptKeydown"
                    ></textarea>
                    <Button
                        size="sm"
                        class="h-9 px-3"
                        :disabled="!canSend"
                        @click="send"
                    >
                        <SendHorizontal class="size-4" />
                        {{ sending ? 'Sending...' : 'Send' }}
                    </Button>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                <p class="mb-3 text-sm font-medium text-foreground">
                    Edit chats
                </p>

                <div
                    v-if="historyEntries.length === 0"
                    class="rounded-lg border border-dashed border-border bg-background px-4 py-3 text-sm text-muted-foreground"
                >
                    No edit chats yet.
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="entry in historyEntries"
                        :key="entry.chat_id"
                        class="flex items-center gap-2 rounded-lg border px-3 py-3 transition-colors"
                        :class="
                            isActiveHistoryEntry(entry)
                                ? 'border-primary/30 bg-primary/5'
                                : 'border-border bg-background hover:bg-muted/50'
                        "
                    >
                        <button
                            type="button"
                            class="min-w-0 flex-1 text-left"
                            @click="emit('select-chat', entry.chat_id)"
                        >
                            <p
                                class="truncate text-sm font-medium"
                                :class="
                                    isActiveHistoryEntry(entry)
                                        ? 'text-foreground'
                                        : 'text-foreground/90'
                                "
                            >
                                {{ entry.title ?? 'Untitled Chat' }}
                            </p>
                            <p
                                v-if="
                                    formatTimestamp(
                                        entry.updated_at ?? entry.created_at,
                                    )
                                "
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{
                                    formatTimestamp(
                                        entry.updated_at ?? entry.created_at,
                                    )
                                }}
                            </p>
                        </button>
                        <span
                            v-if="isActiveHistoryEntry(entry)"
                            class="shrink-0 text-xs font-medium text-primary"
                        >
                            Current
                        </span>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <button
                                    type="button"
                                    class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    @click.stop
                                >
                                    <Ellipsis class="size-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    class="cursor-pointer"
                                    @click="openDeleteDialog(entry)"
                                >
                                    <div
                                        class="flex items-center gap-2 text-destructive"
                                    >
                                        <Trash class="size-4" />
                                        Delete
                                    </div>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>
        </template>

        <Dialog :open="deleteDialogOpen" @update:open="handleDeleteDialogOpen">
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
                        :disabled="!deletingChat"
                        @click="confirmDelete"
                    >
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </aside>
</template>
