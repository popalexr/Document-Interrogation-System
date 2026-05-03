<script setup lang="ts">
import FilePreview from '@/components/FilePreview.vue';
import Button from '@/components/ui/button/Button.vue';
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue';
import DropdownMenuContent from '@/components/ui/dropdown-menu/DropdownMenuContent.vue';
import DropdownMenuItem from '@/components/ui/dropdown-menu/DropdownMenuItem.vue';
import DropdownMenuTrigger from '@/components/ui/dropdown-menu/DropdownMenuTrigger.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import api from '@/routes/api';
import { home as dashboard } from '@/routes/dashboard';
import documents from '@/routes/documents';
import type { AppPageProps, BreadcrumbItem } from '@/types';
import { Icon as DocumentIcon } from '@iconify/vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Download, MoreVertical } from 'lucide-vue-next';
import { computed } from 'vue';

type DocumentRecord = {
    _id: string;
    original_name: string;
    mime_type: string;
    size: number;
    r2_key: string;
};

type DocumentPageProps = AppPageProps<{
    document: DocumentRecord;
}>;

const page = usePage<DocumentPageProps>();

const document = computed(() => page.props.document);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: document.value.original_name,
        href: '#',
    },
]);

const selected = computed(() => ({
    url: new URL(
        api.viewFile.url({ query: { id: document.value._id } }),
        window.location.origin,
    ).toString(),
    name: document.value.original_name,
}));

const extension = computed(() => {
    const index = document.value.original_name.lastIndexOf('.');

    return index === -1
        ? 'FILE'
        : document.value.original_name.slice(index + 1).toUpperCase();
});

const formattedSize = computed(() => {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = document.value.size;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
});

const documentIcon = computed(() => {
    switch (extension.value) {
        case 'DOC':
        case 'DOCX':
            return 'vscode-icons:file-type-word';
        case 'PDF':
            return 'vscode-icons:file-type-pdf2';
        case 'PPT':
        case 'PPTX':
            return 'vscode-icons:file-type-powerpoint';
        case 'XLS':
        case 'XLSX':
        case 'CSV':
            return 'vscode-icons:file-type-excel';
        case 'MD':
        case 'TXT':
        case 'TEX':
            return 'vscode-icons:file-type-text';
        case 'JSON':
            return 'vscode-icons:file-type-json';
        default:
            return 'vscode-icons:default-file';
    }
});
</script>

<template>
    <Head :title="document.original_name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="flex min-h-[calc(100vh-4.5rem)] flex-col gap-5 p-6">
            <section
                class="flex shrink-0 items-center justify-between gap-4 rounded-lg border bg-background px-6 py-5 shadow-sm"
            >
                <div class="flex min-w-0 items-center gap-4">
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border bg-muted/40"
                    >
                        <DocumentIcon
                            :icon="documentIcon"
                            class="h-8 w-8"
                            :aria-label="extension"
                        />
                    </div>

                    <div class="min-w-0 space-y-1">
                        <h1 class="truncate text-xl font-semibold">
                            {{ document.original_name }}
                        </h1>
                        <div
                            class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground"
                        >
                            <span>{{ extension }}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span>{{ formattedSize }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    <Button variant="outline" class="h-11 gap-2" as-child>
                        <a
                            :href="
                                documents.downloadDocument.url({
                                    query: { id: document._id },
                                })
                            "
                            target="_blank"
                            rel="noopener"
                        >
                            <Download class="h-4 w-4" />
                            <span>Download</span>
                        </a>
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="outline"
                                size="icon"
                                class="h-11 w-11"
                                aria-label="Document actions"
                            >
                                <MoreVertical class="h-5 w-5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-40">
                            <DropdownMenuItem :as-child="true">
                                <Link
                                    as="button"
                                    class="block w-full cursor-pointer text-left"
                                    :href="
                                        documents.interrogate.url({
                                            query: { id: document._id },
                                        })
                                    "
                                    prefetch
                                >
                                    Interrogate
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem :as-child="true">
                                <Link
                                    as="button"
                                    class="block w-full cursor-pointer text-left"
                                    :href="
                                        documents.edit.url({
                                            query: { id: document._id },
                                        })
                                    "
                                    prefetch
                                >
                                    Edit
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </section>

            <section
                class="min-h-[42rem] flex-1 overflow-hidden rounded-lg shadow-sm md:min-h-0"
            >
                <FilePreview
                    :file-url="selected.url"
                    :file-name="selected.name"
                    :file-id="document._id"
                    :mime-type="document.mime_type"
                    :show-header="false"
                />
            </section>
        </main>
    </AppLayout>
</template>
