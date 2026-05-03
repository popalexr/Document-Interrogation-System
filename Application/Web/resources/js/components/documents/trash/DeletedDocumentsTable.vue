<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import { Icon as DocumentIcon } from '@iconify/vue';
import { RefreshCcw } from 'lucide-vue-next';
import {
    documentIcon,
    extClasses,
    fileExt,
    formatDate,
    formatSize,
} from './documentUtils';
import type { DeletedDocument } from './types';

defineProps<{
    documents: DeletedDocument[];
    selectedDocumentId: string | null;
    restoringDocumentId: string | null;
}>();

const emit = defineEmits<{
    select: [document: DeletedDocument];
    restore: [document: DeletedDocument];
}>();
</script>

<template>
    <div
        class="flex max-h-[42rem] flex-1 flex-col overflow-hidden rounded-lg border bg-background shadow-xs"
    >
        <div
            class="grid h-10 shrink-0 grid-cols-[3rem_minmax(18rem,1.6fr)_7rem_12rem_12rem] items-center border-b bg-muted/30 px-4 text-xs text-muted-foreground"
        >
            <div>
                <input
                    type="checkbox"
                    class="size-4 rounded border-input"
                    aria-label="Select all deleted documents"
                />
            </div>
            <div>Document</div>
            <div>Type</div>
            <div>Deleted</div>
            <div class="text-center">Actions</div>
        </div>

        <div
            v-if="!documents.length"
            class="flex min-h-0 flex-1 items-center justify-center text-sm text-muted-foreground"
        >
            No deleted documents found.
        </div>

        <div v-else class="min-h-0 flex-1 overflow-auto">
            <ContextMenu v-for="document in documents" :key="document._id">
                <ContextMenuTrigger as-child>
                    <div
                        class="grid min-h-16 cursor-pointer grid-cols-[3rem_minmax(18rem,1.6fr)_7rem_12rem_12rem] items-center border-b px-4 text-sm transition-colors last:border-b-0 hover:bg-muted/40"
                        :class="{
                            'bg-muted/50': selectedDocumentId === document._id,
                        }"
                        @click="emit('select', document)"
                    >
                        <div>
                            <input
                                type="checkbox"
                                class="size-4 rounded border-input"
                                :aria-label="`Select ${document.original_name}`"
                                @click.stop
                            />
                        </div>

                        <div class="flex min-w-0 items-center gap-3">
                            <DocumentIcon
                                :icon="documentIcon(document)"
                                class="size-9 shrink-0"
                            />
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ document.original_name }}
                                </p>
                                <p
                                    class="mt-1 truncate text-xs text-muted-foreground"
                                >
                                    {{ formatSize(document.size) }}
                                    <span class="px-1">&middot;</span>
                                    {{ document.mime_type }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <Badge
                                variant="outline"
                                class="border text-xs"
                                :class="extClasses(fileExt(document))"
                            >
                                {{ fileExt(document) || 'FILE' }}
                            </Badge>
                        </div>

                        <div class="text-muted-foreground">
                            {{ formatDate(document.deleted_at) }}
                        </div>

                        <div class="flex justify-center">
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                :disabled="restoringDocumentId === document._id"
                                @click.stop="emit('restore', document)"
                            >
                                <RefreshCcw class="size-4" />
                                Restore
                            </Button>
                        </div>
                    </div>
                </ContextMenuTrigger>

                <ContextMenuContent class="w-40">
                    <ContextMenuItem :as-child="true">
                        <button
                            type="button"
                            class="flex w-full items-center"
                            :disabled="restoringDocumentId === document._id"
                            @click="emit('restore', document)"
                        >
                            <RefreshCcw class="mr-2 size-4" />
                            Restore
                        </button>
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenu>
        </div>
    </div>
</template>
