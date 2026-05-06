<script setup lang="ts">
import DashboardFilters from '@/components/documents/dashboard/DashboardFilters.vue';
import DeleteDocumentDialog from '@/components/documents/dashboard/DeleteDocumentDialog.vue';
import DocumentDetailsPanel from '@/components/documents/dashboard/DocumentDetailsPanel.vue';
import DocumentsTable from '@/components/documents/dashboard/DocumentsTable.vue';
import {
    fileExt,
    statusKind,
} from '@/components/documents/dashboard/documentUtils';
import type {
    SortKey,
    StatusFilter,
    UploadItem,
} from '@/components/documents/dashboard/types';
import AppLayout from '@/layouts/AppLayout.vue';
import { home as dashboard } from '@/routes/dashboard';
import documents from '@/routes/documents';
import favoritesDocuments from '@/routes/favorites-documents';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Favorites', href: favoritesDocuments.index.url() },
];

const page = usePage();
const user = page.props.auth.user as { name: string };

const favoriteDocuments = computed<UploadItem[]>(
    () => ((page.props as any).favoriteDocuments ?? []) as UploadItem[],
);

const selectedDocumentId = ref<string | null>(null);
const searchQuery = ref('');
const statusFilter = ref<StatusFilter>('all');
const typeFilter = ref('all');
const sortKey = ref<SortKey>('newest');
const deleteDialogOpen = ref(false);
const deletingUpload = ref<UploadItem | null>(null);
const isDeleting = ref(false);
const favoriteDocumentId = ref<string | null>(null);

const documentTypes = computed(() => {
    return Array.from(
        new Set(favoriteDocuments.value.map((upload) => fileExt(upload))),
    ).filter(Boolean);
});

const filteredDocuments = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return [...favoriteDocuments.value]
        .filter((upload) => {
            const matchesSearch =
                !query || upload.original_name.toLowerCase().includes(query);
            const matchesStatus =
                statusFilter.value === 'all' ||
                statusKind(upload) === statusFilter.value;
            const matchesType =
                typeFilter.value === 'all' ||
                fileExt(upload) === typeFilter.value;

            return matchesSearch && matchesStatus && matchesType;
        })
        .sort((a, b) => {
            if (sortKey.value === 'name') {
                return a.original_name.localeCompare(b.original_name);
            }

            const aTime = new Date(a.created_at ?? 0).getTime();
            const bTime = new Date(b.created_at ?? 0).getTime();

            return sortKey.value === 'oldest' ? aTime - bTime : bTime - aTime;
        });
});

const selectedDocument = computed(() => {
    return (
        favoriteDocuments.value.find(
            (upload) => upload._id === selectedDocumentId.value,
        ) ?? null
    );
});

watch(
    favoriteDocuments,
    (nextDocuments) => {
        if (
            selectedDocumentId.value &&
            nextDocuments.some(
                (upload) => upload._id === selectedDocumentId.value,
            )
        ) {
            return;
        }

        selectedDocumentId.value = null;
    },
    { immediate: true },
);

function selectDocument(upload: UploadItem) {
    selectedDocumentId.value = upload._id;
}

function viewDocument(upload: UploadItem) {
    router.visit(documents.view.url({ query: { id: upload._id } }));
}

function closeDetails() {
    selectedDocumentId.value = null;
}

function openDeleteDialog(upload: UploadItem) {
    deletingUpload.value = upload;
    deleteDialogOpen.value = true;
}

function handleDeleteDialogOpen(open: boolean) {
    deleteDialogOpen.value = open;

    if (!open) {
        deletingUpload.value = null;
    }
}

function confirmDelete() {
    if (!deletingUpload.value) return;

    isDeleting.value = true;

    router.post(
        documents.delete.url({ query: { id: deletingUpload.value._id } }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isDeleting.value = false;
            },
            onSuccess: () => {
                handleDeleteDialogOpen(false);
            },
        },
    );
}

function toggleFavorite(upload: UploadItem) {
    favoriteDocumentId.value = upload._id;

    router.post(
        favoritesDocuments.mark.url({ query: { id: upload._id } }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                favoriteDocumentId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Favorite documents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main
            class="flex h-full min-h-0 flex-col overflow-hidden bg-background px-4 py-4 sm:px-6 sm:py-6"
        >
            <div class="flex min-h-0 flex-1 flex-col gap-6 xl:flex-row">
                <section class="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div class="mb-6">
                        <h1 class="text-2xl font-semibold">Favorites</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Owner:
                            <span class="text-foreground">{{ user.name }}</span>
                        </p>
                    </div>

                    <DashboardFilters
                        v-model:search-query="searchQuery"
                        v-model:status-filter="statusFilter"
                        v-model:type-filter="typeFilter"
                        v-model:sort-key="sortKey"
                        :document-types="documentTypes"
                        :show-upload-button="false"
                    />

                    <DocumentDetailsPanel
                        v-if="selectedDocument"
                        class="mb-6 xl:hidden"
                        :upload="selectedDocument"
                        :owner-name="user.name"
                        @close="closeDetails"
                        @delete="openDeleteDialog"
                    />

                    <DocumentsTable
                        :uploads="filteredDocuments"
                        :selected-document-id="selectedDocumentId"
                        :favorite-document-id="favoriteDocumentId"
                        @select="selectDocument"
                        @view="viewDocument"
                        @delete="openDeleteDialog"
                        @toggle-favorite="toggleFavorite"
                    />
                </section>

                <DocumentDetailsPanel
                    v-if="selectedDocument"
                    class="hidden xl:flex"
                    :upload="selectedDocument"
                    :owner-name="user.name"
                    @close="closeDetails"
                    @delete="openDeleteDialog"
                />
            </div>

            <DeleteDocumentDialog
                :open="deleteDialogOpen"
                :upload="deletingUpload"
                :is-deleting="isDeleting"
                @update:open="handleDeleteDialogOpen"
                @confirm="confirmDelete"
            />
        </main>
    </AppLayout>
</template>
