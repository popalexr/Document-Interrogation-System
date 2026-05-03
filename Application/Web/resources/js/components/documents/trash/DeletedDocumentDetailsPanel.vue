<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Icon as DocumentIcon } from '@iconify/vue';
import { RefreshCcw, X } from 'lucide-vue-next';
import { documentIcon, fileExt, formatDate, formatSize } from './documentUtils';
import type { DeletedDocument } from './types';

defineProps<{
    document: DeletedDocument;
    ownerName: string;
    restoringDocumentId: string | null;
}>();

const emit = defineEmits<{
    close: [];
    restore: [document: DeletedDocument];
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
                    :icon="documentIcon(document)"
                    class="size-12 shrink-0"
                />
                <div class="min-w-0">
                    <p class="truncate font-medium">
                        {{ document.original_name }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ formatSize(document.size) }}
                        <span class="px-1">&middot;</span>
                        {{ fileExt(document) || 'FILE' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Badge variant="secondary">Deleted</Badge>
                <span class="text-sm text-muted-foreground">
                    Removed from document library
                </span>
            </div>

            <dl class="space-y-4 text-sm">
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">Deleted date</dt>
                    <dd class="text-right">
                        {{ formatDate(document.deleted_at) }}
                    </dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">Upload date</dt>
                    <dd class="text-right">
                        {{ formatDate(document.created_at) }}
                    </dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">MIME type</dt>
                    <dd class="text-right break-words">
                        {{ document.mime_type }}
                    </dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">Size</dt>
                    <dd class="text-right">
                        {{ formatSize(document.size) }}
                    </dd>
                </div>
                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4">
                    <dt class="text-muted-foreground">Owner</dt>
                    <dd class="text-right">{{ ownerName }}</dd>
                </div>
            </dl>
        </div>

        <div class="mt-auto border-t p-5">
            <h3 class="mb-3 text-sm font-semibold">Actions</h3>
            <Button
                class="w-full justify-start gap-2"
                :disabled="restoringDocumentId === document._id"
                @click="emit('restore', document)"
            >
                <RefreshCcw class="size-4" />
                Restore
            </Button>
        </div>
    </aside>
</template>
