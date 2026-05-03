<script setup lang="ts">
import DeletedDocumentDetailsPanel from '@/components/documents/trash/DeletedDocumentDetailsPanel.vue';
import DeletedDocumentsTable from '@/components/documents/trash/DeletedDocumentsTable.vue';
import TrashFilters from '@/components/documents/trash/TrashFilters.vue';
import TrashHeader from '@/components/documents/trash/TrashHeader.vue';
import { fileExt } from '@/components/documents/trash/documentUtils';
import type {
    DeletedDocument,
    TrashSortKey,
} from '@/components/documents/trash/types';
import AppLayout from '@/layouts/AppLayout.vue';
import documents from '@/routes/documents';
import trash from '@/routes/trash';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trash', href: trash.index.url() },
];

const page = usePage();
const user = page.props.auth.user as { name: string };

const deletedDocuments = computed<DeletedDocument[]>(
    () => ((page.props as any).deletedDocuments ?? []) as DeletedDocument[],
);

const selectedDocumentId = ref<string | null>(null);
const searchQuery = ref('');
const typeFilter = ref('all');
const sortKey = ref<TrashSortKey>('newest_deleted');
const restoringDocumentId = ref<string | null>(null);

const documentTypes = computed(() => {
    return Array.from(
        new Set(deletedDocuments.value.map((document) => fileExt(document))),
    ).filter(Boolean);
});

const filteredDocuments = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return [...deletedDocuments.value]
        .filter((document) => {
            const matchesSearch =
                !query || document.original_name.toLowerCase().includes(query);
            const matchesType =
                typeFilter.value === 'all' ||
                fileExt(document) === typeFilter.value;

            return matchesSearch && matchesType;
        })
        .sort((a, b) => {
            if (sortKey.value === 'name') {
                return a.original_name.localeCompare(b.original_name);
            }

            const aTime = new Date(a.deleted_at ?? 0).getTime();
            const bTime = new Date(b.deleted_at ?? 0).getTime();

            return sortKey.value === 'oldest_deleted'
                ? aTime - bTime
                : bTime - aTime;
        });
});

const selectedDocument = computed(() => {
    return (
        deletedDocuments.value.find(
            (document) => document._id === selectedDocumentId.value,
        ) ?? null
    );
});

watch(
    deletedDocuments,
    (nextDocuments) => {
        if (
            selectedDocumentId.value &&
            nextDocuments.some(
                (document) => document._id === selectedDocumentId.value,
            )
        ) {
            return;
        }

        selectedDocumentId.value = null;
    },
    { immediate: true },
);

function selectDocument(document: DeletedDocument) {
    selectedDocumentId.value = document._id;
}

function closeDetails() {
    selectedDocumentId.value = null;
}

function restoreDocument(document: DeletedDocument) {
    restoringDocumentId.value = document._id;

    router.post(
        documents.restore.url({ query: { id: document._id } }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                restoringDocumentId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Deleted documents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main
            class="flex h-full min-h-0 flex-col overflow-hidden bg-background px-6 py-6"
        >
            <div class="flex min-h-0 flex-1 gap-6">
                <section class="flex min-h-0 min-w-0 flex-1 flex-col">
                    <TrashHeader :owner-name="user.name" />

                    <TrashFilters
                        v-model:search-query="searchQuery"
                        v-model:type-filter="typeFilter"
                        v-model:sort-key="sortKey"
                        :document-types="documentTypes"
                    />

                    <DeletedDocumentsTable
                        :documents="filteredDocuments"
                        :selected-document-id="selectedDocumentId"
                        :restoring-document-id="restoringDocumentId"
                        @select="selectDocument"
                        @restore="restoreDocument"
                    />
                </section>

                <DeletedDocumentDetailsPanel
                    v-if="selectedDocument"
                    :document="selectedDocument"
                    :owner-name="user.name"
                    :restoring-document-id="restoringDocumentId"
                    @close="closeDetails"
                    @restore="restoreDocument"
                />
            </div>
        </main>
    </AppLayout>
</template>
