<script setup lang="ts">
import EditDocumentActionsBar from '@/components/documents/edit/EditDocumentActionsBar.vue';
import EditDocumentAssistantSidebar from '@/components/documents/edit/EditDocumentAssistantSidebar.vue';
import EditDocumentEditorPane from '@/components/documents/edit/EditDocumentEditorPane.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { home as dashboard } from '@/routes/dashboard';
import { view as viewDocument } from '@/routes/documents';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

type ChatMessage = {
    role: 'user' | 'assistant';
    content: string;
};

type ChatData = {
    id: string;
    title: string;
    messages: ChatMessage[];
};

const chatData = computed<ChatData[]>(() => ((page.props as any).chatData ?? []) as ChatData[]);

const documentInfo = computed(
    () => (page.props.document ?? {}) as Record<string, unknown>,
);

const documentId = computed(() => {
    const id = documentInfo.value._id ?? documentInfo.value.id;
    return typeof id === 'string' ? id : '';
});

const documentName = computed(() => {
    const originalName = documentInfo.value.original_name;
    return typeof originalName === 'string'
        ? originalName
        : 'untitled_document.md';
});

const documentMimeType = computed(() => {
    const mimeType = documentInfo.value.mime_type;
    return typeof mimeType === 'string' ? mimeType : undefined;
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'My Documents',
        href: '#',
    },
    {
        title: documentName.value,
        href: documentId.value
            ? viewDocument.url({ query: { id: documentId.value } })
            : '#',
    },
    {
        title: 'Edit Document',
        href: '#',
    },
]);
</script>

<template>
    <Head title="Edit Document" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100vh-4rem)] flex-col bg-muted/20">
            <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
                <EditDocumentEditorPane
                    :file-id="documentId"
                    :file-name="documentName"
                    :mime-type="documentMimeType"
                />
                <EditDocumentAssistantSidebar
                    :chats="chatData"
                />
            </div>
        </div>
    </AppLayout>
</template>
