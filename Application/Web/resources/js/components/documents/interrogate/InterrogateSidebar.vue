<script setup lang="ts">
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue';
import DropdownMenuContent from '@/components/ui/dropdown-menu/DropdownMenuContent.vue';
import DropdownMenuItem from '@/components/ui/dropdown-menu/DropdownMenuItem.vue';
import DropdownMenuTrigger from '@/components/ui/dropdown-menu/DropdownMenuTrigger.vue';
import { Ellipsis, Trash } from 'lucide-vue-next';
import type { ChatsList } from './types';

defineProps<{
    chats: ChatsList[];
}>();

const emit = defineEmits<{
    newChat: [];
    clearChats: [];
    selectChat: [chatId: string];
    deleteChat: [chatId: string];
}>();
</script>

<template>
    <div class="w-[21.428571%] p-2 lg:w-1/7">
        <div class="flex items-center justify-between border-b border-border">
            <div class="text-md font-bold">Your chats</div>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="cursor-pointer rounded-full p-1 hover:bg-muted"
                        aria-label="Chat actions"
                    >
                        <Ellipsis />
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
                        class="cursor-pointer"
                        @click="emit('clearChats')"
                    >
                        Clear all chats
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
        <div class="mt-4 flex flex-col gap-3">
            <div
                v-for="chat in chats"
                :key="chat.chat_id"
                class="flex cursor-pointer justify-between gap-2 rounded-lg border border-border bg-background p-3 hover:bg-muted/50"
            >
                <div class="truncate" @click="emit('selectChat', chat.chat_id)">
                    {{ chat.title ?? 'Untitled Chat' }}
                </div>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="cursor-pointer rounded p-1 hover:bg-muted"
                            aria-label="Chat options"
                        >
                            <Ellipsis :size="16" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            class="cursor-pointer"
                            @click="emit('deleteChat', chat.chat_id)"
                        >
                            <div class="flex w-full items-center gap-1">
                                <Trash :size="16" class="mr-2 text-red-500" />
                                <p class="text-red-500">Delete</p>
                            </div>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </div>
</template>
