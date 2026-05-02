<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/AppLayout.vue';
import documents from '@/routes/documents';
import trash from '@/routes/trash';
import { type BreadcrumbItem } from '@/types';
import { Icon as DocumentIcon } from '@iconify/vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDownUp,
    ChevronDown,
    FileText,
    Info,
    RefreshCcw,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trash', href: trash.index.url() },
];

const page = usePage();
const user = page.props.auth.user as { name: string };

type DeletedDocument = {
    _id: string;
    original_name: string;
    mime_type: string;
    size: number;
    status?: string;
    r2_key?: string;
    created_at?: string | Date;
    updated_at?: string | Date;
    deleted_at?: string | Date;
};

type SortKey = 'newest_deleted' | 'oldest_deleted' | 'name';

const deletedDocuments = computed<DeletedDocument[]>(
    () => ((page.props as any).deletedDocuments ?? []) as DeletedDocument[],
);

const selectedDocumentId = ref<string | null>(null);
const searchQuery = ref('');
const typeFilter = ref('all');
const sortKey = ref<SortKey>('newest_deleted');
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

function formatSize(bytes: number | undefined): string {
    if (!bytes && bytes !== 0) return '';

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;

    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex += 1;
    }

    return `${size.toFixed(unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
}

function formatDate(value: string | Date | undefined): string {
    if (!value) return '';

    const date = typeof value === 'string' ? new Date(value) : value;

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function fileExt(document: DeletedDocument | null | undefined): string {
    const name = document?.original_name;
    if (!name) return '';

    const index = name.lastIndexOf('.');
    if (index === -1) return '';

    return name.slice(index + 1).toUpperCase();
}

function extClasses(extension: string): string {
    switch (extension) {
        case 'PDF':
            return 'border-red-200 bg-red-50 text-red-600';
        case 'DOC':
        case 'DOCX':
            return 'border-blue-200 bg-blue-50 text-blue-600';
        case 'XLS':
        case 'XLSX':
            return 'border-green-200 bg-green-50 text-green-600';
        case 'PPT':
        case 'PPTX':
            return 'border-orange-200 bg-orange-50 text-orange-600';
        default:
            return 'border-slate-200 bg-white text-slate-700';
    }
}

function documentIcon(document: DeletedDocument): string {
    switch (fileExt(document)) {
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
}

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
                    <div class="mb-6 flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h1
                                    class="text-2xl font-semibold tracking-tight"
                                >
                                    Deleted Documents
                                </h1>
                                <TooltipProvider :delay-duration="150">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                class="inline-flex size-5 items-center justify-center rounded-full text-muted-foreground hover:text-foreground"
                                                aria-label="Trash information"
                                            >
                                                <Info class="size-4" />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Review deleted documents and restore
                                            files back to your document library.
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Owner:
                                <span class="text-foreground">{{
                                    user.name
                                }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="mb-6 flex flex-wrap items-center gap-3">
                        <label
                            class="flex h-10 min-w-[18rem] items-center gap-2 rounded-md border bg-background px-3 text-sm shadow-xs"
                        >
                            <Search class="size-4 text-muted-foreground" />
                            <input
                                v-model="searchQuery"
                                type="search"
                                class="min-w-0 flex-1 bg-transparent outline-none placeholder:text-muted-foreground"
                                placeholder="Search deleted documents..."
                            />
                        </label>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" class="gap-2">
                                    <FileText class="size-4" />
                                    Type:
                                    <span class="font-medium">
                                        {{
                                            typeFilter === 'all'
                                                ? 'All'
                                                : typeFilter
                                        }}
                                    </span>
                                    <ChevronDown class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-36">
                                <DropdownMenuItem @click="typeFilter = 'all'">
                                    All
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-for="type in documentTypes"
                                    :key="type"
                                    @click="typeFilter = type"
                                >
                                    {{ type }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" class="gap-2">
                                    <ArrowDownUp class="size-4" />
                                    Sort:
                                    <span class="font-medium">
                                        {{
                                            sortKey === 'newest_deleted'
                                                ? 'Newest deleted'
                                                : sortKey === 'oldest_deleted'
                                                  ? 'Oldest deleted'
                                                  : 'Name'
                                        }}
                                    </span>
                                    <ChevronDown class="size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-44">
                                <DropdownMenuItem
                                    @click="sortKey = 'newest_deleted'"
                                >
                                    Newest deleted
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @click="sortKey = 'oldest_deleted'"
                                >
                                    Oldest deleted
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="sortKey = 'name'">
                                    Name
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Button
                            variant="outline"
                            class="gap-2"
                            @click="typeFilter = 'all'"
                        >
                            <SlidersHorizontal class="size-4" />
                            Clear filters
                        </Button>
                    </div>

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
                            v-if="!filteredDocuments.length"
                            class="flex min-h-0 flex-1 items-center justify-center text-sm text-muted-foreground"
                        >
                            No deleted documents found.
                        </div>

                        <div v-else class="min-h-0 flex-1 overflow-auto">
                            <ContextMenu
                                v-for="document in filteredDocuments"
                                :key="document._id"
                            >
                                <ContextMenuTrigger>
                                    <div
                                        class="grid min-h-16 cursor-pointer grid-cols-[3rem_minmax(18rem,1.6fr)_7rem_12rem_12rem] items-center border-b px-4 text-sm transition-colors last:border-b-0 hover:bg-muted/40"
                                        :class="{
                                            'bg-muted/50':
                                                selectedDocumentId ===
                                                document._id,
                                        }"
                                        @click="selectDocument(document)"
                                    >
                                        <div>
                                            <input
                                                type="checkbox"
                                                class="size-4 rounded border-input"
                                                :aria-label="`Select ${document.original_name}`"
                                                @click.stop
                                            />
                                        </div>

                                        <div
                                            class="flex min-w-0 items-center gap-3"
                                        >
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
                                                    {{
                                                        formatSize(
                                                            document.size,
                                                        )
                                                    }}
                                                    <span class="px-1"
                                                        >&middot;</span
                                                    >
                                                    {{ document.mime_type }}
                                                </p>
                                            </div>
                                        </div>

                                        <div>
                                            <Badge
                                                variant="outline"
                                                class="border text-xs"
                                                :class="
                                                    extClasses(
                                                        fileExt(document),
                                                    )
                                                "
                                            >
                                                {{
                                                    fileExt(document) || 'FILE'
                                                }}
                                            </Badge>
                                        </div>

                                        <div class="text-muted-foreground">
                                            {{
                                                formatDate(document.deleted_at)
                                            }}
                                        </div>

                                        <div class="flex justify-center">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="gap-2"
                                                :disabled="
                                                    restoringDocumentId ===
                                                    document._id
                                                "
                                                @click.stop="
                                                    restoreDocument(document)
                                                "
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
                                            :disabled="
                                                restoringDocumentId ===
                                                document._id
                                            "
                                            @click="restoreDocument(document)"
                                        >
                                            <RefreshCcw class="mr-2 size-4" />
                                            Restore
                                        </button>
                                    </ContextMenuItem>
                                </ContextMenuContent>
                            </ContextMenu>
                        </div>
                    </div>
                </section>

                <aside
                    v-if="selectedDocument"
                    class="flex w-[26rem] shrink-0 flex-col rounded-lg border bg-background shadow-xs"
                >
                    <div class="flex items-center justify-between p-5">
                        <h2 class="font-semibold">Document details</h2>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            @click="closeDetails"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>

                    <div class="space-y-5 px-5 pb-6">
                        <div class="flex items-center gap-3">
                            <DocumentIcon
                                :icon="documentIcon(selectedDocument)"
                                class="size-12 shrink-0"
                            />
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ selectedDocument.original_name }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ formatSize(selectedDocument.size) }}
                                    <span class="px-1">&middot;</span>
                                    {{ fileExt(selectedDocument) || 'FILE' }}
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
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">
                                    Deleted date
                                </dt>
                                <dd class="text-right">
                                    {{
                                        formatDate(selectedDocument.deleted_at)
                                    }}
                                </dd>
                            </div>
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">
                                    Upload date
                                </dt>
                                <dd class="text-right">
                                    {{
                                        formatDate(selectedDocument.created_at)
                                    }}
                                </dd>
                            </div>
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">MIME type</dt>
                                <dd class="text-right break-words">
                                    {{ selectedDocument.mime_type }}
                                </dd>
                            </div>
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">Size</dt>
                                <dd class="text-right">
                                    {{ formatSize(selectedDocument.size) }}
                                </dd>
                            </div>
                            <div
                                class="grid grid-cols-[7rem_minmax(0,1fr)] gap-4"
                            >
                                <dt class="text-muted-foreground">Owner</dt>
                                <dd class="text-right">{{ user.name }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mt-auto border-t p-5">
                        <h3 class="mb-3 text-sm font-semibold">Actions</h3>
                        <Button
                            class="w-full justify-start gap-2"
                            :disabled="
                                restoringDocumentId === selectedDocument._id
                            "
                            @click="restoreDocument(selectedDocument)"
                        >
                            <RefreshCcw class="size-4" />
                            Restore
                        </Button>
                    </div>
                </aside>
            </div>
        </main>
    </AppLayout>
</template>
