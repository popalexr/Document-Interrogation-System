<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import documents from '@/routes/documents';
import { Icon as DocumentIcon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import {
    Download,
    Edit3,
    FileText,
    MessageSquareText,
    MoreVertical,
    Play,
    RefreshCcw,
    Sparkles,
    Star,
    Trash2,
} from 'lucide-vue-next';
import DocumentStatusBadge from './DocumentStatusBadge.vue';
import {
    canAskAi,
    documentIcon,
    extClasses,
    fileExt,
    formatDate,
    formatSize,
    statusDescription,
    statusKind,
} from './documentUtils';
import type { UploadItem } from './types';

defineProps<{
    uploads: UploadItem[];
    selectedDocumentId: string | null;
    favoriteDocumentId?: string | null;
}>();

const emit = defineEmits<{
    select: [upload: UploadItem];
    view: [upload: UploadItem];
    delete: [upload: UploadItem];
    toggleFavorite: [upload: UploadItem];
}>();
</script>

<template>
    <div
        class="flex max-h-[42rem] flex-1 flex-col overflow-hidden rounded-lg border bg-background shadow-xs"
    >
        <div
            class="grid h-10 shrink-0 grid-cols-[3rem_minmax(18rem,1.6fr)_7rem_12rem_17rem] items-center border-b bg-muted/30 px-4 text-xs text-muted-foreground"
        >
            <div class="flex justify-center">
                <Star class="size-4" />
            </div>
            <div>Document</div>
            <div>Type</div>
            <div>Status</div>
            <div class="text-center">Actions</div>
        </div>

        <div
            v-if="!uploads.length"
            class="flex min-h-0 flex-1 items-center justify-center text-sm text-muted-foreground"
        >
            No documents found.
        </div>

        <div v-else class="min-h-0 flex-1 overflow-auto">
            <ContextMenu v-for="upload in uploads" :key="upload._id">
                <ContextMenuTrigger as-child>
                    <div
                        class="grid min-h-16 cursor-pointer grid-cols-[3rem_minmax(18rem,1.6fr)_7rem_12rem_17rem] items-center border-b px-4 transition-colors hover:bg-muted/30"
                        :class="{
                            'bg-blue-50/70 hover:bg-blue-50':
                                selectedDocumentId === upload._id,
                        }"
                        @click="emit('select', upload)"
                        @dblclick="emit('view', upload)"
                    >
                        <div class="flex justify-center" @click.stop>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                :disabled="favoriteDocumentId === upload._id"
                                :aria-label="
                                    upload.favorite
                                        ? 'Remove from favorites'
                                        : 'Mark as favorite'
                                "
                                @click="emit('toggleFavorite', upload)"
                            >
                                <Star
                                    class="size-5"
                                    :class="
                                        upload.favorite
                                            ? 'fill-amber-400 text-amber-500'
                                            : 'text-muted-foreground'
                                    "
                                />
                            </Button>
                        </div>

                        <div class="flex min-w-0 items-center gap-3">
                            <DocumentIcon
                                :icon="documentIcon(upload)"
                                class="size-9 shrink-0"
                            />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ upload.original_name }}
                                </p>
                                <p
                                    class="mt-0.5 truncate text-xs text-muted-foreground"
                                >
                                    {{ formatSize(upload.size) }}
                                    <span class="px-1">&middot;</span>
                                    Uploaded {{ formatDate(upload.created_at) }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <Badge
                                variant="outline"
                                :class="extClasses(fileExt(upload))"
                            >
                                {{ fileExt(upload) || 'FILE' }}
                            </Badge>
                        </div>

                        <div>
                            <DocumentStatusBadge
                                :upload="upload"
                                show-spinner
                            />
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ statusDescription(upload) }}
                            </p>
                        </div>

                        <div
                            class="flex items-center justify-end gap-2"
                            @click.stop
                        >
                            <Button
                                v-if="canAskAi(upload)"
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                as-child
                            >
                                <Link
                                    :href="
                                        documents.interrogate.url({
                                            query: { id: upload._id },
                                        })
                                    "
                                    prefetch
                                >
                                    <Sparkles class="size-4 text-blue-600" />
                                    Ask AI
                                </Link>
                            </Button>
                            <Button
                                v-else-if="statusKind(upload) === 'failed'"
                                variant="outline"
                                size="sm"
                                class="gap-2"
                            >
                                <RefreshCcw class="size-4" />
                                Retry
                            </Button>
                            <Button
                                v-else-if="statusKind(upload) === 'not_indexed'"
                                variant="outline"
                                size="sm"
                                class="gap-2"
                            >
                                <Play class="size-4" />
                                Index
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                disabled
                            >
                                <Sparkles class="size-4" />
                                Ask AI
                            </Button>

                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                as-child
                            >
                                <Link
                                    :href="
                                        documents.view.url({
                                            query: { id: upload._id },
                                        })
                                    "
                                    prefetch
                                >
                                    <FileText class="size-4" />
                                    View
                                </Link>
                            </Button>

                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8"
                                    >
                                        <MoreVertical class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-44">
                                    <DropdownMenuItem :as-child="true">
                                        <Link
                                            as="button"
                                            class="block w-full cursor-pointer text-left"
                                            :href="
                                                documents.view.url({
                                                    query: { id: upload._id },
                                                })
                                            "
                                            prefetch
                                        >
                                            <FileText class="mr-2 size-4" />
                                            View
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem :as-child="true">
                                        <a
                                            class="block w-full cursor-pointer text-left"
                                            :href="
                                                documents.downloadDocument.url({
                                                    query: { id: upload._id },
                                                })
                                            "
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <Download class="mr-2 size-4" />
                                            Download
                                        </a>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem :as-child="true">
                                        <Link
                                            as="button"
                                            class="block w-full cursor-pointer text-left"
                                            :href="
                                                documents.interrogate.url({
                                                    query: { id: upload._id },
                                                })
                                            "
                                            prefetch
                                        >
                                            <MessageSquareText
                                                class="mr-2 size-4"
                                            />
                                            Interrogate
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem :as-child="true">
                                        <Link
                                            as="button"
                                            class="block w-full cursor-pointer text-left"
                                            :href="
                                                documents.edit.url({
                                                    query: { id: upload._id },
                                                })
                                            "
                                            prefetch
                                        >
                                            <Edit3 class="mr-2 size-4" />
                                            Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-destructive"
                                        :as-child="true"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center"
                                            @click="emit('delete', upload)"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Delete
                                        </button>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </ContextMenuTrigger>

                <ContextMenuContent class="w-44">
                    <ContextMenuItem :as-child="true">
                        <Link
                            as="button"
                            class="block w-full cursor-pointer text-left"
                            :href="
                                documents.view.url({
                                    query: { id: upload._id },
                                })
                            "
                            prefetch
                        >
                            <FileText class="mr-2 size-4" />
                            View
                        </Link>
                    </ContextMenuItem>
                    <ContextMenuItem :as-child="true">
                        <a
                            class="block w-full cursor-pointer text-left"
                            :href="
                                documents.downloadDocument.url({
                                    query: { id: upload._id },
                                })
                            "
                            target="_blank"
                            rel="noopener"
                        >
                            <Download class="mr-2 size-4" />
                            Download
                        </a>
                    </ContextMenuItem>
                    <ContextMenuItem :as-child="true">
                        <Link
                            as="button"
                            class="block w-full cursor-pointer text-left"
                            :href="
                                documents.interrogate.url({
                                    query: { id: upload._id },
                                })
                            "
                            prefetch
                        >
                            <MessageSquareText class="mr-2 size-4" />
                            Interrogate
                        </Link>
                    </ContextMenuItem>
                    <ContextMenuItem :as-child="true">
                        <Link
                            as="button"
                            class="block w-full cursor-pointer text-left"
                            :href="
                                documents.edit.url({
                                    query: { id: upload._id },
                                })
                            "
                            prefetch
                        >
                            <Edit3 class="mr-2 size-4" />
                            Edit
                        </Link>
                    </ContextMenuItem>
                    <ContextMenuSeparator />
                    <ContextMenuItem class="text-destructive" :as-child="true">
                        <button
                            type="button"
                            class="flex w-full items-center"
                            @click="emit('delete', upload)"
                        >
                            <Trash2 class="mr-2 size-4" />
                            Delete
                        </button>
                    </ContextMenuItem>
                </ContextMenuContent>
            </ContextMenu>
        </div>
    </div>
</template>
