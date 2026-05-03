<script setup lang="ts">
import DashboardFilters from '@/components/documents/dashboard/DashboardFilters.vue';
import DashboardHeader from '@/components/documents/dashboard/DashboardHeader.vue';
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
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

const page = usePage();
const user = page.props.auth.user as { name: string };

const uploads = computed<UploadItem[]>(
    () => ((page.props as any).uploads ?? []) as UploadItem[],
);

const selectedDocumentId = ref<string | null>(null);
const searchQuery = ref('');
const statusFilter = ref<StatusFilter>('all');
const typeFilter = ref('all');
const sortKey = ref<SortKey>('newest');
const deleteDialogOpen = ref(false);
const deletingUpload = ref<UploadItem | null>(null);
const isDeleting = ref(false);

const documentTypes = computed(() => {
    return Array.from(
        new Set(uploads.value.map((upload) => fileExt(upload))),
    ).filter(Boolean);
});

const filteredUploads = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return [...uploads.value]
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
        uploads.value.find(
            (upload) => upload._id === selectedDocumentId.value,
        ) ?? null
    );
});

watch(
    uploads,
    (nextUploads) => {
        if (
            selectedDocumentId.value &&
            nextUploads.some(
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
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main
            class="flex h-full min-h-0 flex-col overflow-hidden bg-background px-6 py-6"
        >
            <div class="flex min-h-0 flex-1 gap-6">
                <section class="flex min-h-0 min-w-0 flex-1 flex-col">
                    <DashboardHeader :owner-name="user.name" />

                    <DashboardFilters
                        v-model:search-query="searchQuery"
                        v-model:status-filter="statusFilter"
                        v-model:type-filter="typeFilter"
                        v-model:sort-key="sortKey"
                        :document-types="documentTypes"
                    />

                    <DocumentsTable
                        :uploads="filteredUploads"
                        :selected-document-id="selectedDocumentId"
                        @select="selectDocument"
                        @view="viewDocument"
                        @delete="openDeleteDialog"
                    />
                </section>

                <DocumentDetailsPanel
                    v-if="selectedDocument"
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
