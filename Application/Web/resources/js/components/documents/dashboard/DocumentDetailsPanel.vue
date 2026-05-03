<script setup lang="ts">
import { Button } from '@/components/ui/button';
import documents from '@/routes/documents';
import { Icon as DocumentIcon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import {
    Check,
    Download,
    FileText,
    Sparkles,
    Trash2,
    X,
} from 'lucide-vue-next';
import DocumentStatusBadge from './DocumentStatusBadge.vue';
import {
    canAskAi,
    documentIcon,
    fileExt,
    formatDate,
    formatSize,
    statusDescription,
} from './documentUtils';
import type { UploadItem } from './types';

defineProps<{
    upload: UploadItem;
    ownerName: string;
}>();

const emit = defineEmits<{
    close: [];
    delete: [upload: UploadItem];
}>();
</script>

<template>
    <aside
        class="flex w-[26rem] shrink-0 flex-col rounded-lg border bg-background shadow-xs"
    >
        <div class="flex items-center justify-between p-5">
            <h2 class="font-semibold">Document details</h2>
            <Button
                variant="ghost"
                size="icon"
                class="size-8"
                @click="emit('close')"
            >
                <X class="size-4" />
            </Button>
        </div>

        <div class="space-y-5 px-5 pb-6">
            <div class="flex items-center gap-3">
                <DocumentIcon
                    :icon="documentIcon(upload)"
                    class="size-12 shrink-0"
                />
                <div class="min-w-0">
                    <p class="truncate font-medium">
                        {{ upload.original_name }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ formatSize(upload.size) }}
                        <span class="px-1">&middot;</span>
                        {{ fileExt(upload) }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <DocumentStatusBadge :upload="upload" />
                <span class="text-sm text-muted-foreground">
                    {{ statusDescription(upload) }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <Button v-if="canAskAi(upload)" class="gap-2" as-child>
                    <Link
                        :href="
                            documents.interrogate.url({
                                query: { id: upload._id },
                            })
                        "
                        prefetch
                    >
                        <Sparkles class="size-4" />
                        Ask AI
                    </Link>
                </Button>
                <Button v-else class="gap-2" disabled>
                    <Sparkles class="size-4" />
                    Ask AI
                </Button>

                <Button v-if="canAskAi(upload)" class="gap-2" as-child>
                    <Link
                        :href="
                            documents.edit.url({
                                query: { id: upload._id },
                            })
                        "
                        prefetch
                    >
                        <FileText class="size-4" />
                        Edit using AI
                    </Link>
                </Button>
                <Button v-else class="gap-2" disabled>
                    <FileText class="size-4" />
                    Edit using AI
                </Button>
            </div>

            <dl class="space-y-4 text-sm">
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">Upload date</dt>
                    <dd class="text-right">
                        {{ formatDate(upload.created_at) }}
                    </dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">MIME type</dt>
                    <dd class="text-right break-words">
                        {{ upload.mime_type }}
                    </dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">Size</dt>
                    <dd class="text-right">
                        {{ formatSize(upload.size) }}
                    </dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">Owner</dt>
                    <dd class="text-right">{{ ownerName }}</dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">Last modify</dt>
                    <dd class="text-right">
                        {{ formatDate(upload.updated_at ?? upload.created_at) }}
                    </dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">Searchable</dt>
                    <dd
                        class="flex items-center justify-end gap-1"
                        :class="
                            canAskAi(upload)
                                ? 'text-green-600'
                                : 'text-muted-foreground'
                        "
                    >
                        <Check class="size-4" />
                        {{ canAskAi(upload) ? 'Yes' : 'No' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="mt-auto border-t p-5">
            <h3 class="mb-3 text-sm font-semibold">Actions</h3>
            <Button
                variant="outline"
                class="w-full justify-start gap-2"
                as-child
            >
                <a
                    :href="
                        documents.downloadDocument.url({
                            query: { id: upload._id },
                        })
                    "
                    target="_blank"
                    rel="noopener"
                >
                    <Download class="size-4" />
                    Download original file
                </a>
            </Button>
        </div>

        <div class="border-t p-5">
            <button
                type="button"
                class="inline-flex items-center gap-2 text-sm font-medium text-red-600"
                @click="emit('delete', upload)"
            >
                <Trash2 class="size-4" />
                Delete document
            </button>
        </div>
    </aside>
</template>
