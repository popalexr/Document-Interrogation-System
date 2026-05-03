<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreHorizontal, Trash } from 'lucide-vue-next';
import { formatRelative } from './interrogationHelpers';
import type { ChatsList } from './types';

defineProps<{
    activeChatId: string | null;
    chats: ChatsList[];
}>();

const emit = defineEmits<{
    clearChats: [];
    deleteChat: [chatId: string];
    newChat: [];
    selectChat: [chatId: string];
}>();
</script>

<template>
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
                        @click="emit('newChat')"
                    >
                        New chat
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        class="cursor-pointer text-destructive"
                        :disabled="chats.length === 0"
                        @click="emit('clearChats')"
                    >
                        Clear all chats
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div class="mt-5 flex flex-col gap-3">
            <div
                v-for="chat in chats"
                :key="chat.chat_id"
                class="rounded-lg border bg-background p-4 shadow-xs transition hover:bg-muted/40"
                :class="{
                    'border-primary/40 bg-muted/50':
                        chat.chat_id === activeChatId,
                }"
            >
                <div class="flex items-start justify-between gap-3">
                    <button
                        type="button"
                        class="min-w-0 flex-1 text-left"
                        @click="emit('selectChat', chat.chat_id)"
                    >
                        <h3 class="truncate text-sm font-medium">
                            {{ chat.title ?? 'Untitled chat' }}
                        </h3>
                        <p
                            v-if="chat.document_count !== undefined"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            {{ chat.document_count }}
                            {{
                                chat.document_count === 1
                                    ? 'document'
                                    : 'documents'
                            }}
                            <template v-if="chat.updated_at">
                                - {{ formatRelative(chat.updated_at) }}
                            </template>
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
                                @click="emit('deleteChat', chat.chat_id)"
                            >
                                <Trash class="mr-2 size-4" />
                                Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div
                v-if="chats.length === 0"
                class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
            >
                No chats yet.
            </div>
        </div>
    </aside>
</template>
