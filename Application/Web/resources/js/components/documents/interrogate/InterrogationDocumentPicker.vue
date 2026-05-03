<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Icon as DocumentIcon } from '@iconify/vue';
import { Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { documentIcon, fileExt, formatSize } from './interrogationHelpers';
import type { DocumentInfo } from './types';

const props = defineProps<{
    documents: DocumentInfo[];
    selectedDocumentIds: string[];
}>();

const emit = defineEmits<{
    'update:selectedDocumentIds': [documentIds: string[]];
}>();

const open = defineModel<boolean>('open', { required: true });

const documentSearch = ref('');

const allDocumentsSelected = computed(
    () =>
        props.documents.length > 0 &&
        props.selectedDocumentIds.length === props.documents.length,
);

const selectedDocumentsCount = computed(() => props.selectedDocumentIds.length);

const filteredDocuments = computed(() => {
    const query = documentSearch.value.trim().toLowerCase();

    return props.documents.filter((document) => {
        if (!query) return true;

        return (
            document.original_name.toLowerCase().includes(query) ||
            fileExt(document).toLowerCase().includes(query) ||
            document.mime_type.toLowerCase().includes(query)
        );
    });
});

function isDocumentSelected(documentId: string): boolean {
    return props.selectedDocumentIds.includes(documentId);
}

function toggleDocument(documentId: string): void {
    if (isDocumentSelected(documentId)) {
        emit(
            'update:selectedDocumentIds',
            props.selectedDocumentIds.filter(
                (selectedId) => selectedId !== documentId,
            ),
        );
        return;
    }

    emit('update:selectedDocumentIds', [
        ...props.selectedDocumentIds,
        documentId,
    ]);
}

function toggleAllDocuments(): void {
    emit(
        'update:selectedDocumentIds',
        allDocumentsSelected.value
            ? []
            : props.documents.map((document) => document._id),
    );
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Add documents for context</DialogTitle>
                <DialogDescription>
                    Choose the documents the interrogation should use as
                    context.
                </DialogDescription>
            </DialogHeader>

            <label
                class="flex h-10 items-center gap-2 rounded-md border bg-background px-3 text-sm"
            >
                <Search class="size-4 text-muted-foreground" />
                <input
                    v-model="documentSearch"
                    type="search"
                    class="min-w-0 flex-1 bg-transparent outline-none placeholder:text-muted-foreground"
                    placeholder="Search documents..."
                />
            </label>

            <div class="max-h-96 overflow-y-auto rounded-lg border">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 border-b bg-muted/30 px-4 py-3 text-left hover:bg-muted/60"
                    @click="toggleAllDocuments"
                >
                    <input
                        type="checkbox"
                        class="size-4 rounded border-input"
                        :checked="allDocumentsSelected"
                        @click.stop="toggleAllDocuments"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">Select all documents</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Include every available document as context.
                        </p>
                    </div>
                </button>

                <button
                    v-for="document in filteredDocuments"
                    :key="document._id"
                    type="button"
                    class="flex w-full items-center gap-3 border-b px-4 py-3 text-left last:border-b-0 hover:bg-muted/50"
                    @click="toggleDocument(document._id)"
                >
                    <input
                        type="checkbox"
                        class="size-4 rounded border-input"
                        :checked="isDocumentSelected(document._id)"
                        @click.stop="toggleDocument(document._id)"
                    />
                    <DocumentIcon
                        :icon="documentIcon(document)"
                        class="size-8 shrink-0"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium">
                            {{ document.original_name }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ fileExt(document) || 'FILE' }} -
                            {{ formatSize(document.size) }} -
                            {{ document.status }}
                        </p>
                    </div>
                </button>

                <div
                    v-if="filteredDocuments.length === 0"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No documents found.
                </div>
            </div>

            <DialogFooter class="items-center gap-3">
                <p class="mr-auto text-sm text-muted-foreground">
                    {{ selectedDocumentsCount }} selected
                </p>
                <Button @click="open = false">Done</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
